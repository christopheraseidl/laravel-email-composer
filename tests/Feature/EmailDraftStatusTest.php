<?php

use CSeidl\EmailComposer\Enums\EmailDraftStatus;

it('has started sending when sending is running, finished or cancelled', function (EmailDraftStatus $status, bool $hasStartedSending) {
    expect($status->hasStartedSending())->toBe($hasStartedSending);
})->with([
    [EmailDraftStatus::Draft, false],
    [EmailDraftStatus::UnderReview, false],
    [EmailDraftStatus::Approved, false],
    [EmailDraftStatus::Sending, true],
    [EmailDraftStatus::Sent, true],
    [EmailDraftStatus::Cancelled, true],
]);
