<?php

use CSeidl\EmailComposer\Http\Controllers\ReviewController;
use CSeidl\EmailComposer\Http\Controllers\ViewInBrowserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signed'])->group(function () {
    Route::get('email-composer/review/{draft}', [ReviewController::class, 'show'])
        ->name('email-composer.review.show');
    Route::post('email-composer/review/{draft}', [ReviewController::class, 'store'])
        ->name('email-composer.review.store');
    Route::get('email-composer/view/{draft}/{locale}', [ViewInBrowserController::class, 'show'])
        ->name('email-composer.view-in-browser');
});
