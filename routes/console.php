<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\Marketing\RetryFacebookEventJob;
use App\Jobs\Marketing\RunMarketingBudgetAlertsJob;
use App\Jobs\Warehouse\SyncShippingStatusesJob;


// Đồng bộ tỉ giá hối đoái mỗi ngày lúc 7:00 sáng cho tất cả tổ chức
Schedule::command('app:sync-exchange-rate')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();

// Cảnh báo nợ quá hạn mỗi ngày lúc 8:00 sáng
Schedule::command('app:notify-debt')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground();

// Đồng bộ trạng thái giao vận GHN mỗi 5 phút
Schedule::call(function () {
    if (config('warehouse.features.shipping_sync_v1', true)) {
        SyncShippingStatusesJob::dispatch()->onQueue('shipping_sync');
    }
})
    ->name('sync_shipping_statuses')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::call(function () {
    if (config('marketing.features.integration_v2', true)) {
        RetryFacebookEventJob::dispatch()->onQueue('marketing_capi');
    }
})
    ->name('retry_facebook_capi_events')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::call(function () {
    if (config('marketing.features.budget_kpi_v1', true)) {
        RunMarketingBudgetAlertsJob::dispatch()->onQueue('marketing_alerts');
    }
})
    ->name('marketing_budget_alerts')
    ->hourly()
    ->withoutOverlapping();
