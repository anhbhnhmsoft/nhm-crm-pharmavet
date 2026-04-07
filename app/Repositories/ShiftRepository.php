<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Shift;

class ShiftRepository extends BaseRepository
{
    public function model(): Shift
    {
        return new Shift();
    }

    /**
     * Kiểm tra xem có ca làm việc nào bị trùng khoảng thời gian không
     */
    public function isOverlap(int $organizationId, string $startTime, string $endTime, ?int $excludeId = null): bool
    {
        return $this->query()
            ->where('organization_id', $organizationId)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })
            ->exists();
    }
}
