<?php

use App\Http\Controllers\ActivityController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FacebookWebhookController;
use App\Http\Controllers\FacebookAuthController;
use App\Http\Controllers\GHNWebhookController;
use App\Http\Controllers\OrderShippingController;

Route::middleware(['auth:web'])->group(function () {
    Route::post('/heartbeat', [ActivityController::class, 'heartbeat']);
    Route::post('/activity-logout', [ActivityController::class, 'activityLogout']);
    Route::post('/user-leaving', [ActivityController::class, 'userLeaving']);

    Route::get('/users/{user}/login-as', [ActivityController::class, 'loginAs'])
        ->name('users.impersonate');
    Route::get('/impersonate/leave', [ActivityController::class, 'leave'])
        ->name('impersonate.leave');

    Route::post('/orders/{order}/redelivery', [OrderShippingController::class, 'requestRedelivery'])
        ->name('orders.redelivery');
});

Route::middleware(['web'])->prefix('integration/facebook')->group(function () {
    Route::get('{integration}/redirect', [FacebookAuthController::class, 'redirect'])
        ->name('integration.facebook.redirect');

    Route::get('/callback', [FacebookAuthController::class, 'callback'])
        ->name('integration.facebook.callback');
});

Route::middleware(['web', 'auth'])->prefix('api/integrations')->group(function () {
    Route::post('/{integration}/sync-pages', [FacebookAuthController::class, 'syncPages'])
        ->name('api.integrations.sync-pages');

    Route::post('/{integration}/disconnect', [FacebookAuthController::class, 'disconnect'])
        ->name('api.integrations.disconnect');
});

Route::match(['get', 'post'], '/webhooks/facebook', [FacebookWebhookController::class, 'handle'])
    ->name('webhooks.facebook');

Route::post('/webhooks/ghn', [GHNWebhookController::class, 'handle'])
    ->name('webhooks.ghn');

// Route::get('/')->name('login');

Route::get('/register', function () {
    return view('auth.partner-registration');
})->name('partner.register');

