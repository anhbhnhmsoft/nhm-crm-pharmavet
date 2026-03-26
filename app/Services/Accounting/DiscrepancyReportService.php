<?php

namespace App\Services\Accounting;

use App\Core\Logging;
use App\Repositories\DiscrepancyReportRepository;
use App\Core\ServiceReturn;
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
}
