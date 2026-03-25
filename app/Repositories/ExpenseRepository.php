<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Model;

class ExpenseRepository extends BaseRepository
{
    public function model(): Model
    {
        return new Expense();
    }

    /**
     * Tính tổng chi phí trong khoảng thời gian
     */
    public function sumTotalByDateRange(int $organizationId, string $startDate, string $endDate): float
    {
        return (float) $this->query()
            ->where('organization_id', $organizationId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');
    }
}
