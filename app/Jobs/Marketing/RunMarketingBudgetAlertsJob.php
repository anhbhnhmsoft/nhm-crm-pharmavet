<?php

namespace App\Jobs\Marketing;

use App\Common\Constants\User\UserRole;
use App\Repositories\UserRepository;
use App\Services\Marketing\MarketingAlertService;
use Filament\Notifications\Notification;
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
        $seedUsers = $userRepository->query()
            ->whereNotNull('organization_id')
            ->select('id', 'organization_id')
            ->groupBy('organization_id', 'id')
            ->get()
            ->unique('organization_id')
            ->values();

        foreach ($seedUsers as $user) {
            $count = $marketingAlertService->evaluateAndLog($user);
            if ($count <= 0) {
                continue;
            }

            $recipients = $userRepository->query()
                ->where('organization_id', $user->organization_id)
                ->whereIn('role', [
                    UserRole::SUPER_ADMIN->value,
                    UserRole::ADMIN->value,
                    UserRole::MARKETING->value,
                ])
                ->get();

            if ($recipients->isEmpty()) {
                continue;
            }

            Notification::make()
                ->title(__('filament.integration.notifications.marketing_alert_title'))
                ->body(__('filament.integration.notifications.marketing_alert_body', ['count' => $count]))
                ->danger()
                ->sendToDatabase($recipients);
        }
    }
}
