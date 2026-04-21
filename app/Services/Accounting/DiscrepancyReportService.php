<?php

namespace App\Services\Accounting;

use App\Core\Logging;
use App\Repositories\DiscrepancyReportRepository;
use App\Core\ServiceReturn;
use App\Models\Order;
use Throwable;

class DiscrepancyReportService
{
    public function __construct(
        protected DiscrepancyReportRepository $discrepancyReportRepository
    ) {
    }

    /**
     * Lấy báo cáo đối soát chênh lệch
     */
    public function getReport(int $organizationId, string $startDate, string $endDate): ServiceReturn
    {
        try {
            $data = $this->discrepancyReportRepository->getDiscrepancyData($organizationId, $startDate, $endDate);

            return ServiceReturn::success($data);
        } catch (Throwable $e) {
            Logging::error('Get discrepancy report failed', [
                'organization_id' => $organizationId,
                'start' => $startDate,
                'end' => $endDate,
                'error' => $e->getMessage()
            ]);
            return ServiceReturn::error(__('accounting.report.get_failed'));
        }
    }

    public function resolveSystemValue(Order $order): float
    {
        return $this->discrepancyReportRepository->resolveSystemValue($order);
    }

    public function resolveWarehouseValue(Order $order): float
    {
        return $this->discrepancyReportRepository->resolveWarehouseValue($order);
    }

    public function resolveActualPayment(Order $order): float
    {
        return $this->discrepancyReportRepository->resolveActualPayment($order);
    }

    public function resolveDiscrepancyNote(Order $order): string
    {
        return $this->discrepancyReportRepository->resolveDiscrepancyNote($order);
    }

    public function valuesDifferent(float $left, float $right): bool
    {
        return $this->discrepancyReportRepository->valuesDifferent($left, $right);
    }
}
