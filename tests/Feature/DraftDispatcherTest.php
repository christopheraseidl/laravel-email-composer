<?php

use CSeidl\EmailComposer\Actions\Drafts\CancelSend;
use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Enums\EmailAudience;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Facades\EmailComposer;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Jobs\SendEmailJob;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use CSeidl\EmailComposer\Services\DraftDispatcher;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Factories\UserFactory;

use function Pest\Livewire\livewire;

beforeEach(function () {
    config()->set('email-composer.queue.connection', 'sync');
    config()->set('email-composer.queue.queue', 'emails');
});

function approvedDraftWithRecipients(int $count = 3): EmailDraft
{
    $draft = EmailDraft::factory()->approved()->create();
    $draft->recipients()->attach(Recipient::factory()->count($count)->create());

    return $draft;
}

/** @return ArrayObject<int, int> ids of the recipients the sender is called for */
function recordSends(): ArrayObject
{
    $sent = new ArrayObject;

    test()->mock(EmailSenderInterface::class)->shouldReceive('send')
        ->andReturnUsing(function (EmailDraft $draft, Recipient $recipient) use ($sent) {
            $sent[] = $recipient->id;
        });

    return $sent;
}

/**
 * @param  iterable<int, int|Recipient>  $recipients
 * @return list<int>
 */
function sortedIds(iterable $recipients): array
{
    return collect($recipients)->map(fn ($r) => $r instanceof Recipient ? $r->id : $r)->sort()->values()->all();
}

/** @return list<int> */
function pivotIds(EmailDraft $draft): array
{
    return sortedIds($draft->recipients()->pluck('email_composer_recipients.id'));
}

it('queues one send job per recipient on the configured queue', function () {
    Queue::fake();
    $draft = approvedDraftWithRecipients(3);

    app(DraftDispatcher::class)->dispatch($draft);

    Queue::assertPushedOn('emails', SendEmailJob::class);
    expect(Queue::pushed(SendEmailJob::class)->map(fn (SendEmailJob $job) => $job->recipient->id)->sort()->values()->all())
        ->toBe(pivotIds($draft))
        ->and($draft->recipients()->wherePivotNull('queued_at')->count())->toBe(0);
});

it('pushes the send jobs in chunks of bulk.chunk_size', function () {
    config()->set('email-composer.bulk.chunk_size', 2);
    $chunks = [];
    $queue = Mockery::mock(QueueContract::class);
    $queue->shouldReceive('bulk')->andReturnUsing(function (array $jobs) use (&$chunks) {
        $chunks[] = count($jobs);
    });
    $this->mock(QueueFactory::class)->shouldReceive('connection')->with('sync')->andReturn($queue);

    app(DraftDispatcher::class)->dispatch(approvedDraftWithRecipients(5));

    expect($chunks)->toBe([2, 2, 1]);
});

it('refuses a draft that is not approved', function (EmailDraftStatus $status) {
    Queue::fake();
    $draft = EmailDraft::factory()->create(['status' => $status]);
    $draft->recipients()->attach(Recipient::factory()->create());

    expect(fn () => app(DraftDispatcher::class)->dispatch($draft))->toThrow(DomainException::class);

    Queue::assertNothingPushed();
})->with([
    'draft' => EmailDraftStatus::Draft,
    'under review' => EmailDraftStatus::UnderReview,
    'already sending' => EmailDraftStatus::Sending,
    'already sent' => EmailDraftStatus::Sent,
]);

it('marks the draft sending when it is dispatched', function () {
    Queue::fake();
    $draft = approvedDraftWithRecipients(1);

    app(DraftDispatcher::class)->dispatch($draft);

    expect($draft->status)->toBe(EmailDraftStatus::Sending)
        ->and($draft->fresh()->status)->toBe(EmailDraftStatus::Sending);
});

it('refuses a second dispatch of a draft that is already sending', function () {
    Queue::fake();
    $draft = approvedDraftWithRecipients(1);
    $stale = EmailDraft::find($draft->getKey());

    app(DraftDispatcher::class)->dispatch($draft);

    expect(fn () => app(DraftDispatcher::class)->dispatch($stale))
        ->toThrow(DomainException::class, 'already sending or sent');

    Queue::assertPushed(SendEmailJob::class, 1);
});

it('ends sent when dispatched on the sync queue', function () {
    $draft = approvedDraftWithRecipients(1);
    $this->mock(EmailSenderInterface::class)->shouldReceive('send')->once();

    app(DraftDispatcher::class)->dispatch($draft);

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::Sent);
});

