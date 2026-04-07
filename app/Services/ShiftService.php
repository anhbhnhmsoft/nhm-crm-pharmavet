<?php

namespace App\Services;

use App\Repositories\ShiftRepository;

class ShiftService
{
    private ShiftRepository $shiftRepository;

    public function __construct(ShiftRepository $shiftRepository)
    {
        $this->shiftRepository = $shiftRepository;
    }

    /**
     * Kiểm tra xem ca làm việc có bị trùng thời gian không
     */
    public function isOverlap(int $organizationId, string $startTime, string $endTime, ?int $excludeId = null): bool
    {
        return $this->shiftRepository->isOverlap($organizationId, $startTime, $endTime, $excludeId);
    }
}
