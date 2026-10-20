<?php

use CSeidl\EmailComposer\Http\Controllers\ReviewController;
use CSeidl\EmailComposer\Http\Controllers\UnsubscribeController;
use CSeidl\EmailComposer\Http\Controllers\ViewInBrowserController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signed'])->group(function () {
    Route::get('email-composer/review/{draft}', [ReviewController::class, 'show'])
        ->name('email-composer.review.show');
    Route::post('email-composer/review/{draft}', [ReviewController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('email-composer.review.store');
    Route::get('email-composer/view/{draft}/{locale}', [ViewInBrowserController::class, 'show'])
        ->name('email-composer.view-in-browser');
    Route::get('email-composer/unsubscribe/{recipient}', [UnsubscribeController::class, 'show'])
        ->name('email-composer.unsubscribe.show');
    Route::post('email-composer/unsubscribe/{recipient}', [UnsubscribeController::class, 'store'])
        ->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class])
        ->name('email-composer.unsubscribe.store');
});
