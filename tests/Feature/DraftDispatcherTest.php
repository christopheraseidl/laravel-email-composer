<?php

use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Facades\EmailComposer;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\ListEmailDrafts;
use CSeidl\EmailComposer\Jobs\QueueDraftRecipients;
use CSeidl\EmailComposer\Jobs\SendEmailJob;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
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
