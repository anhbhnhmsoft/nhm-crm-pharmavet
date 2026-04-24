<?php

namespace Tests\Unit\Telesale;

use App\Filament\Clusters\Telesale\Pages\CallMetricsReportPage;
use Tests\TestCase;

class CallMetricsReportPageTest extends TestCase
{
    public function test_resolve_date_boundary_normalizes_datetime_input_without_duplicate_time_suffix(): void
    {
        $page = new class extends CallMetricsReportPage {
            public function exposeResolveDateBoundary(string $value, bool $isStart): string
            {
                return $this->resolveDateBoundary($value, $isStart);
            }
        };

        $this->assertSame(
            '2026-04-01 00:00:00',
            $page->exposeResolveDateBoundary('2026-04-01 17:39:56', true),
        );

        $this->assertSame(
            '2026-04-24 23:59:59',
            $page->exposeResolveDateBoundary('2026-04-24 17:39:56', false),
        );
    }
}
