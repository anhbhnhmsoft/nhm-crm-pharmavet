<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Pages;

use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\TelesaleOperationResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class CreateTelesaleOperation extends CreateRecord
{
    protected static string $resource = TelesaleOperationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['organization_id'] = Auth::user()->organization_id;
        /**
         * @var CustomerService $customerService
         */
        $customerService = app(CustomerService::class);

        $result = $customerService->createCustomerFromTelesaleOperation($data);
        if ($result->getData() instanceof Model) {
            return $result->getData();
        }

        return new Customer();
    }
}
