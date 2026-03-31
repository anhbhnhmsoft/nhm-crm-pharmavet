<?php

namespace App\Jobs\Marketing;

use App\Repositories\FacebookEventLogRepository;
use App\Services\Marketing\MarketingConversionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchFacebookCapiEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $eventLogId,
    ) {
    }

    public int $tries = 1;

    public function handle(
        MarketingConversionService $marketingConversionService,
        FacebookEventLogRepository $facebookEventLogRepository,
    ): void {
        $log = $facebookEventLogRepository->find($this->eventLogId);
        if (!$log) {
            return;
        }

        $marketingConversionService->dispatchEvent($log);
    }
}
