<?php

use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Facades\EmailComposer;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\ListEmailDrafts;
use CSeidl\EmailComposer\Jobs\SendEmailJob;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Services\DraftDispatcher;
use Filament\Actions\Testing\TestAction;
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

it('batches one job per recipient on the configured queue', function () {
    Bus::fake();
    $draft = approvedDraftWithRecipients(3);

    app(DraftDispatcher::class)->dispatch($draft);

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 3
        && $batch->jobs->every(fn ($job) => $job instanceof SendEmailJob && $job->draft->is($draft))
        && $batch->jobs->pluck('recipient.id')->sort()->values()->all() === $draft->recipients->pluck('id')->sort()->values()->all()
        && $batch->connection() === 'sync'
        && $batch->queue() === 'emails');
});

it('uses the default connection when none is configured', function () {
    Bus::fake();
    config()->set('email-composer.queue.connection', null);

    app(DraftDispatcher::class)->dispatch(approvedDraftWithRecipients(1));

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->connection() === null);
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
    'already sent' => EmailDraftStatus::Sent,
]);

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

it('sends from the Filament send action', function () {
    Bus::fake();
    $draft = approvedDraftWithRecipients(2);

    livewire(ListEmailDrafts::class)->callAction(TestAction::make('send')->table($draft));

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 2);
});
