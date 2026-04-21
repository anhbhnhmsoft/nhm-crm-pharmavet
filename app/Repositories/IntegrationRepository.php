<?php

namespace App\Repositories;

use App\Common\Constants\Marketing\FacebookConnectionStatus;
use App\Common\Constants\Marketing\IntegrationEntityType;
use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\Marketing\IntegrationStatus;
use App\Common\Constants\StatusConnect;
use App\Core\BaseRepository;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Model;

class IntegrationRepository extends BaseRepository
{

    public function model(): Model
    {
        return new Integration();
    }

    /**
     * @param string $token
     * @return ?Integration
     */
    public function findByWebhookToken(string $token): ?Integration
    {
        return $this->query()->where('config->webhook_verify_token', $token)->first();
    }

    /**
     * @param string $pageId
     * @return ?Integration
     */
    public function findByPageConfigContains(string $pageId): ?Integration
    {
        return $this->query()->whereJsonContains('config->page_ids', $pageId)->first();
    }

    /**
     * @param int $id
     * @param int $organizationId
     * @return ?Integration
     */
    public function findByIdAndOrganization(int $id, int $organizationId): ?Integration
    {
        return $this->query()
            ->where('id', $id)
            ->where('organization_id', $organizationId)
            ->first();
    }

    /**
     * Resolve active Facebook integration by connected page external id.
     */
    public function findFacebookByPageExternalId(string $pageId): ?Integration
    {
        return $this->query()
            ->where('type', IntegrationType::FACEBOOK_ADS->value)
            ->where('status', StatusConnect::CONNECTED->value)
            ->whereHas('entities', function ($query) use ($pageId) {
                $query->where('type', IntegrationEntityType::PAGE_META->value)
                    ->where('status', FacebookConnectionStatus::APPROVED->value)
                    ->where('external_id', $pageId);
            })
            ->first();
    }

    public function findLatestFacebookByUser(int $organizationId, int $userId): ?Integration
    {
        return $this->query()
            ->where('organization_id', $organizationId)
            ->where('created_by', $userId)
            ->where('type', IntegrationType::FACEBOOK_ADS->value)
            ->latest('id')
            ->first();
    }

    public function createOrReuseFacebookIntegration(int $organizationId, int $userId, ?string $name = null): Integration
    {
        $integration = $this->findLatestFacebookByUser($organizationId, $userId);

        if ($integration) {
            return $integration;
        }

        return $this->create([
            'organization_id' => $organizationId,
            'type' => IntegrationType::FACEBOOK_ADS->value,
            'name' => $name ?: __('filament.integration.defaults.facebook_name'),
            'status' => IntegrationStatus::PENDING->value,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function findFacebookForUser(int $integrationId, int $organizationId, int $userId): ?Integration
    {
        return $this->query()
            ->where('id', $integrationId)
            ->where('organization_id', $organizationId)
            ->where('created_by', $userId)
            ->where('type', IntegrationType::FACEBOOK_ADS->value)
            ->first();
    }
}
