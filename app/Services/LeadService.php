<?php

namespace App\Services;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\DefaultConstant;
use App\Common\Constants\Marketing\IntegrationType;
use App\Core\Logging;
use App\Core\ServiceReturn;
use App\Repositories\CustomerRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\ProductRepository;
use App\Services\LeadDistributionService;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class LeadService
{
    public function __construct(
        protected CustomerRepository $customerRepository,
        protected OrganizationRepository $organizationRepository,
        protected ProductRepository $productRepository,
        protected LeadDistributionService $leadDistributionService
    ) {}

    public function submitRegistration(array $data): ServiceReturn
    {
        try {
            DB::beginTransaction();

            $customerData = [
                'username' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'customer_type' => CustomerType::PARTNER_REQUEST->value,
                'organization_id' => DefaultConstant::DEFAULT_ORGANIZATION_ID,
                'source' => IntegrationType::PARTNER_REGISTRATION->value,
                'product_field_id' => isset($data['product_id']) && is_numeric($data['product_id']) ? (int)$data['product_id'] : null,
                'note_temp' => $this->formatNoteTemp($data),
            ];

            $lead = $this->customerRepository->create($customerData);

            $productId = (int) ($customerData['product_field_id'] ?? DefaultConstant::DEFAULT_PRODUCT_ID); 
            $assignedStaff = $this->leadDistributionService->assignLead($lead, $productId, DefaultConstant::DEFAULT_ORGANIZATION_ID);

            if ($assignedStaff) {
                $this->customerRepository->updateById($lead->id, ['assigned_staff_id' => $assignedStaff->id]);

                Notification::make()
                    ->title(__('auth.login.new_lead_notification'))
                    ->body(__('auth.login.new_lead_body', ['name' => $lead->username, 'phone' => $lead->phone]))
                    ->icon('heroicon-o-user-group')
                    ->color('success')
                    ->sendToDatabase($assignedStaff);
            }

            DB::commit();

            return ServiceReturn::success($lead, __('auth.registration.success_title'));
        } catch (Exception $e) {
            DB::rollBack();
            Logging::error('Lead registration failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return ServiceReturn::error(__('auth.registration.error', ['error' => $e->getMessage()]));
        }
    }

    protected function formatNoteTemp(array $data): string
    {
        $lines = [];
        $lines[] = __('auth.registration.note_template.header');
        $lines[] = __('auth.registration.note_template.industry') . ': ' . ($data['industry'] ?? 'N/A');
        $lines[] = __('auth.registration.note_template.employee_count') . ': ' . ($data['employee_count'] ?? 'N/A');
        
        $preferredTime = $data['preferred_time'] ?? 'anytime';
        $timeLabel = __('auth.registration.options.preferred_time.' . $preferredTime);
        $lines[] = __('auth.registration.note_template.preferred_time') . ': ' . $timeLabel;
        
        $lines[] = __('auth.registration.note_template.sent_at') . ': ' . now()->format('d/m/Y H:i');
        
        return implode("\n", $lines);
    }
}
