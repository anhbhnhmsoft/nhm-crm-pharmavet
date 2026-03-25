<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Pages;

use App\Common\Constants\Customer\CustomerType;
use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\TelesaleOperationResource;
use App\Events\TelesaleLeadCreated;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\Telesale\LeadNotificationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Common\Constants\User\UserRole;

class CreateTelesaleOperation extends CreateRecord
{
    protected static string $resource = TelesaleOperationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        if (Auth::user()->role !== UserRole::SUPER_ADMIN->value || !isset($data['organization_id'])) {
            $data['organization_id'] = Auth::user()->organization_id;
        }
        /**
         * @var CustomerService $customerService
         */
        $customerService = app(CustomerService::class);

        $result = $customerService->createCustomerFromTelesaleOperation($data);
        if ($result->getData() instanceof Model) {
            /** @var Customer $customer */
            $customer = $result->getData();

            $aggregate = [
                'duplicate_key' => null,
                'is_duplicate' => false,
                'group_count' => 1,
            ];
            if (config('telesale.realtime.aggregate_notifications', true)) {
                /** @var LeadNotificationService $leadNotificationService */
                $leadNotificationService = app(LeadNotificationService::class);
                $aggregate = $leadNotificationService->aggregateDuplicateLeadNotifications($customer);
            }

            event(new TelesaleLeadCreated(
                customer: $customer,
                duplicateKey: $aggregate['duplicate_key'] ?? null,
                isDuplicate: (bool) ($aggregate['is_duplicate'] ?? false),
                groupCount: (int) ($aggregate['group_count'] ?? 1),
            ));

            if (in_array($customer->customer_type, [
                CustomerType::NEW_DUPLICATE->value,
                CustomerType::OLD_CUSTOMER->value,
            ], true)) {
                Notification::make()
                    ->title(__('telesale.messages.duplicate_lead_warning'))
                    ->warning()
                    ->send();
            }

            return $customer;
        }

        return new Customer();
    }
}
