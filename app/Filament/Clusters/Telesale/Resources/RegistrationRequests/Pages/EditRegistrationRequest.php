<?php

namespace App\Filament\Clusters\Telesale\Resources\RegistrationRequests\Pages;

use App\Filament\Clusters\Telesale\Resources\RegistrationRequests\RegistrationRequestResource;
use App\Services\CustomerService;
use Filament\Resources\Pages\EditRecord;

class EditRegistrationRequest extends EditRecord
{
    protected static string $resource = RegistrationRequestResource::class;

    protected CustomerService $customerService;

    public function boot(CustomerService $customerService): void
    {
        $this->customerService = $customerService;
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    protected function afterSave(): void
    {
        $data = $this->form->getRawState();

        if (!empty($data['new_interaction_content']) && !empty($data['new_interaction_status'])) {
            $customer = $this->record;
            
            $result = $this->customerService->saveInteraction($customer, $data);

            if ($result->isSuccess()) {
                $this->form->fill([
                    'new_interaction_status' => null,
                    'new_interaction_content' => null,
                    'next_action_at' => null,
                ]);
            }
        }
    }
}
