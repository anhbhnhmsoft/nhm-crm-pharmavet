<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
