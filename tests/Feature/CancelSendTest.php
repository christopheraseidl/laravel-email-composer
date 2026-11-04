<?php

use CSeidl\EmailComposer\Actions\Drafts\CancelSend;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Facades\Bus;

function storedBatchId(): string
{
    return app(BatchRepository::class)->store(Bus::batch([]))->id;
}

it('cancels the batch of a sending draft', function () {
    $draft = EmailDraft::factory()->sending()->create(['batch_id' => storedBatchId()]);

    app(CancelSend::class)($draft);

    expect(Bus::findBatch($draft->batch_id)->cancelled())->toBeTrue();
});

it('refuses a draft that is not sending', function (string $state) {
    $draft = $state === 'draft'
        ? EmailDraft::factory()->create(['batch_id' => storedBatchId()])
        : EmailDraft::factory()->{$state}()->create(['batch_id' => storedBatchId()]);

    expect(fn () => app(CancelSend::class)($draft))->toThrow(DomainException::class, 'Cannot cancel');

    expect(Bus::findBatch($draft->batch_id)->cancelled())->toBeFalse();
})->with(['draft', 'underReview', 'approved', 'sent', 'cancelled']);

it('refuses a sending draft whose batch is gone', function () {
    $draft = EmailDraft::factory()->sending()->create(['batch_id' => 'missing']);

    expect(fn () => app(CancelSend::class)($draft))->toThrow(DomainException::class, 'no longer exists');
});
