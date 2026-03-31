<?php

namespace App\Jobs\Marketing;

use App\Repositories\UserRepository;
use App\Services\Marketing\MarketingAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunMarketingBudgetAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(UserRepository $userRepository, MarketingAlertService $marketingAlertService): void
    {
        $users = $userRepository->query()
            ->whereNotNull('organization_id')
            ->select('id', 'organization_id')
            ->groupBy('organization_id', 'id')
            ->get()
            ->unique('organization_id')
            ->values();

        foreach ($users as $user) {
            $marketingAlertService->evaluateAndLog($user);
        }
    }
}
