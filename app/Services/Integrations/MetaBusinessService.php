<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use App\Models\IntegrationEntity;
use App\Common\Constants\Marketing\IntegrationTokenType;
use App\Common\Constants\Marketing\IntegrationEntityType;
use App\Common\Constants\StatusConnect;
use App\Core\ServiceReturn;
use App\Repositories\IntegrationTokenRepository;
use App\Repositories\IntegrationEntityRepository;
use App\Repositories\CustomerRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use FacebookAds\Api;
use FacebookAds\Object\Lead;
use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\Order\OrderStatus;
use App\Repositories\IntegrationRepository;
use App\Repositories\LeadDistributionConfigRepository;
use App\Services\LeadDistributionService;
use Illuminate\Support\Facades\DB;

class MetaBusinessService
{
    const GRAPH_API_VERSION = 'v24.0';
    const GRAPH_API_URL = 'https://graph.facebook.com/';

    protected ?Api $api = null;


    public function __construct(
        protected CustomerRepository $customerRepository,
        protected IntegrationRepository $integrationRepository,
        protected IntegrationTokenRepository $integrationTokenRepository,
        protected IntegrationEntityRepository $integrationEntityRepository,
        protected LeadDistributionConfigRepository $leadDistributionConfigRepository,
        protected LeadDistributionService $leadDistributionService
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
        $driver = Socialite::driver('facebook')
            ->scopes($this->getRequiredScopes())
            ->stateless();

        if ($state) {
            $driver = $driver->with(['state' => $state]);
        }

        return $driver->redirect()->getTargetUrl();
    }

    /**
     * Get required Facebook permissions
     */
    protected function getRequiredScopes(): array
    {
        return [
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_metadata',
            'leads_retrieval',
            'business_management',
        ];
    }

    /**
     * Handle Facebook OAuth callback
     */
    public function handleCallback(Integration $integration): ServiceReturn
    {
        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();
            Log::info('Facebook callback', [
                'integration_id' => $integration->id,
                'facebook_user_id' => $facebookUser->getId(),
            ]);
            // Exchange for long-lived token
            $longLivedToken = $this->exchangeLongLivedToken($facebookUser->token);

            if (!$longLivedToken) {
                throw new \Exception(__('messages.meta_business.error.exchange_token_failed'));
            }

            // Save user token
            $this->integrationTokenRepository->upsertUserToken(
                [
                    'integration_id' => $integration->id,
                    'entity_id' => null,
                    'token' => $longLivedToken['access_token'],
                    'scopes' => $facebookUser->approvedScopes ?? [],
                    'expires_at' => now()->addSeconds($longLivedToken['expires_in'] ?? 5184000),
                    'status' => StatusConnect::CONNECTED->value,
                ]
            );
            // Fetch and save pages
            $syncResult = $this->syncPages($integration);
            if ($syncResult->isError()) {
                throw new \RuntimeException($syncResult->getMessage());
            }
            $syncedPages = (int) data_get($syncResult->getData(), 'count', 0);

            // Update integration status
            $integration->update([
                'status' => StatusConnect::CONNECTED->value, // connected
                'status_message' => __('messages.meta_business.connected_successfully'),
                'last_sync_at' => now(),
            ]);

            Log::info('Facebook integration connected', [
                'integration_id' => $integration->id,
                'user_id' => $facebookUser->getId(),
            ]);

            return ServiceReturn::success(
                ['count' => $syncedPages],
                'Meta Business ' . __('messages.meta_business.success.connected')
            );
        } catch (\Exception $e) {
            Log::error('Facebook callback failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            $integration->update([
                'status' => StatusConnect::ERROR->value, // connected
                'status_message' => $e->getMessage(),
            ]);

