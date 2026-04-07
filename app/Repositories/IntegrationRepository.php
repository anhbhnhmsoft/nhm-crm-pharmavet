<?php

namespace App\Repositories;

use App\Common\Constants\Marketing\IntegrationEntityType;
use App\Common\Constants\Marketing\IntegrationType;
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
                    ->where('status', StatusConnect::CONNECTED->value)
                    ->where('external_id', $pageId);
            })
            ->first();
    }
}
