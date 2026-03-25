<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Revenue;
use Illuminate\Database\Eloquent\Model;

class RevenueRepository extends BaseRepository
{
    public function model(): Model
    {
        return new Revenue();
    }

    /**
     * Tính tổng doanh thu khác trong khoảng thời gian
     */
    public function sumTotalByDateRange(int $organizationId, string $startDate, string $endDate): float
    {
        return (float) $this->query()
            ->where('organization_id', $organizationId)
            ->whereBetween('revenue_date', [$startDate, $endDate])
            ->sum('amount');
    }
}
