<?php

use App\Http\Controllers\ActivityController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FacebookWebhookController;
use App\Http\Controllers\FacebookAuthController;

Route::middleware(['auth:web'])->group(function () {
    Route::post('/heartbeat', [ActivityController::class, 'heartbeat']);
    Route::post('/activity-logout', [ActivityController::class, 'activityLogout']);
    Route::post('/user-leaving', [ActivityController::class, 'userLeaving']);

    Route::get('/users/{user}/login-as', [ActivityController::class, 'loginAs'])
        ->name('users.impersonate');
    Route::get('/impersonate/leave', [ActivityController::class, 'leave'])
        ->name('impersonate.leave');
});

Route::middleware(['web'
// , 'auth'
])->prefix('integration/facebook')->group(function () {
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

// Route::get('/')->name('login');
