<?php

namespace App\Jobs\Marketing;

use App\Repositories\FacebookEventLogRepository;
use App\Services\Marketing\MarketingConversionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryFacebookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(
        FacebookEventLogRepository $facebookEventLogRepository,
        MarketingConversionService $marketingConversionService,
    ): void {
        $logs = $facebookEventLogRepository->query()
            ->whereIn('status', ['retrying', 'failed'])
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->limit(100)
            ->get();

        foreach ($logs as $log) {
            $marketingConversionService->dispatchEvent($log);
        }
    }
}
