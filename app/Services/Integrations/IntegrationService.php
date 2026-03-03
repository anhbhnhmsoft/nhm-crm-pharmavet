<?php

namespace App\Services\Integrations;

use App\Core\ServiceReturn;
use App\Repositories\IntegrationRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class IntegrationService
{
    protected IntegrationRepository $integrationRepository;
    /**
     * @param IntegrationRepository $integrationRepository
     */
    public function __construct(IntegrationRepository $integrationRepository)
    {
        $this->integrationRepository = $integrationRepository;
    }

    /**
     * Initialize integration
     * @param array $data
     * @return ServiceReturn
     */
    public function initIntegration(array $data): ServiceReturn
    {
        try {
            $result = $this->integrationRepository->create($data);
            return ServiceReturn::success($result);
        } catch (Throwable $th) {
            Log::error('Integration initialization failed', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            return ServiceReturn::error(__('messages.integration.init_failed'));
        }
    }

    public function getIntegration(int $id): ServiceReturn
    {
        try {
            $result = $this->integrationRepository->find($id);
            return ServiceReturn::success($result);
        } catch (Throwable $th) {
            Log::error('Integration retrieval failed', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            return ServiceReturn::error(__('messages.integration.init_failed'));
        }
    }
}
