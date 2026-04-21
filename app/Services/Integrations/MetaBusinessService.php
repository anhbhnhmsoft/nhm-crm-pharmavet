<?php

namespace App\Services\Integrations;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Marketing\FacebookConnectionStatus;
use App\Common\Constants\Marketing\IntegrationEntityType;
use App\Common\Constants\Marketing\IntegrationStatus;
use App\Common\Constants\Marketing\IntegrationTokenType;
use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\StatusConnect;
use App\Common\Constants\User\UserRole;
use App\Core\ServiceReturn;
use App\Models\FacebookLead;
use App\Models\Integration;
use App\Models\IntegrationEntity;
use App\Models\User;
use App\Repositories\CustomerRepository;
use App\Repositories\FacebookLeadRepository;
use App\Repositories\IntegrationEntityRepository;
use App\Repositories\IntegrationRepository;
use App\Repositories\IntegrationTokenRepository;
use App\Repositories\LeadDistributionConfigRepository;
use App\Services\LeadDistributionService;
use FacebookAds\Api;
use FacebookAds\Object\Lead;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaBusinessService
{
    public const GRAPH_API_URL = 'https://graph.facebook.com/';

    protected ?Api $api = null;

    public function __construct(
        protected CustomerRepository $customerRepository,
        protected IntegrationRepository $integrationRepository,
        protected IntegrationTokenRepository $integrationTokenRepository,
        protected IntegrationEntityRepository $integrationEntityRepository,
        protected LeadDistributionConfigRepository $leadDistributionConfigRepository,
        protected LeadDistributionService $leadDistributionService,
        protected FacebookLeadRepository $facebookLeadRepository,
        protected FacebookLeadMapper $facebookLeadMapper,
    ) {
        if (class_exists(Api::class)) {
            Api::init(
                (string) config('services.facebook.app_id', config('services.facebook.client_id')),
                (string) config('services.facebook.app_secret', config('services.facebook.client_secret')),
                (string) config('services.facebook.access_token', '')
            );
            $this->api = Api::instance();
        }
    }

    public function getRedirectUrl(?string $state = null): string
    {
        $appId = (string) config('services.facebook.app_id', config('services.facebook.client_id'));
        $configId = (string) config('services.facebook.login_config_id');
        $redirectUri = (string) config('services.facebook.redirect');

        if ($appId === '' || $configId === '' || $redirectUri === '') {
            throw new \RuntimeException(__('messages.meta_business.error.login_configuration_missing'));
        }

        $version = trim((string) config('services.facebook.graph_api_version', 'v25.0'), '/');

        return 'https://www.facebook.com/'.$version.'/dialog/oauth?'.http_build_query(array_filter([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'response_type' => 'code',
            'override_default_response_type' => 'true',
            'config_id' => $configId,
        ], static fn ($value) => $value !== null && $value !== ''));
    }

    protected function getRequiredScopes(): array
    {
        return [
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_metadata',
            'leads_retrieval',
        ];
    }

    public function connectWithUserAccessToken(User $user, string $userAccessToken, ?Integration $integration = null): ServiceReturn
    {
        try {
            $integration ??= $this->integrationRepository->createOrReuseFacebookIntegration(
                (int) $user->organization_id,
                (int) $user->id,
                __('filament.integration.defaults.facebook_name')
            );

            $longLivedToken = $this->exchangeLongLivedToken($userAccessToken);

            if (!$longLivedToken) {
                throw new \RuntimeException(__('messages.meta_business.error.exchange_token_failed'));
            }

            $this->integrationTokenRepository->upsertUserToken([
                'integration_id' => $integration->id,
                'entity_id' => null,
                'token' => $longLivedToken['access_token'],
                'scopes' => $this->getRequiredScopes(),
                'expires_at' => now()->addSeconds($longLivedToken['expires_in'] ?? 5184000),
                'status' => StatusConnect::CONNECTED->value,
            ]);

            $syncResult = $this->syncPages($integration);
            if ($syncResult->isError()) {
                return $syncResult;
            }

            $pages = $integration->facebookPages()
                ->orderByDesc('id')
                ->get()
                ->map(fn (IntegrationEntity $entity) => $this->serializeEntity($entity))
                ->values()
                ->all();

            return ServiceReturn::success([
                'integration_id' => $integration->id,
                'count' => count($pages),
                'pages' => $pages,
            ], __('messages.meta_business.success.pending_approval'));
        } catch (\Throwable $throwable) {
            Log::error('Facebook connect failed', [
                'integration_id' => $integration?->id,
                'organization_id' => $user->organization_id,
                'error' => $throwable->getMessage(),
            ]);

            if ($integration) {
                $integration->update([
                    'status' => IntegrationStatus::ERROR->value,
                    'status_message' => $throwable->getMessage(),
                ]);
            }

            return ServiceReturn::error(__('messages.meta_business.error.connect_failed'), $throwable);
        }
    }

    public function handleCallback(Integration $integration, string $authorizationCode): ServiceReturn
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return ServiceReturn::error(__('common.error.invalid_or_expired_token'));
            }

            if ($authorizationCode === '') {
                return ServiceReturn::error(__('filament.integration.errors.connection_failed'));
            }

            $tokenPayload = $this->exchangeAuthorizationCodeForToken($authorizationCode);

            if (!$tokenPayload || empty($tokenPayload['access_token'])) {
                throw new \RuntimeException(__('messages.meta_business.error.exchange_code_failed'));
            }

            return $this->connectWithUserAccessToken($user, (string) $tokenPayload['access_token'], $integration);
        } catch (\Throwable $throwable) {
            Log::error('Facebook callback failed', [
                'integration_id' => $integration->id,
                'error' => $throwable->getMessage(),
            ]);

            $integration->update([
                'status' => IntegrationStatus::ERROR->value,
                'status_message' => $throwable->getMessage(),
            ]);

            return ServiceReturn::error(__('messages.meta_business.error.callback_failed'), $throwable);
        }
    }

    protected function exchangeAuthorizationCodeForToken(string $authorizationCode): ?array
    {
        try {
            $response = Http::asForm()->post($this->graphUrl('/oauth/access_token'), [
                'client_id' => config('services.facebook.app_id', config('services.facebook.client_id')),
                'client_secret' => config('services.facebook.app_secret', config('services.facebook.client_secret')),
                'redirect_uri' => config('services.facebook.redirect'),
                'code' => $authorizationCode,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to exchange business login authorization code', [
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $throwable) {
            Log::error('Exception exchanging business login authorization code', [
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    protected function exchangeLongLivedToken(string $shortToken): ?array
    {
        try {
            $response = Http::get($this->graphUrl('/oauth/access_token'), [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.facebook.client_id'),
                'client_secret' => config('services.facebook.client_secret'),
                'fb_exchange_token' => $shortToken,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to exchange long-lived token', [
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $throwable) {
            Log::error('Exception exchanging token', [
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    public function syncPages(Integration $integration): ServiceReturn
    {
        try {
            $userToken = $this->integrationTokenRepository->getUserLongLivedToken($integration->id);
            if (!$userToken) {
                throw new \RuntimeException(__('messages.meta_business.error.no_user_token'));
            }

            $response = Http::get($this->graphUrl('/me/accounts'), [
                'access_token' => $userToken->token,
                'fields' => 'id,name,category,access_token,picture{url},tasks',
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException(__('messages.meta_business.error.fetch_pages_failed', ['error' => $response->body()]));
            }

            $pages = $response->json('data', []);
            $syncedCount = 0;

            foreach ($pages as $pageData) {
                $tasks = $pageData['tasks'] ?? [];

                if (!$this->hasLeadgenPermission($tasks)) {
                    continue;
                }

                $existing = $this->integrationEntityRepository->query()
                    ->where('integration_id', $integration->id)
                    ->where('type', IntegrationEntityType::PAGE_META->value)
                    ->where('external_id', (string) $pageData['id'])
                    ->first();

                $metadata = $this->buildPageMetadata($existing, $pageData);

                $entity = $this->integrationEntityRepository->upsertPendingFacebookPage($integration->id, $pageData, $metadata);

                $this->integrationTokenRepository->upsertPageAccessToken([
                    'integration_id' => $integration->id,
                    'entity_id' => $entity->id,
                    'token' => $pageData['access_token'],
                    'scopes' => $pageData['tasks'] ?? [],
                    'expires_at' => now()->addDays(60),
                    'status' => StatusConnect::CONNECTED->value,
                ]);

                $syncedCount++;
            }

            $integration->update([
                'status' => IntegrationStatus::PENDING->value,
                'status_message' => __('messages.meta_business.pending_approval'),
                'last_sync_at' => now(),
            ]);

            return ServiceReturn::success([
                'count' => $syncedCount,
                'pending_count' => $integration->pendingFacebookPages()->count(),
            ], __('messages.meta_business.success.pages_synced_pending'));
        } catch (\Throwable $throwable) {
            Log::error('Failed to sync pages', [
                'integration_id' => $integration->id,
                'error' => $throwable->getMessage(),
            ]);

            $integration->update([
                'status' => IntegrationStatus::ERROR->value,
                'status_message' => $throwable->getMessage(),
            ]);

            return ServiceReturn::error(__('messages.meta_business.error.sync_pages_failed'), $throwable);
        }
    }

    public function approveConnections(User $actor, Integration $integration, array $pageIds = []): ServiceReturn
    {
        try {
            if (!$this->canApprove($actor, $integration)) {
                return ServiceReturn::error(__('common.error.403'));
            }

            $entities = $integration->facebookPages()
                ->when($pageIds !== [], fn ($query) => $query->whereIn('external_id', $pageIds))
                ->get();

            if ($entities->isEmpty()) {
                return ServiceReturn::error(__('messages.meta_business.error.page_entity_not_found'));
            }

            $approvedCount = 0;
            $failed = [];

            foreach ($entities as $entity) {
                $pageToken = $this->integrationTokenRepository->getActivePageAccessTokenByEntity($integration->id, $entity->id);

                if (!$pageToken) {
                    $entity->update([
                        'status_reason' => __('messages.meta_business.error.page_token_not_found'),
                    ]);
                    $failed[] = $entity->external_id;
                    continue;
                }

                $pageToken = $this->integrationTokenRepository->markExpiredIfNeeded($pageToken);

                if ((int) $pageToken->status !== StatusConnect::CONNECTED->value) {
                    $entity->update([
                        'status' => FacebookConnectionStatus::EXPIRED->value,
                        'status_reason' => __('messages.meta_business.error.page_token_expired'),
                    ]);
                    $failed[] = $entity->external_id;
                    continue;
                }

                $subscribed = $this->subscribePageToWebhook($entity, $pageToken->token);
                $metadata = $entity->metadata ?? [];
                $metadata['webhook_subscribed'] = $subscribed;
                $metadata['webhook_subscribed_at'] = $subscribed ? now()->toDateTimeString() : null;

                if (!$subscribed) {
                    $entity->update([
                        'metadata' => $metadata,
                        'status_reason' => __('messages.meta_business.error.webhook_subscribe_failed'),
                    ]);
                    $failed[] = $entity->external_id;
                    continue;
                }

                $entity->update([
                    'metadata' => $metadata,
                    'status' => FacebookConnectionStatus::APPROVED->value,
                    'approved_by' => $actor->id,
                    'approved_at' => now(),
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'webhook_subscribed_at' => now(),
                    'status_reason' => null,
                    'disconnected_at' => null,
                ]);

                $approvedCount++;
            }

            $this->refreshIntegrationAggregateStatus($integration->refresh());

            return ServiceReturn::success([
                'approved_count' => $approvedCount,
                'failed_page_ids' => $failed,
            ], __('messages.meta_business.success.approved'));
        } catch (\Throwable $throwable) {
            Log::error('Approve facebook connection failed', [
                'integration_id' => $integration->id,
                'actor_id' => $actor->id,
                'error' => $throwable->getMessage(),
            ]);

            return ServiceReturn::error(__('messages.meta_business.error.approve_failed'), $throwable);
        }
    }

    public function rejectConnections(User $actor, Integration $integration, array $pageIds = [], ?string $reason = null): ServiceReturn
    {
        try {
            if (!$this->canApprove($actor, $integration)) {
                return ServiceReturn::error(__('common.error.403'));
            }

            $updated = $this->integrationEntityRepository->rejectPages($integration->id, $pageIds, $actor, $reason);
            $this->refreshIntegrationAggregateStatus($integration->refresh());

            return ServiceReturn::success([
                'rejected_count' => $updated,
            ], __('messages.meta_business.success.rejected'));
        } catch (\Throwable $throwable) {
            return ServiceReturn::error(__('messages.meta_business.error.reject_failed'), $throwable);
        }
    }

    public function fetchLead(string $leadId, string $pageAccessToken): ServiceReturn
    {
        try {
            $api = $this->api;
            if ($api) {
                $api->setDefaultAccessToken($pageAccessToken);
                $lead = new Lead($leadId);
                $data = $lead->read(['id', 'created_time', 'field_data', 'form_id', 'ad_id', 'campaign_id', 'adset_id'])->exportAllData();

                return ServiceReturn::success([
                    'id' => $data['id'] ?? null,
                    'form_id' => $data['form_id'] ?? null,
                    'ad_id' => $data['ad_id'] ?? null,
                    'campaign_id' => $data['campaign_id'] ?? null,
                    'adset_id' => $data['adset_id'] ?? null,
                    'created_time' => $data['created_time'] ?? null,
                    'fields' => $this->extractLeadFields($data),
                    'field_data' => $data['field_data'] ?? [],
                ]);
            }

            $response = Http::get($this->graphUrl("/{$leadId}"), [
                'access_token' => $pageAccessToken,
                'fields' => 'id,created_time,field_data,form_id,ad_id,campaign_id,adset_id',
            ]);

            if (!$response->successful()) {
                return ServiceReturn::error(__('messages.meta_business.error.fetch_lead_failed'));
            }

            $data = $response->json();

            return ServiceReturn::success([
                'id' => $data['id'] ?? null,
                'form_id' => $data['form_id'] ?? null,
                'ad_id' => $data['ad_id'] ?? null,
                'campaign_id' => $data['campaign_id'] ?? null,
                'adset_id' => $data['adset_id'] ?? null,
                'created_time' => $data['created_time'] ?? null,
                'fields' => $this->extractLeadFields($data),
                'field_data' => $data['field_data'] ?? [],
            ]);
        } catch (\Throwable $throwable) {
            return ServiceReturn::error(__('messages.meta_business.error.fetch_lead_failed'), $throwable);
        }
    }

    public function testConnection(Integration $integration): ServiceReturn
    {
        try {
            $userToken = $this->integrationTokenRepository->getUserLongLivedToken($integration->id);

            if (!$userToken) {
                return ServiceReturn::error(__('messages.meta_business.error.no_user_token'));
            }

            $response = Http::get($this->graphUrl('/me'), [
                'access_token' => $userToken->token,
                'fields' => 'id,name',
            ]);

            return ServiceReturn::success(['connected' => $response->successful()]);
        } catch (\Throwable $throwable) {
            return ServiceReturn::error(__('messages.meta_business.error.connection_test_failed'), $throwable);
        }
    }

    public function disconnect(Integration $integration): ServiceReturn
    {
        try {
            foreach ($integration->facebookPages()->get() as $entity) {
                $pageToken = $this->integrationTokenRepository->getActivePageAccessTokenByEntity($integration->id, $entity->id);

                if ($pageToken && (int) $entity->status === FacebookConnectionStatus::APPROVED->value) {
                    $this->unsubscribePageFromWebhook($entity, $pageToken->token);
                }

                $metadata = $entity->metadata ?? [];
                $metadata['webhook_subscribed'] = false;
                $metadata['webhook_subscribed_at'] = null;

                $entity->update([
                    'metadata' => $metadata,
                    'status' => FacebookConnectionStatus::DISCONNECTED->value,
                    'disconnected_at' => now(),
                    'status_reason' => __('messages.meta_business.disconnected'),
                ]);

                $this->integrationTokenRepository->markEntityTokensDisconnected($integration->id, $entity->id);
            }

            $integration->update([
                'status' => IntegrationStatus::PENDING->value,
                'status_message' => __('messages.meta_business.disconnected'),
            ]);

            return ServiceReturn::success(null, __('messages.meta_business.success.disconnected'));
        } catch (\Throwable $throwable) {
            return ServiceReturn::error(__('messages.meta_business.error.disconnect_failed'), $throwable);
        }
    }

    public function verifyIntegrationByWebhookToken(string $token): ServiceReturn
    {
        $expected = (string) config('services.facebook.webhook_verify_token', '');

        if ($expected !== '' && hash_equals($expected, $token)) {
            return ServiceReturn::success(true);
        }

        return ServiceReturn::error(__('messages.meta_business.error.invalid_verify_token'));
    }

    public function findApprovedPageByPageId(string $pageId): ServiceReturn
    {
        try {
            $entity = $this->integrationEntityRepository->findApprovedFacebookPageByExternalId($pageId);

            if (!$entity) {
                return ServiceReturn::error(__('messages.meta_business.error.page_entity_not_found'));
            }

            return ServiceReturn::success($entity);
        } catch (\Throwable $throwable) {
            return ServiceReturn::error(__('messages.meta_business.error.lookup_page_error'), $throwable);
        }
    }

    public function findIntegrationByPageId(string $pageId): ServiceReturn
    {
        $pageResult = $this->findApprovedPageByPageId($pageId);
        if ($pageResult->isError()) {
            return $pageResult;
        }

        return ServiceReturn::success($pageResult->getData()->integration);
    }

    public function queueLeadFromWebhook(string $pageId, string $leadId, array $payload = []): ServiceReturn
    {
        $pageResult = $this->findApprovedPageByPageId($pageId);
        if ($pageResult->isError()) {
            return $pageResult;
        }

        /** @var IntegrationEntity $entity */
        $entity = $pageResult->getData();
        $integration = $entity->integration;

        $facebookLead = $this->facebookLeadRepository->firstOrCreateQueued([
            'organization_id' => $integration->organization_id,
            'integration_id' => $integration->id,
            'entity_id' => $entity->id,
            'page_id' => $pageId,
            'leadgen_id' => $leadId,
            'payload_json' => $payload,
        ]);

        return ServiceReturn::success($facebookLead);
    }

    public function processQueuedLead(int $facebookLeadId): ServiceReturn
    {
        /** @var FacebookLead|null $facebookLead */
        $facebookLead = $this->facebookLeadRepository->find($facebookLeadId) ?: null;

        if (!$facebookLead) {
            return ServiceReturn::error(__('messages.meta_business.error.facebook_lead_not_found'), data: ['retryable' => false]);
        }

        if ($facebookLead->status === 'processed') {
            return ServiceReturn::success(['already_processed' => true]);
        }

        try {
            /** @var IntegrationEntity|null $entity */
            $entity = $this->integrationEntityRepository->query()->find($facebookLead->entity_id);
            if (!$entity) {
                $this->facebookLeadRepository->markFailed($facebookLead, __('messages.meta_business.error.page_entity_not_found'));
                return ServiceReturn::error(__('messages.meta_business.error.page_entity_not_found'), data: ['retryable' => false]);
            }

            $pageToken = $this->integrationTokenRepository->getActivePageAccessTokenByEntity($facebookLead->integration_id, $entity->id);
            if (!$pageToken) {
                $this->facebookLeadRepository->markFailed($facebookLead, __('messages.meta_business.error.page_token_not_found'));
                return ServiceReturn::error(__('messages.meta_business.error.page_token_not_found'));
            }

            $leadResult = $this->fetchLead($facebookLead->leadgen_id, $pageToken->token);
            if ($leadResult->isError()) {
                $this->facebookLeadRepository->markFailed($facebookLead, $leadResult->getMessage());
                return $leadResult;
            }

            $leadData = $leadResult->getData();
            $leadData['page_id'] = $entity->external_id;

            $marketingData = [];
            if (!empty($leadData['ad_id'])) {
                $userToken = $this->integrationTokenRepository->getUserLongLivedToken($facebookLead->integration_id);
                if ($userToken) {
                    $marketingData = $this->fetchAdInfo((string) $leadData['ad_id'], $userToken->token) ?? [];
                }
            }

            $facebookLead->update([
                'form_id' => $leadData['form_id'] ?? null,
                'normalized_payload_json' => [
                    'lead_data' => $leadData,
                    'marketing_data' => $marketingData,
                ],
            ]);

            $storeResult = $this->storeFacebookLeadByIntegration(
                (int) $facebookLead->integration_id,
                $leadData,
                $entity->metadata['default_product_id'] ?? null,
                $marketingData
            );

            if ($storeResult->isError()) {
                $this->facebookLeadRepository->markFailed($facebookLead, $storeResult->getMessage());
                return $storeResult;
            }

            $entity->update([
                'last_lead_received_at' => now(),
            ]);

            $this->facebookLeadRepository->markProcessed($facebookLead);

            return ServiceReturn::success($storeResult->getData());
        } catch (\Throwable $throwable) {
            if ($facebookLead) {
                $this->facebookLeadRepository->markFailed($facebookLead, $throwable->getMessage());
            }

            return ServiceReturn::error(__('messages.meta_business.error.process_lead_failed'), $throwable);
        }
    }

    public function processLead(int $integrationId, string $pageId, string $leadId): ServiceReturn
    {
        $queueResult = $this->queueLeadFromWebhook($pageId, $leadId, [
            'legacy_process' => true,
            'integration_id' => $integrationId,
        ]);

        if ($queueResult->isError()) {
            return $queueResult;
        }

        /** @var FacebookLead $facebookLead */
        $facebookLead = $queueResult->getData();

        return $this->processQueuedLead($facebookLead->id);
    }

    protected function fetchAdInfo(string $adId, string $userAccessToken): ?array
    {
        try {
            $response = Http::get($this->graphUrl("/{$adId}"), [
                'access_token' => $userAccessToken,
                'fields' => 'id,name,campaign{id,name},adset{id,name}',
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            return [
                'ad_id' => $data['id'] ?? null,
                'ad_name' => $data['name'] ?? null,
                'campaign_id' => $data['campaign']['id'] ?? null,
                'campaign_name' => $data['campaign']['name'] ?? null,
                'adset_id' => $data['adset']['id'] ?? null,
                'adset_name' => $data['adset']['name'] ?? null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function storeFacebookLeadByIntegration(int $integrationId, array $leadData, ?int $productId = null, array $marketingData = []): ServiceReturn
    {
        DB::beginTransaction();

        try {
            $integration = $this->integrationRepository->find($integrationId);
            if (!$integration instanceof Integration) {
                DB::rollBack();
                return ServiceReturn::error(__('messages.meta_business.error.integration_not_found'), data: ['retryable' => false]);
            }

            $mapping = (array) ($integration->field_mapping ?? []);
            $fields = $this->extractLeadFields($leadData);

            $username = (string) $this->resolveMappedLeadValue($fields, $mapping, 'name', ['full_name', 'name']);
            $phone = $this->normalizePhone(
                (string) $this->resolveMappedLeadValue($fields, $mapping, 'phone', ['phone_number', 'phone', 'phone_no'])
            );
            $email = $this->normalizeEmail(
                (string) $this->resolveMappedLeadValue($fields, $mapping, 'email', ['email', 'email_address'])
            );

            if ($phone) {
                $isBlacklisted = DB::table('black_list')
                    ->join('customers', 'black_list.customer_id', '=', 'customers.id')
                    ->where('customers.phone', $phone)
                    ->where('customers.organization_id', $integration->organization_id)
                    ->exists();

                if ($isBlacklisted) {
                    DB::commit();

                    return ServiceReturn::error(
                        __('messages.meta_business.error.customer_blacklisted'),
                        data: ['retryable' => false]
                    );
                }
            }

            $existingCustomer = $this->customerRepository->query()
                ->where('organization_id', $integration->organization_id)
                ->where(function ($query) use ($phone, $email) {
                    if ($phone) {
                        $query->where('phone', $phone);
                    }

                    if ($email && !$phone) {
                        $query->orWhere('email', $email);
                    }
                })
                ->first();

            $payload = [
                'organization_id' => $integration->organization_id,
                'username' => $username ?: ($email ?: $phone ?: 'Lead'),
                'phone' => $phone ?: null,
                'email' => $email ?: null,
                'address' => null,
                'assigned_staff_id' => null,
                'note' => null,
                'source' => IntegrationType::FACEBOOK_ADS->value,
                'source_detail' => $this->formatSourceDetail($leadData, $marketingData),
                'source_id' => (string) ($leadData['id'] ?? ''),
                'interaction_status' => InteractionStatus::FIRST_CALL->value,
                'product_id' => $productId,
            ];

            if ($existingCustomer) {
                $hasCompletedOrder = $existingCustomer->orders()
                    ->where('status', OrderStatus::COMPLETED->value)
                    ->exists();

                $payload['customer_type'] = $hasCompletedOrder
                    ? CustomerType::OLD_CUSTOMER->value
                    : CustomerType::NEW_DUPLICATE->value;
            } else {
                $payload['customer_type'] = CustomerType::NEW->value;
            }

            $customer = $this->customerRepository->create($payload);

            if ($customer) {
                $staff = $this->leadDistributionService->assignLead($customer, $productId, $integration->organization_id);
                if ($staff) {
                    $customer->update(['assigned_staff_id' => $staff->id]);
                }
            }

            DB::commit();

            return ServiceReturn::success([
                'customer' => $customer,
                'customer_type' => CustomerType::getLabel($customer->customer_type),
            ]);
        } catch (\Throwable $throwable) {
            DB::rollBack();

            Log::error('Store Facebook lead error', [
                'integration_id' => $integrationId,
                'error' => $throwable->getMessage(),
            ]);

            return ServiceReturn::error(__('messages.meta_business.error.store_lead_failed'), $throwable);
        }
    }

    protected function formatSourceDetail(array $leadData, array $marketingData): string
    {
        $details = [
            'channel' => 'facebook_ads',
            'type' => 'leadgen',
        ];

        if (!empty($leadData['page_id'])) {
            $details['page_id'] = (string) $leadData['page_id'];
        }
        if (!empty($leadData['form_id'])) {
            $details['form_id'] = (string) $leadData['form_id'];
        }
        if (!empty($leadData['id'])) {
            $details['lead_id'] = (string) $leadData['id'];
        }
        if (!empty($marketingData['campaign_name'])) {
            $details['campaign'] = $marketingData['campaign_name'];
        }
        if (!empty($marketingData['ad_name'])) {
            $details['ad'] = $marketingData['ad_name'];
        }
        if (!empty($marketingData['adset_name'])) {
            $details['adset'] = $marketingData['adset_name'];
        }

        return json_encode($details, JSON_UNESCAPED_UNICODE);
    }

    protected function hasLeadgenPermission(array $tasks): bool
    {
        return in_array('MANAGE', $tasks, true)
            && (in_array('ADVERTISE', $tasks, true) || in_array('CREATE_CONTENT', $tasks, true));
    }

    protected function extractLeadFields(array $leadData): array
    {
        return $this->facebookLeadMapper->extractLeadFields($leadData);
    }

    protected function resolveMappedLeadValue(array $fields, array $mapping, string $targetField, array $fallbackKeys = []): mixed
    {
        return $this->facebookLeadMapper->resolveMappedLeadValue($fields, $mapping, $targetField, $fallbackKeys);
    }

    protected function normalizePhone(string $phone): string
    {
        return $this->facebookLeadMapper->normalizePhone($phone);
    }

    protected function normalizeEmail(string $email): string
    {
        return $this->facebookLeadMapper->normalizeEmail($email);
    }

    protected function subscribePageToWebhook(IntegrationEntity $entity, string $pageAccessToken): bool
    {
        try {
            $response = Http::post($this->graphUrl("/{$entity->external_id}/subscribed_apps"), [
                'subscribed_fields' => 'leadgen',
                'access_token' => $pageAccessToken,
            ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function unsubscribePageFromWebhook(IntegrationEntity $entity, string $pageAccessToken): bool
    {
        try {
            $response = Http::delete($this->graphUrl("/{$entity->external_id}/subscribed_apps"), [
                'access_token' => $pageAccessToken,
            ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function buildPageMetadata(?IntegrationEntity $existing, array $pageData): array
    {
        $existingMetadata = $existing?->metadata ?? [];

        return [
            'category' => $pageData['category'] ?? null,
            'picture' => $pageData['picture']['data']['url'] ?? null,
            'tasks' => $pageData['tasks'] ?? [],
            'default_product_id' => $existingMetadata['default_product_id'] ?? null,
            'webhook_subscribed' => false,
            'webhook_subscribed_at' => null,
        ];
    }

    protected function refreshIntegrationAggregateStatus(Integration $integration): void
    {
        $approved = $integration->approvedFacebookPages()->count();
        $pending = $integration->pendingFacebookPages()->count();
        $rejected = $integration->facebookPages()->where('status', FacebookConnectionStatus::REJECTED->value)->count();

        if ($approved > 0) {
            $integration->update([
                'status' => IntegrationStatus::CONNECTED->value,
                'status_message' => __('messages.meta_business.connected_successfully'),
            ]);

            return;
        }

        if ($pending > 0) {
            $integration->update([
                'status' => IntegrationStatus::PENDING->value,
                'status_message' => __('messages.meta_business.pending_approval'),
            ]);

            return;
        }

        if ($rejected > 0) {
            $integration->update([
                'status' => IntegrationStatus::ERROR->value,
                'status_message' => __('messages.meta_business.rejected'),
            ]);

            return;
        }

        $integration->update([
            'status' => IntegrationStatus::PENDING->value,
            'status_message' => __('messages.meta_business.pending_approval'),
        ]);
    }

    protected function serializeEntity(IntegrationEntity $entity): array
    {
        return [
            'id' => $entity->id,
            'page_id' => $entity->external_id,
            'page_name' => $entity->name,
            'status' => $entity->status,
            'status_label' => FacebookConnectionStatus::tryFrom((int) $entity->status)?->label(),
            'approved_at' => $entity->approved_at?->toDateTimeString(),
            'rejected_at' => $entity->rejected_at?->toDateTimeString(),
            'webhook_subscribed_at' => $entity->webhook_subscribed_at?->toDateTimeString(),
            'last_lead_received_at' => $entity->last_lead_received_at?->toDateTimeString(),
            'status_reason' => $entity->status_reason,
            'metadata' => $entity->metadata ?? [],
        ];
    }

    protected function canApprove(User $actor, Integration $integration): bool
    {
        if ((int) $actor->role === UserRole::SUPER_ADMIN->value) {
            return true;
        }

        return (int) $actor->role === UserRole::ADMIN->value
            && (int) $actor->organization_id === (int) $integration->organization_id;
    }

    protected function graphUrl(string $path): string
    {
        $version = (string) config('services.facebook.graph_api_version', 'v25.0');

        return rtrim(self::GRAPH_API_URL, '/') . '/' . trim($version, '/') . '/' . ltrim($path, '/');
    }
}
