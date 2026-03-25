<?php

namespace App\Repositories;

use App\Common\Constants\Organization\FundTransactionStatus;
use App\Core\BaseRepository;
use App\Models\FundTransaction;
use Illuminate\Database\Eloquent\Model;

class FundTransactionRepository extends BaseRepository
{
    public function model(): Model
    {
        return new FundTransaction();
    }

    /**
     * Tính tổng giao dịch theo loại và thời gian cho các quỹ của một tổ chức
     */
    public function sumByTypeDateRange(int $organizationId, string $startDate, string $endDate): \Illuminate\Support\Collection
    {
        return $this->query()
            ->whereHas('fund', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->where('status', FundTransactionStatus::COMPLETED->value)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');
    }
}
