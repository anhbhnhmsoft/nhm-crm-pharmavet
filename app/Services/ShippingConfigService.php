<?php

namespace App\Services;

use App\Core\ServiceReturn;
use App\Repositories\ShippingConfigRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShippingConfigService
{
    public function __construct(protected ShippingConfigRepository $shippingConfigRepository) {}

    public function getShippConfig($organizationId)
    {
        try {

            $result = $this->shippingConfigRepository->query()->firstOrCreate([
                'organization_id' => $organizationId
            ]);

            return ServiceReturn::success($result);
        } catch (Throwable $thr) {
            Log::error($thr->getMessage());
            return ServiceReturn::error($thr->getMessage());
        }
    }

    public function saveShippingConfig($data)
    {
        try {

            $result = $this->shippingConfigRepository->query()->updateOrCreate(
                ['organization_id' => $data['organization_id']],
                [
                    'account_name' => $data['account_name'],
                    'api_token' => $data['api_token'],
                    'default_store_id' => $data['default_store_id'],
                    'use_insurance' => $data['use_insurance'] ?? false,
                    'insurance_limit' => $data['insurance_limit'] ?? 0,
                    'required_note' => $data['required_note'],
                    'allow_cod_on_failed' => $data['allow_cod_on_failed'] ?? false,
                    'default_pickup_shift' => $data['default_pickup_shift'] ?? '1',
                    'default_pickup_time' => $data['default_pickup_time'] ?? null,
                ]
            );
            return ServiceReturn::success($result);
        } catch (Throwable $thr) {

            Log::error($thr->getMessage());
            return ServiceReturn::error($thr->getMessage());
        }
    }
}
