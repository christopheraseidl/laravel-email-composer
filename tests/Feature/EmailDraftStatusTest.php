<?php

use CSeidl\EmailComposer\Enums\EmailDraftStatus;

it('is final once sending has begun', function (EmailDraftStatus $status, bool $final) {
    expect($status->isFinal())->toBe($final);
})->with([
    [EmailDraftStatus::Draft, false],
    [EmailDraftStatus::UnderReview, false],
    [EmailDraftStatus::Approved, false],
    [EmailDraftStatus::Sending, true],
    [EmailDraftStatus::Sent, true],
    [EmailDraftStatus::Cancelled, true],
]);
