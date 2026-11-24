<?php

use CSeidl\EmailComposer\Actions\Feedback\ToggleFeedbackResolved;
use CSeidl\EmailComposer\Models\DraftFeedback;

it('resolves open feedback', function () {
    $feedback = DraftFeedback::factory()->create();

    app(ToggleFeedbackResolved::class)($feedback);

    expect($feedback->fresh()->resolved)->toBeTrue();
});

it('reopens resolved feedback', function () {
    $feedback = DraftFeedback::factory()->resolved()->create();

    app(ToggleFeedbackResolved::class)($feedback);

    expect($feedback->fresh()->resolved)->toBeFalse();
});
