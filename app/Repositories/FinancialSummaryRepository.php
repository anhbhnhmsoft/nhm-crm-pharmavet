<?php

namespace App\Repositories;

use App\Models\FinancialSummary;
use App\Core\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class FinancialSummaryRepository extends BaseRepository
{
    protected function model(): Model
    {
        return new FinancialSummary();
    }

    /**
     * Tìm hoặc tạo mới bản ghi báo cáo theo ngày và tổ chức
     */
    public function updateOrCreateSummary(int $organizationId, string $date, array $data): Model
    {
        return $this->model()::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'date' => $date,
            ],
            $data
        );
    }

    /**
     * Lấy dữ liệu báo cáo trong khoảng thời gian
     */
    public function getSummaryByDateRange(int $organizationId, string $startDate, string $endDate): Collection
    {
        return $this->query()
            ->where('organization_id', $organizationId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();
    }
}
