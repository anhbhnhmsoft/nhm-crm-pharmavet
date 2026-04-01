<?php

use App\Http\Controllers\MarketingConversionController;
use App\Http\Controllers\WebsiteV2LeadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->middleware('throttle:120,1')->group(function () {
    Route::post('/website/{site_id}/leads', [WebsiteV2LeadController::class, 'ingest'])
        ->name('api.v2.website.leads');
    Route::post('/website/{site_id}/ping', [WebsiteV2LeadController::class, 'ping'])
        ->name('api.v2.website.ping');

    Route::post('/facebook/capi/events', [MarketingConversionController::class, 'store'])
        ->name('api.v2.facebook.capi.events');
});
