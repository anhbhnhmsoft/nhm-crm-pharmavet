<?php

namespace App\Services\Marketing;

use App\Models\MarketingScoringRuleSet;
use App\Repositories\MarketingScoringRuleSetRepository;

class MarketingRankingService
{
    public function __construct(
        protected MarketingScoringRuleSetRepository $marketingScoringRuleSetRepository,
    ) {
    }

    public function scoreRows(array $rows, ?int $ruleSetId, ?int $organizationId = null): array
    {
        $ruleSet = $this->resolveRuleSet($ruleSetId, $organizationId);
        $rules = (array) ($ruleSet?->rules_json ?? []);

        $orderWeight = (float) ($rules['order_weight'] ?? 10);
        $contactWeight = (float) ($rules['contact_weight'] ?? 2);
        $revenueWeight = (float) ($rules['revenue_weight'] ?? 0.001);
        $conversionBonusThreshold = (float) ($rules['conversion_bonus_threshold'] ?? 30);
        $conversionBonus = (float) ($rules['conversion_bonus'] ?? 20);

        foreach ($rows as &$row) {
            $orders = (int) ($row['orders'] ?? 0);
            $contacts = (int) ($row['contacts'] ?? 0);
            $revenue = (float) ($row['adjusted_revenue'] ?? 0);
            $conversion = (float) ($row['conversion_rate'] ?? 0);

            $score = ($orders * $orderWeight) + ($contacts * $contactWeight) + ($revenue * $revenueWeight);
            if ($conversion >= $conversionBonusThreshold) {
                $score += $conversionBonus;
            }

            $row['score'] = round($score, 2);
        }
        unset($row);

        usort($rows, function (array $a, array $b): int {
            if (($a['score'] ?? 0) !== ($b['score'] ?? 0)) {
                return ($a['score'] ?? 0) < ($b['score'] ?? 0) ? 1 : -1;
            }

            if (($a['adjusted_revenue'] ?? 0) !== ($b['adjusted_revenue'] ?? 0)) {
                return ($a['adjusted_revenue'] ?? 0) < ($b['adjusted_revenue'] ?? 0) ? 1 : -1;
            }

            if (($a['conversion_rate'] ?? 0) !== ($b['conversion_rate'] ?? 0)) {
                return ($a['conversion_rate'] ?? 0) < ($b['conversion_rate'] ?? 0) ? 1 : -1;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        foreach ($rows as $index => &$row) {
            $row['rank'] = $index + 1;
        }
        unset($row);

        return $rows;
    }

    protected function resolveRuleSet(?int $ruleSetId, ?int $organizationId = null): ?MarketingScoringRuleSet
    {
        if (!empty($ruleSetId)) {
            if (!$organizationId) {
                return $this->marketingScoringRuleSetRepository->find((int) $ruleSetId) ?: null;
            }

            return $this->marketingScoringRuleSetRepository->query()
                ->when($organizationId, fn($query) => $query->where('organization_id', $organizationId))
                ->whereKey((int) $ruleSetId)
                ->first();
        }

        return $this->marketingScoringRuleSetRepository->query()
            ->when($organizationId, fn($query) => $query->where('organization_id', $organizationId))
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
}
