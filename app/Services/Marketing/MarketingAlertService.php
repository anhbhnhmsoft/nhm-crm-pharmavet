<?php

namespace App\Services\Marketing;

use App\Models\User;
use App\Repositories\MarketingAlertLogRepository;

class MarketingAlertService
{
    public function __construct(
        protected MarketingBudgetService $marketingBudgetService,
        protected MarketingAlertLogRepository $marketingAlertLogRepository,
    ) {
    }

    public function evaluateAndLog(User $viewer): int
    {
        $report = $this->marketingBudgetService->summarize([
            'from_date' => now()->subDays(7)->toDateString(),
            'to_date' => now()->toDateString(),
        ], $viewer);

        $created = 0;
        foreach ($report['rows'] as $row) {
            $alert = $this->resolveAlert($row);
            if (!$alert) {
                continue;
            }

            $exists = $this->marketingAlertLogRepository->query()
                ->where('organization_id', $viewer->organization_id)
                ->where('alert_type', $alert['alert_type'])
                ->where('channel', $row['channel'])
                ->where('campaign', $row['campaign'])
                ->whereNull('resolved_at')
                ->whereDate('triggered_at', now()->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            $this->marketingAlertLogRepository->create([
                'organization_id' => $viewer->organization_id,
                'alert_type' => $alert['alert_type'],
                'severity' => $alert['severity'],
                'channel' => $row['channel'],
                'campaign' => $row['campaign'],
                'payload_json' => $row,
                'triggered_at' => now(),
            ]);
            $created++;
        }

        return $created;
    }

    private function resolveAlert(array $row): ?array
    {
        if ($row['status'] === 'over_budget') {
            return ['alert_type' => 'over_budget', 'severity' => 'high'];
        }

        if ($row['status'] === 'roi_low') {
            return ['alert_type' => 'low_roi', 'severity' => 'warning'];
        }

        if ((float) $row['actual_spend'] > 0 && (float) $row['valid_leads'] === 0.0) {
            return ['alert_type' => 'spend_without_lead', 'severity' => 'high'];
        }

        return null;
    }
}