            return ServiceReturn::error('Meta Business ' . __('messages.meta_business.error.callback_failed'), $e);
        }
    }

    /**
     * Exchange short-lived token for long-lived token
     * @see https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived/
     */
    protected function exchangeLongLivedToken(string $shortToken): ?array
    {
        try {
            $response = Http::get(self::GRAPH_API_URL . self::GRAPH_API_VERSION . '/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.facebook.client_id'),
                'client_secret' => config('services.facebook.client_secret'),
                'fb_exchange_token' => $shortToken,
            ]);
            Log::info('Facebook exchange token response', [
                'response' => $response->body(),
            ]);
            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to exchange long-lived token', [
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception exchanging token', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Sync Facebook Pages
     * @see https://developers.facebook.com/docs/pages/managing/
     */
    public function syncPages(Integration $integration): ServiceReturn
    {
        try {
            $userToken = $this->integrationTokenRepository->getUserLongLivedToken($integration->id);

            if (!$userToken) {
                throw new \Exception(__('messages.meta_business.error.no_user_token'));
            }
            $fullUrl = self::GRAPH_API_URL . self::GRAPH_API_VERSION . '/me/accounts';
            Log::info('Facebook API URL:', ['url' => $fullUrl]);
            // Fetch pages from Facebook
            $response = Http::get($fullUrl, [
                'access_token' => $userToken->token,
                'fields' => 'id,name,category,access_token,picture{url},tasks',
            ]);

            if (!$response->successful()) {
                throw new \Exception(__('messages.meta_business.error.fetch_pages_failed', ['error' => $response->body()]));
            }
            Log::info('Meta Business fetch pages', [
                'response' => $response->body(),
            ]);

            $pages = $response->json('data', []);
            $syncedCount = 0;
            $subscribedCount = 0;


            foreach ($pages as $pageData) {
                // Only sync pages with lead retrieval capabilities.
                $tasks = $pageData['tasks'] ?? [];
                if (!$this->hasLeadgenPermission($tasks)) {
                    continue;
                }
                $isSubscribed = $this->savePageEntity($integration, $pageData);
                if ($isSubscribed !== null) {
                    $syncedCount++;
                    if ($isSubscribed) {
                        $subscribedCount++;
                    }
                }
            }

            $integration->update([
                'status' => StatusConnect::CONNECTED->value,
                'status_message' => __('messages.meta_business.success.pages_synced'),
                'last_sync_at' => now(),
            ]);

            Log::info('Pages synced successfully', [
                'integration_id' => $integration->id,
                'count' => $syncedCount,
            ]);

            return ServiceReturn::success([
                'count' => $syncedCount,
                'subscribed_count' => $subscribedCount,
            ], 'Meta Business ' . __('messages.meta_business.success.pages_synced'));
        } catch (\Exception $e) {
            Log::error('Failed to sync pages', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            $integration->update([
                'status' => StatusConnect::ERROR->value,
                'status_message' => $e->getMessage(),
            ]);

            return ServiceReturn::error($e->getMessage(), $e);
        }
    }

    /**
     * Save page entity and subscribe
     */
    protected function savePageEntity(Integration $integration, array $pageData): ?bool
    {
        try {
            $tasks = $pageData['tasks'] ?? [];

            // Check for existing entity to preserve metadata
            $existingEntity = $this->integrationEntityRepository->query()
                ->where('integration_id', $integration->id)
                ->where('type', IntegrationEntityType::PAGE_META->value)
                ->where('external_id', $pageData['id'])
                ->first();

            $metadata = [
                'category' => $pageData['category'] ?? null,
                'picture' => $pageData['picture']['data']['url'] ?? null,
                'tasks' => $tasks,
                'default_product_id' => null,
                'webhook_subscribed' => false,
                'webhook_subscribed_at' => null,
                'webhook_error' => null,
            ];

            if ($existingEntity) {
                $existingMetadata = $existingEntity->metadata ?? [];

                // Merge existing metadata with new metadata
                // We prioritize existing configuration (like default_product_id) over defaults
                $metadata['default_product_id'] = $existingMetadata['default_product_id'] ?? null;
                $metadata['webhook_subscribed'] = $existingMetadata['webhook_subscribed'] ?? false;
                $metadata['webhook_subscribed_at'] = $existingMetadata['webhook_subscribed_at'] ?? null;
                $metadata['webhook_error'] = $existingMetadata['webhook_error'] ?? null;
            }

            // Save page entity
            $entity = $this->integrationEntityRepository->upsertPageEntity(
                $integration->id,
                $pageData['id'],
                [
                    'name' => $pageData['name'],
                    'metadata' => $metadata,
                    'status' => StatusConnect::CONNECTED->value,
                    'connected_at' => now(),
                ]
            );

            // Save page access token
            $this->integrationTokenRepository->upsertPageAccessToken(
                [
                    'integration_id' => $integration->id,
                    'entity_id' => $entity->id,
                    'token' => $pageData['access_token'],
                    'scopes' => $pageData['access_token_scopes'] ?? [],
                    'expires_at' => now()->addSeconds($pageData['access_token_expires_in'] ?? 5184000),
                    'status' => StatusConnect::CONNECTED->value,
                ]
            );

            // Subscribe page to webhook
            $isSubscribed = $this->subscribePageToWebhook($entity, $pageData['access_token']);

            $metadata = $entity->metadata ?? [];
            $metadata['webhook_subscribed'] = $isSubscribed;
            $metadata['webhook_subscribed_at'] = $isSubscribed ? now()->toDateTimeString() : null;
            $metadata['webhook_error'] = $isSubscribed ? null : __('messages.meta_business.error.webhook_subscribe_failed');

            $entity->update([
                'metadata' => $metadata,
                'status' => $isSubscribed ? StatusConnect::CONNECTED->value : StatusConnect::ERROR->value,
            ]);

            return $isSubscribed;
        } catch (\Exception $e) {
            Log::error('Failed to save page entity', [
                'integration_id' => $integration->id,
                'page_id' => $pageData['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Fetch lead data from Facebook
     * @see https://developers.facebook.com/docs/marketing-api/guides/lead-ads/retrieving/
     */
    public function fetchLead(string $leadId, string $pageAccessToken): ServiceReturn
    {
        try {

            /**
             * @var \FacebookAds\Api $api
             */
            $api = $this->api;
            if ($api) {
                $api->setDefaultAccessToken($pageAccessToken);
                $lead = new Lead($leadId);
                $data = $lead->read(['id', 'created_time', 'field_data', 'form_id', 'ad_id', 'campaign_id', 'adset_id'])->exportAllData();

                $fields = [];
                foreach ($data['field_data'] ?? [] as $field) {
                    $name = $field['name'] ?? null;
                    $values = $field['values'] ?? [];
                    if ($name && !empty($values)) {
                        $fields[$name] = is_array($values) ? $values[0] : $values;
                    }
                }

                return ServiceReturn::success([
                    'id' => $data['id'] ?? null,
                    'form_id' => $data['form_id'] ?? null,
                    'ad_id' => $data['ad_id'] ?? null,
                    'campaign_id' => $data['campaign_id'] ?? null,
                    'adset_id' => $data['adset_id'] ?? null,
                    'created_time' => $data['created_time'] ?? null,
                    'fields' => $fields,
                ]);
            }

            $response = Http::get(
                self::GRAPH_API_URL . self::GRAPH_API_VERSION . "/{$leadId}",
                [
                    'access_token' => $pageAccessToken,
                    'fields' => 'id,created_time,field_data,form_id,ad_id,campaign_id,adset_id',
                ]
            );

            if (!$response->successful()) {
                Log::error('Failed to fetch lead', [
                    'lead_id' => $leadId,
                    'response' => $response->body(),
                ]);
                return ServiceReturn::error(__('messages.meta_business.error.fetch_lead_failed'));
            }

            $data = $response->json();

            $fields = [];
            foreach ($data['field_data'] ?? [] as $field) {
                $name = $field['name'] ?? null;
                $values = $field['values'] ?? [];
                if ($name && !empty($values)) {
                    $fields[$name] = is_array($values) ? $values[0] : $values;
                }
            }

            return ServiceReturn::success([
                'id' => $data['id'] ?? null,
                'form_id' => $data['form_id'] ?? null,
                'ad_id' => $data['ad_id'] ?? null,
                'campaign_id' => $data['campaign_id'] ?? null,
                'adset_id' => $data['adset_id'] ?? null,
                'created_time' => $data['created_time'] ?? null,
                'fields' => $fields,
            ]);
        } catch (\Exception $e) {
            Log::error('Exception fetching lead', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
            return ServiceReturn::error(__('messages.meta_business.error.fetch_lead_failed'), $e);
        }
    }

    /**
     * Test connection
     */
    public function testConnection(Integration $integration): ServiceReturn
    {
        try {
            $userToken = $integration->tokens()
                ->where('type', IntegrationTokenType::USER_LONG_LIVED_TOKEN->value)
                ->where('status', StatusConnect::CONNECTED->value)
                ->first();

            if (!$userToken) {
                return ServiceReturn::error(__('messages.meta_business.error.no_user_token'));
            }

            $response = Http::get(
                self::GRAPH_API_URL . self::GRAPH_API_VERSION . '/me',
                [
                    'access_token' => $userToken->token,
                    'fields' => 'id,name',
                ]
            );

            return ServiceReturn::success(['connected' => $response->successful()]);
        } catch (\Exception $e) {
            Log::error('Connection test failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            return ServiceReturn::error(__('messages.meta_business.error.connection_test_failed'), $e);
        }
    }

    /**
     * Disconnect integration
     */
    public function disconnect(Integration $integration): ServiceReturn
    {
        try {
            // Unsubscribe all pages
            foreach ($integration->entities as $entity) {
                if ($entity->type === IntegrationEntityType::PAGE_META->value) {
                    $pageToken = $this->integrationTokenRepository->getPageAccessTokenByEntity($integration->id, $entity->id);
                    if ($pageToken) {
                        $this->unsubscribePageFromWebhook($entity, $pageToken->token);
                    }
                }
            }

            // Delete all tokens
            $integration->tokens()->delete();

            // Deactivate all entities
            $integration->entities()->update(['status' => StatusConnect::PENDING->value]);

            // Update integration status
            $integration->update([
                'status' => StatusConnect::PENDING->value, // pending
                'status_message' => __('messages.meta_business.disconnected'),
            ]);

            Log::info('Integration disconnected', [
                'integration_id' => $integration->id,
            ]);

            return ServiceReturn::success(null, 'Meta Business ' . __('messages.meta_business.success.disconnected'));
        } catch (\Exception $e) {
            Log::error('Failed to disconnect integration', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            $integration->update([
                'status' => StatusConnect::ERROR->value,
                'status_message' => $e->getMessage(),
            ]);
            return ServiceReturn::error('Meta Business ' . __('messages.meta_business.error.disconnect_failed'), $e);
        }
    }

    /**
     * Unsubscribe page from webhook
     * @see https://developers.facebook.com/docs/graph-api/reference/page/subscribed_apps/
     */
    public function verifyIntegrationByWebhookToken(string $token): ServiceReturn
    {
        try {
            $integration = $this->integrationRepository->findByWebhookToken($token);
            if (!$integration) {
                return ServiceReturn::error(__('messages.meta_business.error.invalid_verify_token'));
            }
            return ServiceReturn::success($integration);
        } catch (\Throwable $thr) {
            return ServiceReturn::error(__('messages.meta_business.error.verify_token_error'), $thr);
        }
    }

    /**
     * Subscribe page to webhook (leadgen)
     * @see https://developers.facebook.com/docs/graph-api/reference/page/subscribed_apps/
     */
    protected function subscribePageToWebhook(IntegrationEntity $entity, string $pageAccessToken): bool
    {
        try {
            $response = Http::post(
                self::GRAPH_API_URL . self::GRAPH_API_VERSION . "/{$entity->external_id}/subscribed_apps",
                [
                    'subscribed_fields' => 'leadgen',
                    'access_token' => $pageAccessToken,
                ]
            );

            if ($response->successful()) {
                Log::info('Page subscribed to webhook', [
                    'entity_id' => $entity->id,
                    'page_id' => $entity->external_id,
                ]);

                return true;
            }

            Log::error('Failed to subscribe page to webhook', [
                'entity_id' => $entity->id,
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception subscribing page', [
                'entity_id' => $entity->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function unsubscribePageFromWebhook(IntegrationEntity $entity, string $pageAccessToken): bool
    {
        try {
            $response = Http::delete(
                self::GRAPH_API_URL . self::GRAPH_API_VERSION . "/{$entity->external_id}/subscribed_apps",
                [
                    'access_token' => $pageAccessToken,
                ]
            );

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to unsubscribe page', [
                'entity_id' => $entity->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function findIntegrationByPageId(string $pageId): ServiceReturn
    {
        try {
            $integration = $this->integrationRepository->findFacebookByPageExternalId($pageId);
            if (!$integration) {
                return ServiceReturn::error(__('messages.meta_business.error.integration_not_found'));
            }
            return ServiceReturn::success($integration);
        } catch (\Throwable $thr) {
            return ServiceReturn::error(__('messages.meta_business.error.lookup_page_error'), $thr);
        }
    }

    public function processLead(int $integrationId, string $pageId, string $leadId): ServiceReturn
    {
        try {

            $entity = $this->integrationEntityRepository->query()
                ->where('integration_id', $integrationId)
                ->where('type', IntegrationEntityType::PAGE_META->value)
                ->where('external_id', $pageId)
                ->first();
            if (!$entity) {
                return ServiceReturn::error(__('messages.meta_business.error.page_entity_not_found'));
            }

            $pageToken = $this->integrationTokenRepository->getPageAccessTokenByEntity($integrationId, $entity->id);
            if (!$pageToken) {
                return ServiceReturn::error(__('messages.meta_business.error.page_token_not_found'));
            }

            $ret = $this->fetchLead($leadId, $pageToken->token);
            if ($ret->isError()) {
                return $ret;
            }

            $productId = $entity->metadata['default_product_id'] ?? null;
            $marketingData = [];

            // Fetch Campaign/Ad info if ad_id exists
            $leadData = $ret->getData();
            $leadData['page_id'] = $pageId;
            if (!empty($leadData['ad_id'])) {
                $userToken = $this->integrationTokenRepository->getUserLongLivedToken($integrationId);
                if ($userToken) {
                    $marketingInfo = $this->fetchAdInfo($leadData['ad_id'], $userToken->token);
                    if ($marketingInfo) {
                        $marketingData = $marketingInfo;
                    }
                }
            }

            $store = $this->storeFacebookLeadByIntegration($integrationId, $leadData, $productId, $marketingData);
            if ($store->isError()) {
                return $store;
            }
            return ServiceReturn::success();
        } catch (\Throwable $thr) {
            return ServiceReturn::error(__('messages.meta_business.error.process_lead_failed'), $thr);
        }
    }

    /**
     * Fetch Ad and Campaign info from Facebook
     * @see https://developers.facebook.com/docs/marketing-api/reference/adgroup
     */
    protected function fetchAdInfo(string $adId, string $userAccessToken): ?array
    {
        try {
            $response = Http::get(self::GRAPH_API_URL . self::GRAPH_API_VERSION . "/{$adId}", [
                'access_token' => $userAccessToken,
                'fields' => 'id,name,campaign{id,name},adset{id,name}',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'ad_id' => $data['id'] ?? null,
                    'ad_name' => $data['name'] ?? null,
                    'campaign_id' => $data['campaign']['id'] ?? null,
                    'campaign_name' => $data['campaign']['name'] ?? null,
                    'adset_id' => $data['adset']['id'] ?? null,
                    'adset_name' => $data['adset']['name'] ?? null,
                ];
            }
            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Ad info', ['ad_id' => $adId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function storeFacebookLeadByIntegration(int $integrationId, array $leadData, ?int $productId = null, array $marketingData = []): ServiceReturn
    {
        DB::beginTransaction();
        try {
            $integration = $this->integrationRepository->find($integrationId);
            if (!$integration) {
                return ServiceReturn::error(__('messages.meta_business.error.integration_not_found'));
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
                    Log::warning('Customer is blacklisted, skipping', [
                        'phone' => $phone,
                        'organization_id' => $integration->organization_id,
                        'source' => 'facebook_lead',
                    ]);
                    DB::commit();
                    return ServiceReturn::error(
                        __('messages.meta_business.error.customer_blacklisted'),
                        data: ['retryable' => false]
                    );
                }
            }

            $existingCustomer = $this->customerRepository->query()
                ->where('organization_id', $integration->organization_id)
                ->where(function ($q) use ($phone, $email) {
                    if ($phone) {
                        $q->where('phone', $phone);
                    }
                    if ($email && !$phone) {
                        $q->orWhere('email', $email);
                    }
                })
                ->first();

            if ($existingCustomer) {
                $hasCompletedOrder = $existingCustomer->orders()
                    ->where('status', OrderStatus::COMPLETED->value)
                    ->exists();

                if ($hasCompletedOrder) {
                    $customer = $this->customerRepository->create([
                        'organization_id' => $integration->organization_id,
                        'username' => $username ?: ($email ?: $phone ?: 'Lead'),
                        'phone' => $phone ?: null,
                        'email' => $email ?: null,
                        'address' => null,
                        'customer_type' => CustomerType::OLD_CUSTOMER->value,
                        'assigned_staff_id' => null,
                        'note' => null,
                        'source' => IntegrationType::FACEBOOK_ADS->value,
                        'source_detail' => $this->formatSourceDetail($leadData, $marketingData),
                        'source_id' => (string) ($leadData['id'] ?? ''),
                        'interaction_status' => InteractionStatus::FIRST_CALL->value,
                        'product_id' => $productId,
                    ]);

                    Log::info('Created OLD_CUSTOMER from Facebook lead', [
                        'customer_id' => $customer->id,
                        'phone' => $phone,
                        'existing_customer_id' => $existingCustomer->id,
                    ]);
                } else {
                    $customer = $this->customerRepository->create([
                        'organization_id' => $integration->organization_id,
                        'username' => $username ?: ($email ?: $phone ?: 'Lead'),
                        'phone' => $phone ?: null,
                        'email' => $email ?: null,
                        'address' => null,
                        'customer_type' => CustomerType::NEW_DUPLICATE->value,
                        'assigned_staff_id' => null,
                        'note' => null,
                        'source' => IntegrationType::FACEBOOK_ADS->value,
                        'source_detail' => $this->formatSourceDetail($leadData, $marketingData),
                        'source_id' => (string) ($leadData['id'] ?? ''),
                        'interaction_status' => InteractionStatus::FIRST_CALL->value,
                        'product_id' => $productId,
                    ]);

                    Log::info('Created NEW_DUPLICATE from Facebook lead', [
                        'customer_id' => $customer->id,
                        'phone' => $phone,
                        'existing_customer_id' => $existingCustomer->id,
                    ]);
                }
            } else {
                $customer = $this->customerRepository->create([
                    'organization_id' => $integration->organization_id,
                    'username' => $username ?: ($email ?: $phone ?: 'Lead'),
                    'phone' => $phone ?: null,
                    'email' => $email ?: null,
                    'address' => null,
                    'customer_type' => CustomerType::NEW->value,
                    'assigned_staff_id' => null,
                    'note' => null,
                    'source' => IntegrationType::FACEBOOK_ADS->value,
                    'source_detail' => $this->formatSourceDetail($leadData, $marketingData),
                    'source_id' => (string) ($leadData['id'] ?? ''),
                    'interaction_status' => InteractionStatus::FIRST_CALL->value,
                    'product_id' => $productId,
                ]);

                Log::info('Created NEW customer from Facebook lead', [
                    'customer_id' => $customer->id,
                    'phone' => $phone,
                ]);
            }

            if ($customer) {
                $staff = $this->leadDistributionService->assignLead($customer, $productId, $integration->organization_id);
                if ($staff) {
                    $customer->update(['assigned_staff_id' => $staff->id]);

                    Log::info('Assigned Facebook lead to staff', [
                        'customer_id' => $customer->id,
                        'staff_id' => $staff->id,
                        'staff_name' => $staff->name,
                    ]);
                }
            }

            DB::commit();

            return ServiceReturn::success([
                'customer' => $customer,
                'customer_type' => CustomerType::getLabel($customer->customer_type),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Store Facebook lead error: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString(),
            ]);
            return ServiceReturn::error('Store Facebook lead error: ' . $th->getMessage());
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

        if (count($details) <= 2) {
            return json_encode($details, JSON_UNESCAPED_UNICODE);
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
        $fields = [];
        $raw = $leadData['field_data'] ?? $leadData['fields'] ?? [];

        foreach ($raw as $key => $item) {
            if (is_string($key) && !is_array($item)) {
                $fields[$key] = $item;
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $name = $item['name'] ?? null;
            if (!is_string($name) || trim($name) === '') {
                continue;
            }

            $value = $item['values'][0] ?? ($item['value'] ?? null);
            $fields[$name] = is_string($value) ? trim($value) : $value;
        }

        return $fields;
    }

    protected function resolveMappedLeadValue(array $fields, array $mapping, string $targetField, array $fallbackKeys = []): mixed
    {
        $targetField = trim($targetField);

        if (isset($mapping[$targetField]) && is_string($mapping[$targetField])) {
            $sourceKey = trim($mapping[$targetField]);
            if ($sourceKey !== '' && array_key_exists($sourceKey, $fields)) {
                return $fields[$sourceKey];
            }
        }

        foreach ($mapping as $source => $target) {
            if (!is_string($source) || !is_string($target)) {
                continue;
            }

            if (trim($target) === $targetField && array_key_exists($source, $fields)) {
                return $fields[$source];
            }
        }

        foreach ($fallbackKeys as $key) {
            if (array_key_exists($key, $fields)) {
                return $fields[$key];
            }
        }

        return null;
    }

    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    protected function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
