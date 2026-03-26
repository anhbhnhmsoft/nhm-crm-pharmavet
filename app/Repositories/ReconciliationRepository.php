<?php

namespace App\Repositories;

use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Core\BaseRepository;
use App\Models\Reconciliation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ReconciliationRepository extends BaseRepository
{
    public function model(): Model
    {
        return new Reconciliation();
    }

    /**
     * Lấy tổng tiền đối soát đã thanh toán trước một thời điểm
     */
    public function getPaidCodBefore(int $organizationId, string $date): float
    {
        return (float) $this->query()
            ->where('organization_id', $organizationId)
            ->where('status', ReconciliationStatus::PAID->value)
            ->where('reconciliation_date', '<', $date)
            ->sum('cod_amount') ?? 0;
    }

    /**
     * Lấy danh sách đối soát trong khoảng thời gian
     */
    public function getReconciliationsByDateRange(int $organizationId, string $startDate, string $endDate): Collection
    {
        return $this->query()
            ->where('organization_id', $organizationId)
            ->whereBetween('reconciliation_date', [$startDate, $endDate])
            ->orderBy('reconciliation_date', 'asc')
            ->get();
    }
}

