<?php

namespace App\Services\Warehouse;

use App\Common\Constants\Warehouse\InventoryMovementType;
use App\Repositories\InventoryMovementRepository;
use App\Repositories\OrderRepository;

class WarehouseReportService
{
    public function __construct(
        protected InventoryMovementRepository $inventoryMovementRepository,
        protected OrderRepository $orderRepository,
    ) {
    }

    public function buildStockSummary(int $organizationId, string $fromDate, string $toDate): array
    {
        $base = $this->inventoryMovementRepository->query()
            ->where('organization_id', $organizationId)
            ->whereBetween('occurred_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);

        $imports = (clone $base)->whereIn('movement_type', InventoryMovementType::importValues())->sum('quantity_change');
        $exports = abs((int) (clone $base)->whereIn('movement_type', InventoryMovementType::exportValues())->sum('quantity_change'));

        return [
            'opening' => 0,
            'imports' => (int) $imports,
            'exports' => (int) $exports,
            'closing' => (int) $imports - (int) $exports,
        ];
    }

    public function buildCoverageSummary(int $organizationId, string $fromDate, string $toDate, int $days = 30): array
    {
        $summary = $this->buildStockSummary($organizationId, $fromDate, $toDate);
        $avgDaily = $days > 0 ? round(($summary['exports'] / $days), 2) : 0;
        $daysOfStock = $avgDaily > 0 ? round(($summary['closing'] / $avgDaily), 2) : 0;

        return [
            'available_stock' => $summary['closing'],
            'avg_daily_out' => $avgDaily,
            'days_of_stock' => $daysOfStock,
        ];
    }
}
