<?php

namespace Tests\Unit\Telesale;

use App\Services\Telesale\PushsaleRuleService;
use App\Services\Telesale\TelesaleReportDataService;
use App\Services\Telesale\TelesaleReportScopeService;
use Tests\TestCase;

class TelesaleReportDataServiceTest extends TestCase
{
    public function test_normalize_date_range_filters_handles_datetime_inputs_without_duplicate_time_suffix(): void
    {
        $service = new class(
            $this->createMock(TelesaleReportScopeService::class),
            $this->createMock(PushsaleRuleService::class),
        ) extends TelesaleReportDataService {
            public function exposeNormalizeDateRangeFilters(array $filters): array
            {
                return $this->normalizeDateRangeFilters($filters);
            }
        };

        $filters = $service->exposeNormalizeDateRangeFilters([
            'from_date' => '2026-04-01 15:57:15',
            'to_date' => '2026-04-24 15:57:15',
        ]);

        $this->assertSame('2026-04-01', $filters['from_date']);
        $this->assertSame('2026-04-24', $filters['to_date']);
        $this->assertSame('2026-04-01 00:00:00', $filters['from_at']);
        $this->assertSame('2026-04-24 23:59:59', $filters['to_at']);
    }
}