it('ends cancelled when cancelled during the send and sent when not', function (bool $cancel, EmailDraftStatus $status) {
    config()->set('email-composer.bulk.chunk_size', 1);
    $draft = approvedDraftWithRecipients(3);
    $this->mock(EmailSenderInterface::class)->shouldReceive('send')
        ->andReturnUsing(fn () => $cancel ? app(CancelSend::class)($draft->fresh()) : null);

    app(DraftDispatcher::class)->dispatch($draft);

    expect($draft->fresh()->status)->toBe($status);
})->with([
    'cancelled' => [true, EmailDraftStatus::Cancelled],
    'not cancelled' => [false, EmailDraftStatus::Sent],
]);

it('resends to every recipient after a cancelled send is edited and approved again', function () {
    Storage::fake(config('email-composer.storage.disk'));
    config()->set('email-composer.bulk.chunk_size', 1);
    $draft = approvedDraftWithRecipients(3);
    $sends = [];
    $cancel = true;
    $this->mock(EmailSenderInterface::class)->shouldReceive('send')
        ->andReturnUsing(function (EmailDraft $sent, Recipient $recipient) use (&$sends, &$cancel) {
            $sends[] = [$recipient->id, $sent->subjectFor('en')];

            if ($cancel) {
                app(CancelSend::class)($sent->fresh());
            }
        });

    app(DraftDispatcher::class)->dispatch($draft);

    expect($draft->refresh()->status)->toBe(EmailDraftStatus::Cancelled)
        ->and($draft->recipients->pluck('pivot.delivery_status')->sort()->values()->all())
        ->toBe(['cancelled', 'cancelled', 'sent']);

    $draft->returnToDraft();
    $draft->update(['subject' => ['en' => 'Fixed', 'es' => 'Arreglado']]);
    $draft->submit();
    $draft->approve(UserFactory::new()->create());
    $cancel = false;
    $sends = [];

    app(DraftDispatcher::class)->dispatch($draft->refresh());

    expect($draft->refresh()->status)->toBe(EmailDraftStatus::Sent)
        ->and(sortedIds(array_column($sends, 0)))->toBe(pivotIds($draft))
        ->and(array_unique(array_column($sends, 1)))->toBe(['Fixed'])
        ->and($draft->recipients->map(fn (Recipient $r) => $r->pivot->only('delivery_status', 'attempts'))->unique()->all())
        ->toBe([['delivery_status' => 'sent', 'attempts' => 1]]);
});

it('refuses a draft with no recipients', function () {
    Queue::fake();

    expect(fn () => app(DraftDispatcher::class)->dispatch(EmailDraft::factory()->approved()->create()))
        ->toThrow(DomainException::class, 'no recipients');

    Queue::assertNothingPushed();
});

it('sends to every recipient and marks the draft sent', function () {
    $draft = approvedDraftWithRecipients(2);
    $this->mock(EmailSenderInterface::class)->shouldReceive('send')->twice();

    EmailComposer::send($draft);

    expect($draft->refresh())
        ->status->toBe(EmailDraftStatus::Sent)
        ->sent_at->not->toBeNull();
    expect($draft->recipients->pluck('pivot.delivery_status')->all())->toBe(['sent', 'sent']);
});

it('marks the draft sent only after every chunk has been sent', function () {
    config()->set('email-composer.bulk.chunk_size', 1);
    $draft = approvedDraftWithRecipients(3);
    $this->mock(EmailSenderInterface::class)->shouldReceive('send')->times(3);

    EmailComposer::send($draft);

    expect($draft->refresh()->status)->toBe(EmailDraftStatus::Sent);
    expect($draft->recipients->pluck('pivot.delivery_status')->all())->toBe(['sent', 'sent', 'sent']);
});

it('sends from the Filament send action', function () {
    Queue::fake();
    $draft = approvedDraftWithRecipients(2);

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])->callAction('send');

    Queue::assertPushed(SendEmailJob::class, 2);
});

it('sends to every recipient when the audience is all', function () {
    $recipients = Recipient::factory()->count(3)->create();
    Recipient::factory()->create()->delete();
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::All]);
    $sent = recordSends();

    app(DraftDispatcher::class)->dispatch($draft);

    expect(sortedIds($sent))->toBe(sortedIds($recipients))
        ->and(pivotIds($draft))->toBe(sortedIds($recipients));
});

it('sends to the members of the list when the audience is a list', function () {
    $members = Recipient::factory()->count(2)->create();
    Recipient::factory()->count(2)->create();
    $list = RecipientList::factory()->create();
    $list->recipients()->attach($members);
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::List, 'recipient_list_id' => $list->id]);
    $sent = recordSends();

    app(DraftDispatcher::class)->dispatch($draft);

    expect(sortedIds($sent))->toBe(sortedIds($members))
        ->and(pivotIds($draft))->toBe(sortedIds($members));
});

