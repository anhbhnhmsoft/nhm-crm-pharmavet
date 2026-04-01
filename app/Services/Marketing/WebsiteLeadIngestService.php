<?php

namespace App\Services\Marketing;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\Order\OrderStatus;
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

        $normalized = $this->applyIntegrationMapping(
            (array) Arr::get($payload, 'lead', []),
            $validation['normalized'],
            (array) ($integration->field_mapping ?? [])
        );
        $normalized = $this->applyDefaultValues($normalized, (array) Arr::get($integration->config ?? [], 'field_defaults', []));
        $qualityErrors = $this->validateNormalizedLead($normalized);
        if (!empty($qualityErrors)) {
            $log->update([
                'status' => 'invalid',
                'normalized_json' => $normalized,
                'error_json' => $qualityErrors,
            ]);

            return ServiceReturn::error('Invalid payload', data: [
                'errors' => $qualityErrors,
                'log_id' => $log->id,
            ]);
        }

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
                ->where('status', OrderStatus::COMPLETED->value)
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
            'note' => $this->buildCustomerNoteWithTag((string) ($normalized['note'] ?? ''), (string) Arr::get($integration->config ?? [], 'inbound_tag', 'form_register')),
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
            $this->websiteLeadIngestLogRepository->create([
                'organization_id' => (int) $integration->organization_id,
                'integration_id' => (int) $integration->id,
                'site_id' => $siteId,
                'request_id' => (string) ($payload['request_id'] ?? ''),
                'status' => 'ping_invalid',
                'payload_json' => $payload,
                'normalized_json' => null,
                'error_json' => $validation['errors'],
                'received_at' => now(),
            ]);

            return ServiceReturn::error('Invalid payload', data: [
                'errors' => $validation['errors'],
            ]);
        }

        $normalized = $this->applyIntegrationMapping(
            (array) Arr::get($payload, 'lead', []),
            $validation['normalized'],
            (array) ($integration->field_mapping ?? [])
        );
        $normalized = $this->applyDefaultValues($normalized, (array) Arr::get($integration->config ?? [], 'field_defaults', []));
        $qualityErrors = $this->validateNormalizedLead($normalized);
        if (!empty($qualityErrors)) {
            $this->websiteLeadIngestLogRepository->create([
                'organization_id' => (int) $integration->organization_id,
                'integration_id' => (int) $integration->id,
                'site_id' => $siteId,
                'request_id' => (string) ($payload['request_id'] ?? ''),
                'status' => 'ping_invalid',
                'payload_json' => $payload,
                'normalized_json' => $normalized,
                'error_json' => $qualityErrors,
                'received_at' => now(),
            ]);

            return ServiceReturn::error('Invalid payload', data: [
                'errors' => $qualityErrors,
            ]);
        }

        $this->websiteLeadIngestLogRepository->create([
            'organization_id' => (int) $integration->organization_id,
            'integration_id' => (int) $integration->id,
            'site_id' => $siteId,
            'request_id' => (string) ($payload['request_id'] ?? ''),
            'status' => 'ping_ok',
            'payload_json' => $payload,
            'normalized_json' => $normalized,
            'error_json' => null,
            'received_at' => now(),
        ]);

        return ServiceReturn::success([
            'ok' => true,
            'normalized' => $normalized,
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

    protected function applyIntegrationMapping(array $rawLead, array $normalized, array $mapping): array
    {
        if (empty($mapping)) {
            return $normalized;
        }

        $mapped = $normalized;
        foreach ($mapping as $left => $right) {
            if (!is_string($right) || !is_string($left)) {
                continue;
            }

            $left = trim($left);
            $right = trim($right);
            if ($left === '' || $right === '') {
                continue;
            }

            // Support both conventions:
            // 1) CRM field => Source field (recommended): name => full_name
            // 2) Source field => CRM field (legacy): full_name => name
            if (array_key_exists($left, $mapped) && array_key_exists($right, $rawLead)) {
                $mapped[$left] = is_string($rawLead[$right]) ? trim($rawLead[$right]) : $rawLead[$right];
                continue;
            }

            if (array_key_exists($right, $mapped) && array_key_exists($left, $rawLead)) {
                $mapped[$right] = is_string($rawLead[$left]) ? trim($rawLead[$left]) : $rawLead[$left];
            }
        }

        return $mapped;
    }

    protected function applyDefaultValues(array $normalized, array $defaults): array
    {
        if (empty($defaults)) {
            return $normalized;
        }

        foreach ($defaults as $field => $value) {
            if (!is_string($field) || !array_key_exists($field, $normalized)) {
                continue;
            }

            $current = $normalized[$field];
            if ($current === null || (is_string($current) && trim($current) === '')) {
                $normalized[$field] = is_string($value) ? trim($value) : $value;
            }
        }

        return $normalized;
    }

    protected function validateNormalizedLead(array $normalized): array
    {
        $errors = [];

        $phoneDigits = preg_replace('/[^0-9]/', '', (string) ($normalized['phone'] ?? ''));
        if ($phoneDigits === '' || strlen($phoneDigits) < 9 || strlen($phoneDigits) > 11) {
            $errors['lead.phone'][] = 'Phone number must contain 9-11 digits.';
        }

        if ($phoneDigits !== '' && preg_match('/^(\d)\1+$/', $phoneDigits) === 1) {
            $errors['lead.phone'][] = 'Phone number is not valid.';
        }

        $email = strtolower(trim((string) ($normalized['email'] ?? '')));
        if ($email !== '' && preg_match('/@example\.(com|org|net)$/', $email) === 1) {
            $errors['lead.email'][] = 'Email appears to be a placeholder.';
        }

        return $errors;
    }

    protected function buildCustomerNoteWithTag(string $note, string $tag): string
    {
        $tag = trim($tag);
        $tagText = $tag !== '' ? '#' . ltrim($tag, '#') : '';

        if ($tagText === '') {
            return $note;
        }

        $base = trim($note);
        if ($base === '') {
            return $tagText;
        }

        if (str_contains($base, $tagText)) {
            return $base;
        }

        return $base . PHP_EOL . $tagText;
    }
}
