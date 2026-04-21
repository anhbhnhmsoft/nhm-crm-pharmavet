<?php

use App\Http\Controllers\ActivityController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FacebookWebhookController;
use App\Http\Controllers\FacebookAuthController;
use App\Http\Controllers\FundTransactionAttachmentController;
use App\Http\Controllers\GHNWebhookController;
use App\Http\Controllers\MarketingSpendAttachmentController;
use App\Http\Controllers\OrderExportTicketPrintController;
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

    Route::get('/orders/{order}/export-ticket/print', OrderExportTicketPrintController::class)
        ->name('orders.export-ticket.print');

    Route::get('/marketing/spend-attachments/{attachment}/download', [MarketingSpendAttachmentController::class, 'download'])
        ->name('marketing.spend-attachments.download');

    Route::get('/fund-transaction-attachments/{attachment}', [FundTransactionAttachmentController::class, 'show'])
        ->name('fund-transaction-attachments.show');

    Route::get('/fund-transaction-attachments/{attachment}/download', [FundTransactionAttachmentController::class, 'download'])
        ->name('fund-transaction-attachments.download');
});

Route::middleware(['web', 'auth:web'])->prefix('integration/facebook')->group(function () {
    Route::get('{integration}/redirect', [FacebookAuthController::class, 'redirect'])
        ->name('integration.facebook.redirect');

    Route::get('/callback', [FacebookAuthController::class, 'callback'])
        ->name('integration.facebook.callback');
});

Route::middleware(['web', 'auth:web'])->prefix('api/integrations')->group(function () {
    Route::post('/{integration}/sync-pages', [FacebookAuthController::class, 'syncPages'])
        ->name('api.integrations.sync-pages');

    Route::post('/{integration}/disconnect', [FacebookAuthController::class, 'disconnect'])
        ->name('api.integrations.disconnect');
});

Route::get('/webhook/facebook', [FacebookWebhookController::class, 'handle'])
    ->middleware('throttle:facebook-webhook')
    ->name('webhook.facebook.verify');

Route::post('/webhook/facebook', [FacebookWebhookController::class, 'handle'])
    ->middleware(['throttle:facebook-webhook', 'facebook.webhook.signature'])
    ->name('webhook.facebook.receive');

Route::get('/webhooks/facebook', [FacebookWebhookController::class, 'handle'])
    ->middleware('throttle:facebook-webhook')
    ->name('webhooks.facebook.verify');

Route::post('/webhooks/facebook', [FacebookWebhookController::class, 'handle'])
    ->middleware(['throttle:facebook-webhook', 'facebook.webhook.signature'])
    ->name('webhooks.facebook.receive');

Route::post('/webhooks/ghn', [GHNWebhookController::class, 'handle'])
    ->name('webhooks.ghn');

// Route::get('/')->name('login');

Route::get('/register', function () {
    return view('auth.partner-registration');
})->name('partner.register');
