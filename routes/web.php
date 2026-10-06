<?php

use CSeidl\EmailComposer\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signed'])->group(function () {
    Route::get('email-composer/review/{draft}', [ReviewController::class, 'show'])
        ->name('email-composer.review.show');
    Route::post('email-composer/review/{draft}', [ReviewController::class, 'store'])
        ->name('email-composer.review.store');
});
