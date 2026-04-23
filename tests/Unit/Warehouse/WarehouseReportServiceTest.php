<?php

namespace Tests\Unit\Warehouse;

use App\Repositories\InventoryMovementRepository;
use App\Repositories\OrderRepository;
use App\Services\Warehouse\WarehouseReportService;
use ReflectionMethod;
use Tests\TestCase;

class WarehouseReportServiceTest extends TestCase
{
    public function test_normalize_boundary_accepts_date_strings(): void
    {
        $service = new WarehouseReportService(new InventoryMovementRepository(), new OrderRepository());

        $start = $this->invokeNormalizeBoundary($service, '2026-04-01', true);
        $end = $this->invokeNormalizeBoundary($service, '2026-04-22', false);

        $this->assertSame('2026-04-01 00:00:00', $start);
        $this->assertSame('2026-04-22 23:59:59', $end);
    }

    public function test_normalize_boundary_accepts_datetime_strings(): void
    {
        $service = new WarehouseReportService(new InventoryMovementRepository(), new OrderRepository());

        $start = $this->invokeNormalizeBoundary($service, '2026-04-01 16:54:39', true);
        $end = $this->invokeNormalizeBoundary($service, '2026-04-22 16:54:39', false);

        $this->assertSame('2026-04-01 00:00:00', $start);
        $this->assertSame('2026-04-22 23:59:59', $end);
    }

    private function invokeNormalizeBoundary(WarehouseReportService $service, string $value, bool $isStart): string
    {
        $method = new ReflectionMethod($service, 'normalizeBoundary');
        $method->setAccessible(true);

        return $method->invoke($service, $value, $isStart);
    }
}
