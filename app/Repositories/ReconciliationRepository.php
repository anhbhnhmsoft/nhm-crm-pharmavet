<?php

namespace App\Repositories;

use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Core\BaseRepository;
use App\Models\Reconciliation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
    public function getReconciliationsByDateRange(int $organizationId, string $startDate, string $endDate): EloquentCollection
    {
        return $this->query()
            ->where('organization_id', $organizationId)
            ->whereBetween('reconciliation_date', [$startDate, $endDate])
            ->orderBy('reconciliation_date', 'asc')
            ->get();
    }

    /**
     * Tính tổng phí đối soát (thực thu) trong khoảng thời gian.
     */
    public function sumTotalFeeByDateRange(int $organizationId, string $fromDate, string $toDate): float
    {
        return (float) $this->query()
            ->where('organization_id', $organizationId)
            ->whereBetween('reconciliation_date', [$fromDate, $toDate])
            ->sum('total_fee');
    }

    public function getUnlinkedGhnItemNamesByOrganization(int $organizationId): Collection
    {
        return $this->query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('ghn_items')
            ->whereDoesntHave('order.items')
            ->get(['ghn_items'])
            ->flatMap(fn (Reconciliation $reconciliation) => collect($reconciliation->ghn_items)->pluck('name'))
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values();
    }

    public function sumDisplayedAmount(Builder $query): float
    {
        return (float) (clone $query)
            ->sum(DB::raw("
                COALESCE(
                    (
                        SELECT orders.total_amount
                        FROM orders
                        WHERE orders.id = reconciliations.order_id
                            AND orders.deleted_at IS NULL
                        LIMIT 1
                    ),
                    reconciliations.cod_amount
                )
            "));
    }
}
