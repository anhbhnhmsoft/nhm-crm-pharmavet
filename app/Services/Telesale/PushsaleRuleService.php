<?php

namespace App\Services\Telesale;

use App\Models\PushsaleRuleSet;
use App\Repositories\PushsaleRuleSetRepository;

class PushsaleRuleService
{
    public function __construct(
        private PushsaleRuleSetRepository $pushsaleRuleSetRepository,
    ) {
    }

    public function applyRuleSet(float $revenue, ?int $ruleSetId): array
    {
        if (empty($ruleSetId)) {
            return [
                'adjusted_revenue' => $revenue,
                'kpi_multiplier' => 1,
            ];
        }

        $ruleSet = $this->pushsaleRuleSetRepository->find($ruleSetId);
        if (!$ruleSet) {
            return [
                'adjusted_revenue' => $revenue,
                'kpi_multiplier' => 1,
            ];
        }

        $multiplier = (float) data_get($ruleSet->rules_json, 'kpi_multiplier', 1);

        return [
            'adjusted_revenue' => round($revenue * max(0, $multiplier), 2),
            'kpi_multiplier' => max(0, $multiplier),
        ];
    }
}
