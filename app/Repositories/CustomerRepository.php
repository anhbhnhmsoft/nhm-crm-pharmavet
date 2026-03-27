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
}