it('sends to the attached recipients when the audience is selected', function () {
    $chosen = Recipient::factory()->count(2)->create();
    Recipient::factory()->count(2)->create();
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::Selected]);
    $draft->recipients()->attach($chosen);
    $sent = recordSends();

    app(DraftDispatcher::class)->dispatch($draft);

    expect(sortedIds($sent))->toBe(sortedIds($chosen))
        ->and(pivotIds($draft))->toBe(sortedIds($chosen));
});

it('refuses a list draft whose list was deleted', function (bool $force) {
    Queue::fake();
    $list = RecipientList::factory()->create();
    $list->recipients()->attach(Recipient::factory()->create());
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::List, 'recipient_list_id' => $list->id]);

    $force ? $list->forceDelete() : $list->delete();

    expect(fn () => app(DraftDispatcher::class)->dispatch($draft->fresh()))
        ->toThrow(DomainException::class, 'recipient list no longer exists');

    Queue::assertNothingPushed();
    expect($draft->fresh()->status)->toBe(EmailDraftStatus::Approved);
})->with([
    'soft deleted' => false,
    'force deleted' => true,
]);

it('refuses an all draft when there are no recipients', function () {
    Queue::fake();
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::All]);

    expect(fn () => app(DraftDispatcher::class)->dispatch($draft))
        ->toThrow(DomainException::class, 'no recipients');

    Queue::assertNothingPushed();
});

it('does not send to a recipient added to the list after dispatch', function () {
    Queue::fake();
    $member = Recipient::factory()->create();
    $list = RecipientList::factory()->create();
    $list->recipients()->attach($member);
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::List, 'recipient_list_id' => $list->id]);

    app(DraftDispatcher::class)->dispatch($draft);
    $list->recipients()->attach(Recipient::factory()->create());

    expect(pivotIds($draft))->toBe([$member->id]);
});

it('queues only what the rate limit has room for, and the rest on later scheduled runs', function () {
    config()->set('email-composer.queue.connection', 'database');
    config()->set('email-composer.queue.queue', null);
    config(['email-composer.queue.rate_limit_per_minute' => 2]);
    $sent = recordSends();
    $draft = approvedDraftWithRecipients(5);

    app(DraftDispatcher::class)->dispatch($draft);
    workDatabaseQueue();

    expect($sent)->toHaveCount(2)
        ->and(DB::table('jobs')->count())->toBe(0);

    $this->artisan('email-composer:queue-sends');
    workDatabaseQueue();

    expect($sent)->toHaveCount(2);

    foreach ([4, 5] as $count) {
        $this->travel(61)->seconds();
        $this->artisan('email-composer:queue-sends');
        workDatabaseQueue();

        expect($sent)->toHaveCount($count);
    }

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::Sending);

    $this->artisan('email-composer:queue-sends');

    expect(sortedIds($sent))->toBe(pivotIds($draft))
        ->and($draft->fresh()->status)->toBe(EmailDraftStatus::Sent);
});

it('queues a row again once its job has been gone longer than a job may retry', function () {
    Queue::fake();
    $draft = approvedDraftWithRecipients(1);

    app(DraftDispatcher::class)->dispatch($draft);
    $this->artisan('email-composer:queue-sends');

    Queue::assertPushed(SendEmailJob::class, 1);

    $this->travel(1)->day();
    $this->travel(1)->second();
    $this->artisan('email-composer:queue-sends');

    Queue::assertPushed(SendEmailJob::class, 2);
});

it('cancels every recipient not sent yet at once', function () {
    config()->set('email-composer.queue.connection', 'database');
    config()->set('email-composer.queue.queue', null);
    config(['email-composer.queue.rate_limit_per_minute' => 1]);
    recordSends();
    $draft = approvedDraftWithRecipients(3);

    app(DraftDispatcher::class)->dispatch($draft);
    workDatabaseQueue();
    app(CancelSend::class)($draft->fresh());

    expect($draft->recipients()->orderBy('email_composer_recipients.id')->pluck('delivery_status')->all())->toBe(['sent', 'cancelled', 'cancelled'])
        ->and($draft->fresh()->status)->toBe(EmailDraftStatus::Cancelled);
});

it('queues no more sends than the rate limit has room for, counting those still queued', function () {
    Queue::fake();
    config(['email-composer.queue.rate_limit_per_minute' => 2]);
    $draft = approvedDraftWithRecipients(5);

    app(DraftDispatcher::class)->dispatch($draft);
    $this->artisan('email-composer:queue-sends');

    Queue::assertPushed(SendEmailJob::class, 2);
});
