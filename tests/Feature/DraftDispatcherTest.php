<?php

use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Enums\EmailAudience;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Facades\EmailComposer;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\ListEmailDrafts;
use CSeidl\EmailComposer\Jobs\QueueDraftRecipients;
use CSeidl\EmailComposer\Jobs\SendEmailJob;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use CSeidl\EmailComposer\Services\DraftDispatcher;
use Filament\Actions\Testing\TestAction;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

use function Pest\Livewire\livewire;

pest()->use(RefreshDatabase::class);

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

it('batches the job that queues the recipients on the configured queue', function () {
    Bus::fake();
    $draft = approvedDraftWithRecipients(3);

    app(DraftDispatcher::class)->dispatch($draft);

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 1
        && $batch->jobs->first() instanceof QueueDraftRecipients
        && $batch->jobs->first()->draft->is($draft)
        && $batch->connection() === 'sync'
        && $batch->queue() === 'emails');
});

it('adds one send job per recipient to the batch in chunks of bulk.chunk_size', function () {
    config()->set('email-composer.bulk.chunk_size', 2);
    $draft = approvedDraftWithRecipients(5);

    $chunks = [];
    $batch = Mockery::mock(Batch::class);
    $batch->shouldReceive('cancelled')->andReturnFalse();
    $batch->shouldReceive('add')->andReturnUsing(function (array $jobs) use (&$chunks, $batch) {
        $chunks[] = $jobs;

        return $batch;
    });
    $this->mock(BatchRepository::class)->shouldReceive('find')->andReturn($batch);

    (new QueueDraftRecipients($draft))->withBatchId('batch')->handle();

    $jobs = collect($chunks)->flatten();

    expect(array_map('count', $chunks))->toBe([2, 2, 1])
        ->and($jobs->every(fn ($job) => $job instanceof SendEmailJob && $job->draft->is($draft)))->toBeTrue()
        ->and($jobs->pluck('recipient.id')->sort()->values()->all())
        ->toBe($draft->recipients->pluck('id')->sort()->values()->all());
});

it('uses the default connection when none is configured', function () {
    Bus::fake();
    config()->set('email-composer.queue.connection', null);

    app(DraftDispatcher::class)->dispatch(approvedDraftWithRecipients(1));

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->connection() === null);
});

it('uses the connection\'s default queue when none is configured', function () {
    Bus::fake();
    config()->set('email-composer.queue.queue', null);

    app(DraftDispatcher::class)->dispatch(approvedDraftWithRecipients(1));

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->queue() === null);
});

it('refuses a draft that is not approved', function (EmailDraftStatus $status) {
    Bus::fake();
    $draft = EmailDraft::factory()->create(['status' => $status]);
    $draft->recipients()->attach(Recipient::factory()->create());

    expect(fn () => app(DraftDispatcher::class)->dispatch($draft))->toThrow(DomainException::class);

    Bus::assertNothingBatched();
})->with([
    'draft' => EmailDraftStatus::Draft,
    'under review' => EmailDraftStatus::UnderReview,
    'already sending' => EmailDraftStatus::Sending,
    'already sent' => EmailDraftStatus::Sent,
]);

it('marks the draft sending when it is dispatched', function () {
    Bus::fake();
    $draft = approvedDraftWithRecipients(1);

    app(DraftDispatcher::class)->dispatch($draft);

    expect($draft->status)->toBe(EmailDraftStatus::Sending)
        ->and($draft->fresh()->status)->toBe(EmailDraftStatus::Sending);
});

it('refuses a second dispatch of a draft that is already sending', function () {
    Bus::fake();
    $draft = approvedDraftWithRecipients(1);
    $stale = EmailDraft::find($draft->getKey());

    app(DraftDispatcher::class)->dispatch($draft);

    expect(fn () => app(DraftDispatcher::class)->dispatch($stale))
        ->toThrow(DomainException::class, 'already sending or sent');

    Bus::assertBatchCount(1);
});

it('ends sent when dispatched on the sync queue', function () {
    $draft = approvedDraftWithRecipients(1);
    $this->mock(EmailSenderInterface::class)->shouldReceive('send')->once();

    app(DraftDispatcher::class)->dispatch($draft);

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::Sent);
});

it('refuses a draft with no recipients', function () {
    Bus::fake();

    expect(fn () => app(DraftDispatcher::class)->dispatch(EmailDraft::factory()->approved()->create()))
        ->toThrow(DomainException::class, 'no recipients');

    Bus::assertNothingBatched();
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
    Bus::fake();
    $draft = approvedDraftWithRecipients(2);

    livewire(ListEmailDrafts::class)->callAction(TestAction::make('send')->table($draft));

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->first() instanceof QueueDraftRecipients);
});

it('shows a refused send as an error notification', function () {
    Bus::fake();
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::Selected]);

    livewire(ListEmailDrafts::class)
        ->callAction(TestAction::make('send')->table($draft))
        ->assertNotified('Draft not sent');

    Bus::assertNothingBatched();
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
    Bus::fake();
    $list = RecipientList::factory()->create();
    $list->recipients()->attach(Recipient::factory()->create());
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::List, 'recipient_list_id' => $list->id]);

    $force ? $list->forceDelete() : $list->delete();

    expect(fn () => app(DraftDispatcher::class)->dispatch($draft->fresh()))
        ->toThrow(DomainException::class, 'recipient list no longer exists');

    Bus::assertNothingBatched();
    expect($draft->fresh()->status)->toBe(EmailDraftStatus::Approved);
})->with([
    'soft deleted' => false,
    'force deleted' => true,
]);

it('refuses an all draft when there are no recipients', function () {
    Bus::fake();
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::All]);

    expect(fn () => app(DraftDispatcher::class)->dispatch($draft))
        ->toThrow(DomainException::class, 'no recipients');

    Bus::assertNothingBatched();
});

it('does not send to a recipient added to the list after dispatch', function () {
    Bus::fake();
    $member = Recipient::factory()->create();
    $list = RecipientList::factory()->create();
    $list->recipients()->attach($member);
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::List, 'recipient_list_id' => $list->id]);

    app(DraftDispatcher::class)->dispatch($draft);
    $list->recipients()->attach(Recipient::factory()->create());

    expect(pivotIds($draft))->toBe([$member->id]);
});
