<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;

class CustomerRepository extends BaseRepository
{
    public function model(): Model
    {
        return new Customer();
    }

    /**
     * Lấy danh sách khách hàng
     */
    public function getForSelect(int $organizationId, ?string $search = null, int $limit = 50): array
    {
        return $this->query()
            ->where('organization_id', $organizationId)
            ->when($search, fn($q) => $q->where('username', 'like', "%{$search}%"))
            ->limit($limit)
            ->pluck('username', 'id')
            ->toArray();
    }
    /**
     * Lấy danh sách khách hàng mới trong khoảng thời gian theo organization
     */
    public function findByOrganizationAndDateRange(int $organizationId, string $startDate, string $endDate, array $with = []): \Illuminate\Database\Eloquent\Collection
    {
        return $this->query()
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when(!empty($with), fn($q) => $q->with($with))
            ->get();
    }
}
