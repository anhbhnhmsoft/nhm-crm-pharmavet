<?php

use App\Common\Constants\User\UserRole;
use App\Http\Controllers\Api\V1\Admin\FacebookConnectionApprovalController;
use App\Http\Controllers\Api\V1\FacebookConnectionController;
use App\Http\Controllers\MarketingConversionController;
use App\Http\Controllers\WebsiteV2LeadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth:api'])
    ->group(function () {
        Route::post('/facebook/connect', [FacebookConnectionController::class, 'connect'])
            ->middleware('api.role:' . UserRole::MARKETING->value . ',' . UserRole::ADMIN->value . ',' . UserRole::SUPER_ADMIN->value);

        Route::get('/facebook/my-pages', [FacebookConnectionController::class, 'myPages'])
            ->middleware('api.role:' . UserRole::MARKETING->value . ',' . UserRole::ADMIN->value . ',' . UserRole::SUPER_ADMIN->value);

        Route::post('/admin/facebook/approve', [FacebookConnectionApprovalController::class, 'approve'])
            ->middleware('api.role:' . UserRole::ADMIN->value . ',' . UserRole::SUPER_ADMIN->value);

        Route::post('/admin/facebook/reject', [FacebookConnectionApprovalController::class, 'reject'])
            ->middleware('api.role:' . UserRole::ADMIN->value . ',' . UserRole::SUPER_ADMIN->value);
    });

Route::prefix('v2')->middleware('throttle:120,1')->group(function () {
    Route::post('/website/{site_id}/leads', [WebsiteV2LeadController::class, 'ingest'])
        ->name('api.v2.website.leads');
    Route::post('/website/{site_id}/ping', [WebsiteV2LeadController::class, 'ping'])
        ->name('api.v2.website.ping');

    Route::post('/facebook/capi/events', [MarketingConversionController::class, 'store'])
        ->name('api.v2.facebook.capi.events');
});
