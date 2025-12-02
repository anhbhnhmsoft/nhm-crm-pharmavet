<?php

namespace App\Services;

use App\Common\Constants\NormalStatus;
use App\Core\ServiceReturn;
use App\Repositories\WarehouseRepository;

class WarehouseService
{

    public function __construct(
        protected WarehouseRepository $warehouseRepository
    ) {}
    /**
     * Find the best matching warehouse for a given province.
     * Priority:
     * 1. Warehouse configured to deliver to this province (via warehouse_delivery_provinces)
     * 2. Warehouse located in this province
     * 3. Any active warehouse (fallback)
     */
    public function findWarehouseForProvince(int $provinceId, int $organizationId): ServiceReturn
    {
        // 1. Find warehouse that explicitly covers this province
        $warehouse = $this->warehouseRepository->query()->where('organization_id', $organizationId)
            ->where('is_active', NormalStatus::ACTIVE->value)
            ->whereHas('deliveryProvinces', function ($query) use ($provinceId) {
                $query->where('provinces.id', $provinceId);
            })
            ->first();

        if ($warehouse) {
            return ServiceReturn::success($warehouse);
        }

        // 2. Fallback: Find warehouse in the same province
        $warehouse = $this->warehouseRepository->query()->where('organization_id', $organizationId)
            ->where('is_active', NormalStatus::ACTIVE->value)
            ->where('province_id', $provinceId)
            ->first();

        if ($warehouse) {
            return ServiceReturn::success($warehouse);
        }

        // 3. Fallback: Any active warehouse
        return ServiceReturn::success($this->warehouseRepository->query()->where('organization_id', $organizationId)
            ->where('is_active', NormalStatus::ACTIVE->value)
            ->first());
    }
}
