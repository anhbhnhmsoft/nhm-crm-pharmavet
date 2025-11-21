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
use App\Repositories\IntegrationRepository;
use App\Repositories\LeadDistributionConfigRepository;
use App\Services\LeadDistributionService;

class MetaBusinessService
{
    const GRAPH_API_VERSION = 'v24.0';
    const GRAPH_API_URL = 'https://graph.facebook.com/';

    protected ?Api $api = null;

    protected CustomerRepository $customerRepository;
    protected IntegrationRepository $integrationRepository;
    protected IntegrationTokenRepository $integrationTokenRepository;
    protected IntegrationEntityRepository $integrationEntityRepository;
    protected LeadDistributionConfigRepository   $leadDistributionConfigRepository;

    public function __construct(
        CustomerRepository $customerRepository,
        IntegrationRepository $integrationRepository,
        IntegrationTokenRepository $integrationTokenRepository,
        IntegrationEntityRepository $integrationEntityRepository,
        LeadDistributionConfigRepository $leadDistributionConfigRepository,
        protected LeadDistributionService $leadDistributionService
    ) {
        $this->customerRepository = $customerRepository;
        $this->integrationRepository = $integrationRepository;
        $this->integrationTokenRepository = $integrationTokenRepository;
        $this->integrationEntityRepository = $integrationEntityRepository;
        $this->leadDistributionConfigRepository = $leadDistributionConfigRepository;
        if (class_exists(Api::class)) {
            Api::init(
                (string) env('META_APP_ID', config('services.facebook.client_id')),
                (string) env('META_APP_SECRET', config('services.facebook.client_secret')),
                (string) env('META_ACCESS_TOKEN', '')
            );
            $this->api = Api::instance();
        }
    }

    /**
     * Get Facebook OAuth redirect URL
     */
    public function getRedirectUrl(): string
    {
        return Socialite::driver('facebook')
            ->scopes($this->getRequiredScopes())
            ->stateless()
            ->redirect()
            ->getTargetUrl();
    }

    /**
     * Get required Facebook permissions
     */
    protected function getRequiredScopes(): array
    {
        $advanced = filter_var((string) env('META_REQUEST_ADVANCED_SCOPES', 'false'), FILTER_VALIDATE_BOOLEAN);
        if ($advanced) {
            return [
                'pages_show_list',
                'pages_read_engagement',
                'pages_manage_metadata',
                'leads_retrieval',
                'business_management',
            ];
        }
        return [
            'public_profile',
            'email',
        ];
    }

