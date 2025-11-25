<?php

namespace App\Repositories;

use App\Common\Constants\Marketing\IntegrationTokenType;
use App\Common\Constants\StatusConnect;
use App\Core\BaseRepository;
use App\Models\IntegrationToken;
use Illuminate\Database\Eloquent\Model;

class IntegrationTokenRepository extends BaseRepository
{
    public function model(): Model
    {
        return new IntegrationToken();
    }

    /**
     * @param int $integrationId
     * @param string $token
     * @param array $scopes
     * @param \DateTimeInterface $expiresAt
     * @param int $status
     * @return void
     */
    public function upsertUserToken(array $data): void
    {
        $this->query()->updateOrCreate(
            [
                'integration_id' => $data['integration_id'],
                'type' => IntegrationTokenType::USER_LONG_LIVED_TOKEN->value,
                'entity_id' => $data['entity_id'],
            ],
            [
                'token' => $data['token'],
                'scopes' => $data['scopes'],
                'expires_at' => $data['expires_at'],
                'status' => $data['status'],
            ]
        );
    }

    /**
     * @param int | string $integrationId
     * @return ?IntegrationToken
     */
    public function getUserLongLivedToken(int | string $integrationId): ?IntegrationToken
    {
        return $this->query()
            ->where('integration_id', $integrationId)
            ->where('type', \App\Common\Constants\Marketing\IntegrationTokenType::USER_LONG_LIVED_TOKEN->value)
            ->where('status', StatusConnect::CONNECTED->value)
            ->first();
    }

    /**
     * @param int | string $integrationId
     * @param int | string $entityId
     * @return ?IntegrationToken
     */
    public function getPageAccessTokenByEntity(int | string $integrationId, int | string $entityId): ?IntegrationToken
    {
        return $this->query()
            ->where('integration_id', $integrationId)
            ->where('entity_id', $entityId)
            ->where('type', \App\Common\Constants\Marketing\IntegrationTokenType::PAGE_ACCESS_TOKEN->value)
            ->where('status', StatusConnect::CONNECTED->value)
            ->first();
    }
}
