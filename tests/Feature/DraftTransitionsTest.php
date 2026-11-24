<?php

use CSeidl\EmailComposer\Actions\Drafts\ApproveDraft;
use CSeidl\EmailComposer\Actions\Drafts\MarkDraftSent;
use CSeidl\EmailComposer\Actions\Drafts\ReturnDraftToDraft;
use CSeidl\EmailComposer\Actions\Drafts\SubmitDraft;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Factories\UserFactory;

beforeEach(fn () => Storage::fake(config('email-composer.storage.disk')));

it('submits a draft for review', function () {
    $draft = draftWithTemplate();

    app(SubmitDraft::class)($draft);

    expect($draft->fresh())
        ->status->toBe(EmailDraftStatus::UnderReview)
        ->submitted_at->not->toBeNull();
});

it('approves a draft under review', function () {
    $approver = UserFactory::new()->create();
    $draft = EmailDraft::factory()->underReview()->create();

    app(ApproveDraft::class)($draft, $approver);

    expect($draft->fresh())
        ->status->toBe(EmailDraftStatus::Approved)
        ->approved_at->not->toBeNull()
        ->approved_by->toBe($approver->getKey());
});

it('publishes the rendered locales when approved', function () {
    $draft = EmailDraft::factory()->underReview()->create();

    app(ApproveDraft::class)($draft, UserFactory::new()->create());

    $files = $draft->fresh()->public_files;
    expect($files)->toHaveKeys(['en', 'es']);
    foreach ($files as $path) {
        Storage::disk(config('email-composer.storage.disk'))->assertExists($path);
    }
});

it('returns a draft under review to draft', function () {
    $draft = EmailDraft::factory()->underReview()->create();

    app(ReturnDraftToDraft::class)($draft);

    expect($draft->fresh())
        ->status->toBe(EmailDraftStatus::Draft)
        ->submitted_at->toBeNull();
});

it('returns an approved draft to draft and clears the approval', function () {
    $draft = EmailDraft::factory()->approved()->create();

    app(ReturnDraftToDraft::class)($draft);

    expect($draft->fresh())
        ->status->toBe(EmailDraftStatus::Draft)
        ->submitted_at->toBeNull()
        ->approved_at->toBeNull()
        ->approved_by->toBeNull();
});

it('unpublishes an approved draft returned to draft', function () {
    $draft = EmailDraft::factory()->underReview()->create();
    app(ApproveDraft::class)($draft, UserFactory::new()->create());
    $paths = $draft->fresh()->public_files;

    app(ReturnDraftToDraft::class)($draft->fresh());

    foreach ($paths as $path) {
        Storage::disk(config('email-composer.storage.disk'))->assertMissing($path);
    }
    expect($draft->fresh()->public_files)->toBeNull();
});

it('rejects an invalid transition', function (string $state, string $action) {
    $draft = $state === 'draft'
        ? EmailDraft::factory()->create()
        : EmailDraft::factory()->{$state}()->create();

    $call = $action === ApproveDraft::class
        ? fn () => app($action)($draft, UserFactory::new()->create())
        : fn () => app($action)($draft);

    expect($call)->toThrow(DomainException::class);
})->with([
    'approve while in draft' => ['draft', ApproveDraft::class],
    'mark sent while in draft' => ['draft', MarkDraftSent::class],
    'return a draft to draft' => ['draft', ReturnDraftToDraft::class],
    'submit while under review' => ['underReview', SubmitDraft::class],
    'mark sent while under review' => ['underReview', MarkDraftSent::class],
    'submit while approved' => ['approved', SubmitDraft::class],
    'return a sending draft to draft' => ['sending', ReturnDraftToDraft::class],
    'return a sent draft to draft' => ['sent', ReturnDraftToDraft::class],
    'mark an approved draft sent' => ['approved', MarkDraftSent::class],
    'mark an already sent draft sent' => ['sent', MarkDraftSent::class],
    'submit a sent draft' => ['sent', SubmitDraft::class],
]);
