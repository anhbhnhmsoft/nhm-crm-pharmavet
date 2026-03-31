<?php

namespace Tests\Unit\Marketing;

use App\Models\MarketingScoringRuleSet;
use App\Repositories\MarketingScoringRuleSetRepository;
use App\Services\Marketing\MarketingRankingService;
use PHPUnit\Framework\TestCase;

class MarketingRankingServiceTest extends TestCase
{
    public function test_it_scores_and_sorts_by_score_then_revenue_then_conversion(): void
    {
        $repo = $this->createMock(MarketingScoringRuleSetRepository::class);
        $repo->method('find')->willReturn(new MarketingScoringRuleSet([
            'rules_json' => [
                'order_weight' => 10,
                'contact_weight' => 0,
                'revenue_weight' => 0,
                'conversion_bonus_threshold' => 30,
                'conversion_bonus' => 5,
            ],
        ]));

        $service = new MarketingRankingService($repo);

        $rows = [
            ['name' => 'B', 'orders' => 3, 'contacts' => 10, 'adjusted_revenue' => 100, 'conversion_rate' => 35],
            ['name' => 'A', 'orders' => 3, 'contacts' => 10, 'adjusted_revenue' => 200, 'conversion_rate' => 10],
            ['name' => 'C', 'orders' => 2, 'contacts' => 10, 'adjusted_revenue' => 500, 'conversion_rate' => 20],
        ];

        $ranked = $service->scoreRows($rows, 1);

        $this->assertSame('B', $ranked[0]['name']);
        $this->assertSame(1, $ranked[0]['rank']);
        $this->assertSame('A', $ranked[1]['name']);
        $this->assertSame('C', $ranked[2]['name']);
    }
}