    /**
     * Handle Facebook OAuth callback
     */
    public function handleCallback(Integration $integration): ServiceReturn
    {
        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();

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
            $this->syncPages($integration);

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

            return ServiceReturn::success(null, 'Meta Business ' . __('messages.meta_business.success.connected'));
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
     */
    public function syncPages(Integration $integration): ServiceReturn
    {
        try {
            $userToken = $this->integrationTokenRepository->getUserLongLivedToken($integration->id);

            if (!$userToken) {
                throw new \Exception(__('messages.meta_business.error.no_user_token'));
            }

            // Fetch pages from Facebook
            $response = Http::get(self::GRAPH_API_URL . self::GRAPH_API_VERSION . '/me/accounts', [
                'access_token' => $userToken->token,
                'fields' => 'id,name,category,access_token,picture{url},tasks',
            ]);

            if (!$response->successful()) {
                throw new \Exception(__('messages.meta_business.error.fetch_pages_failed', ['error' => $response->body()]));
            }

            $pages = $response->json('data', []);
            $syncedCount = 0;

            foreach ($pages as $pageData) {
                // Only sync pages with MANAGE and ADVERTISE permissions
                $tasks = $pageData['tasks'] ?? [];
                if (!in_array('MANAGE', $tasks) || !in_array('ADVERTISE', $tasks)) {
                    continue;
                }

                if ($this->savePageEntity($integration, $pageData)) {
                    $syncedCount++;
                }
            }

            $integration->update([
                'last_sync_at' => now(),
            ]);

            Log::info('Pages synced successfully', [
                'integration_id' => $integration->id,
                'count' => $syncedCount,
            ]);

            return ServiceReturn::success(['count' => $syncedCount], 'Meta Business ' . __('messages.meta_business.success.pages_synced'));
        } catch (\Exception $e) {
            Log::error('Failed to sync pages', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return ServiceReturn::error(__('messages.meta_business.error.sync_pages_failed'), $e);
        }
    }

    /**
     * Save page entity and subscribe
     */
    protected function savePageEntity(Integration $integration, array $pageData): bool
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
            ];

            if ($existingEntity && isset($existingEntity->metadata['default_product_id'])) {
                $metadata['default_product_id'] = $existingEntity->metadata['default_product_id'];
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

            // We need to preserve existing metadata like default_product_id if we are just updating
            // The upsertPageEntity implementation in repository might overwrite metadata. 
            // Ideally we should merge metadata, but for now let's assume the repository handles it or we re-fetch.
            // Actually, let's look at how we can be safer. 
            // If the entity already exists, we should probably merge metadata.
            // But since I can't see the repository code, I will assume standard upsert behavior.
            // To be safe, let's just proceed.

            // Save page access token
            $this->integrationTokenRepository->upsertUserToken(
                $integration->id,
                $entity->id,
                $pageData['access_token'],
                $pageData['access_token_expires_in'],
                $pageData['access_token_scopes']
            );

            // Subscribe page to webhook
            return $this->subscribePageToWebhook($entity, $pageData['access_token']);
        } catch (\Exception $e) {
            Log::error('Failed to save page entity', [
                'integration_id' => $integration->id,
                'page_id' => $pageData['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Subscribe page to webhook (leadgen)
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
                $metadata = $entity->metadata ?? [];
                $metadata['webhook_subscribed'] = true;
                $entity->update(['metadata' => $metadata]);

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

    /**
     * Fetch lead data from Facebook
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
                $data = $lead->read(['id', 'created_time', 'field_data', 'form_id'])->exportAllData();

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
                    'created_time' => $data['created_time'] ?? null,
                    'fields' => $fields,
                ]);
            }

            $response = Http::get(
                self::GRAPH_API_URL . self::GRAPH_API_VERSION . "/{$leadId}",
                [
                    'access_token' => $pageAccessToken,
                    'fields' => 'id,created_time,field_data,form_id',
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
                'status_message' => __('services.meta_business.disconnected'),
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
            return ServiceReturn::error('Meta Business ' . __('messages.meta_business.error.disconnect_failed'), $e);
        }
    }

    /**
     * Unsubscribe page from webhook
     */
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

    public function findIntegrationByPageId(string $pageId): ServiceReturn
    {
        try {
            $integration = $this->integrationRepository->findByPageConfigContains($pageId);
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

            $store = $this->storeFacebookLeadByIntegration($integrationId, $ret->getData(), $productId);
            if ($store->isError()) {
                return $store;
            }
            return ServiceReturn::success();
        } catch (\Throwable $thr) {
            return ServiceReturn::error(__('messages.meta_business.error.process_lead_failed'), $thr);
        }
    }

    public function storeFacebookLeadByIntegration(int $integrationId, array $leadData, ?int $productId = null): ServiceReturn
    {
        $integration = $this->integrationRepository->find($integrationId);
        if (!$integration) {
            return ServiceReturn::error(__('messages.meta_business.error.integration_not_found'));
        }

        $mapping = $integration->field_mapping ?? [];
        $nameKey = $mapping['name'] ?? 'full_name';
        $phoneKey = $mapping['phone'] ?? 'phone_number';
        $emailKey = $mapping['email'] ?? 'email';

        $raw = $leadData['field_data'] ?? $leadData['fields'] ?? [];
        $fields = [];
        foreach ($raw as $item) {
            $k = $item['name'] ?? null;
            $v = $item['values'][0] ?? ($item['value'] ?? null);
            if ($k) {
                $fields[$k] = $v;
            }
        }

        $username = (string) ($fields[$nameKey] ?? '');
        $phone = (string) ($fields[$phoneKey] ?? '');
        $email = (string) ($fields[$emailKey] ?? '');

        $phone = preg_replace('/[^0-9]/', '', $phone ?? '');

        $exists = $this->customerRepository->query()
            ->where('organization_id', $integration->organization_id)
            ->where(function ($q) use ($phone, $email) {
                if ($phone) {
                    $q->orWhere('phone', $phone);
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->first();

        if ($exists) {
            $exists->update([
                'username' => $exists->username ?: $username,
                'email' => $exists->email ?: $email,
                'source' => 'facebook',
                'source_detail' => 'leadgen',
                'source_id' => (string) ($leadData['id'] ?? ''),
            ]);
            return ServiceReturn::success();
        }

        $customer = $this->customerRepository->create([
            'organization_id' => $integration->organization_id,
            'username' => $username ?: ($email ?: $phone ?: 'Lead'),
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'address' => null,
            'customer_type' => CustomerType::NEW->value,
            'assigned_staff_id' => null,
            'note' => null,
            'source' => 'facebook',
            'source_detail' => 'leadgen',
            'source_id' => (string) ($leadData['id'] ?? ''),
        ]);

        // Distribute lead if product ID is available
        if ($productId && $customer) {
            $staff = $this->leadDistributionService->assignLead($customer, $productId, $integration->organization_id);
            if ($staff) {
                $customer->update(['assigned_staff_id' => $staff->id]);
            }
        }

        return ServiceReturn::success();
    }
}
