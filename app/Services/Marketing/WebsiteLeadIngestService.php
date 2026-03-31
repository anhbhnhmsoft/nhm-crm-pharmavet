<?php

namespace App\Services\Marketing;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Marketing\IntegrationType;
use App\Core\ServiceReturn;
use App\Repositories\CustomerRepository;
use App\Repositories\IntegrationRepository;
use App\Repositories\WebsiteLeadIngestLogRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class WebsiteLeadIngestService
{
    public function __construct(
        protected IntegrationRepository $integrationRepository,
        protected CustomerRepository $customerRepository,
        protected WebsiteLeadIngestLogRepository $websiteLeadIngestLogRepository,
    ) {
    }

    public function ingest(string $siteId, array $payload, ?string $token = null): ServiceReturn
    {
        $integration = $this->integrationRepository->query()
            ->where('type', IntegrationType::WEBSITE->value)
            ->where('config->site_id', $siteId)
            ->first();

        if (!$integration) {
            return ServiceReturn::error('Website integration not found');
        }

        $expectedToken = (string) Arr::get($integration->config ?? [], 'webhook_secret', '');
        if ($expectedToken === '' || $expectedToken !== (string) $token) {
            return ServiceReturn::error('Unauthorized');
        }

        $validation = $this->validatePayload($payload);
        $log = $this->websiteLeadIngestLogRepository->create([
            'organization_id' => (int) $integration->organization_id,
            'integration_id' => (int) $integration->id,
            'site_id' => $siteId,
            'request_id' => (string) ($payload['request_id'] ?? ''),
            'status' => $validation['valid'] ? 'validated' : 'invalid',
            'payload_json' => $payload,
            'normalized_json' => $validation['normalized'] ?? null,
            'error_json' => $validation['errors'] ?? null,
            'received_at' => now(),
        ]);

        if (!$validation['valid']) {
            return ServiceReturn::error('Invalid payload', data: [
                'errors' => $validation['errors'],
                'log_id' => $log->id,
            ]);
        }

        $normalized = $this->applyIntegrationMapping($validation['normalized'], (array) ($integration->field_mapping ?? []));
        $phone = preg_replace('/[^0-9]/', '', (string) ($normalized['phone'] ?? ''));
        $email = strtolower(trim((string) ($normalized['email'] ?? '')));

        $existing = $this->customerRepository->query()
            ->where('organization_id', (int) $integration->organization_id)
            ->where(function ($query) use ($phone, $email) {
                if ($phone !== '') {
                    $query->where('phone', $phone);
                }
                if ($email !== '') {
                    $query->orWhere('email', $email);
                }
            })
            ->first();

        $customerType = CustomerType::NEW->value;
        if ($existing) {
            $hasCompleted = $existing->orders()
                ->where('status', \App\Common\Constants\StatusProgress::COMPLETED->value)
                ->exists();
            $customerType = $hasCompleted ? CustomerType::OLD_CUSTOMER->value : CustomerType::NEW_DUPLICATE->value;
        }

        $mapping = (array) ($integration->field_mapping ?? []);
        $sourceDetail = (string) ($normalized['source_detail'] ?? 'website_v2');
        $productId = (int) ($normalized['product_id'] ?? Arr::get($integration->config ?? [], 'default_product_id', 0));

        $customer = $this->customerRepository->create([
            'organization_id' => (int) $integration->organization_id,
            'username' => (string) ($normalized['name'] ?? 'Website Lead'),
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'address' => (string) ($normalized['address'] ?? ''),
            'customer_type' => $customerType,
            'assigned_staff_id' => null,
            'note' => (string) ($normalized['note'] ?? ''),
            'source' => IntegrationType::WEBSITE->value,
            'source_detail' => $sourceDetail,
            'source_id' => (string) ($normalized['external_id'] ?? ''),
            'interaction_status' => InteractionStatus::FIRST_CALL->value,
            'product_id' => $productId > 0 ? $productId : null,
        ]);

        $log->update(['status' => 'processed']);

        return ServiceReturn::success([
            'customer_id' => $customer->id,
            'customer_type' => $customerType,
            'log_id' => $log->id,
            'mapping' => $mapping,
            'normalized' => $normalized,
        ]);
    }

    public function ping(string $siteId, array $payload, ?string $token = null): ServiceReturn
    {
        $integration = $this->integrationRepository->query()
            ->where('type', IntegrationType::WEBSITE->value)
            ->where('config->site_id', $siteId)
            ->first();

        if (!$integration) {
            return ServiceReturn::error('Website integration not found');
        }

        $expectedToken = (string) Arr::get($integration->config ?? [], 'webhook_secret', '');
        if ($expectedToken === '' || $expectedToken !== (string) $token) {
            return ServiceReturn::error('Unauthorized');
        }

        $validation = $this->validatePayload($payload);
        if (!$validation['valid']) {
            return ServiceReturn::error('Invalid payload', data: [
                'errors' => $validation['errors'],
            ]);
        }

        return ServiceReturn::success([
            'ok' => true,
            'normalized' => $validation['normalized'],
        ]);
    }

    protected function validatePayload(array $payload): array
    {
        $allowedRootKeys = ['request_id', 'lead'];
        $invalidRootKeys = array_values(array_diff(array_keys($payload), $allowedRootKeys));
        if (!empty($invalidRootKeys)) {
            return [
                'valid' => false,
                'errors' => [
                    'payload' => ['Unsupported root keys: ' . implode(', ', $invalidRootKeys)],
                ],
            ];
        }

        $lead = (array) ($payload['lead'] ?? []);
        $allowedLeadKeys = ['name', 'phone', 'email', 'address', 'note', 'source_detail', 'external_id', 'product_id'];
        $invalidLeadKeys = array_values(array_diff(array_keys($lead), $allowedLeadKeys));
        if (!empty($invalidLeadKeys)) {
            return [
                'valid' => false,
                'errors' => [
                    'lead' => ['Unsupported lead keys: ' . implode(', ', $invalidLeadKeys)],
                ],
            ];
        }

        $validator = Validator::make($payload, [
            'request_id' => ['nullable', 'string', 'max:120'],
            'lead' => ['required', 'array'],
            'lead.name' => ['required', 'string', 'max:255'],
            'lead.phone' => ['required', 'string', 'max:30'],
            'lead.email' => ['nullable', 'email', 'max:255'],
            'lead.address' => ['nullable', 'string', 'max:255'],
            'lead.note' => ['nullable', 'string'],
            'lead.source_detail' => ['nullable', 'string', 'max:255'],
            'lead.external_id' => ['nullable', 'string', 'max:120'],
            'lead.product_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
            ];
        }

        return [
            'valid' => true,
            'normalized' => [
                'name' => trim((string) Arr::get($payload, 'lead.name')),
                'phone' => trim((string) Arr::get($payload, 'lead.phone')),
                'email' => trim((string) Arr::get($payload, 'lead.email')),
                'address' => trim((string) Arr::get($payload, 'lead.address')),
                'note' => trim((string) Arr::get($payload, 'lead.note')),
                'source_detail' => trim((string) Arr::get($payload, 'lead.source_detail')),
                'external_id' => trim((string) Arr::get($payload, 'lead.external_id')),
                'product_id' => (int) Arr::get($payload, 'lead.product_id', 0),
            ],
        ];
    }

    protected function applyIntegrationMapping(array $normalized, array $mapping): array
    {
        if (empty($mapping)) {
            return $normalized;
        }

        $mapped = $normalized;
        foreach ($mapping as $sourceKey => $target) {
            if (!is_string($target)) {
                continue;
            }

            if (array_key_exists($sourceKey, $normalized) && array_key_exists($target, $mapped)) {
                $mapped[$target] = $normalized[$sourceKey];
            }
        }

        return $mapped;
    }
}
