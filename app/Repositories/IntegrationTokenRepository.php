<?php

namespace App\Repositories;

use App\Common\Constants\Marketing\IntegrationTokenType;
use App\Common\Constants\StatusConnect;
use App\Core\BaseRepository;
use App\Models\IntegrationToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        $this->upsertToken($data, IntegrationTokenType::USER_LONG_LIVED_TOKEN);
    }

    public function upsertPageAccessToken(array $data): void
    {
        $this->upsertToken($data, IntegrationTokenType::PAGE_ACCESS_TOKEN);
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

    public function getActivePageAccessTokenByEntity(int | string $integrationId, int | string $entityId): ?IntegrationToken
    {
        return $this->getPageAccessTokenByEntity($integrationId, $entityId);
    }

    public function markEntityTokensDisconnected(int | string $integrationId, int | string $entityId): int
    {
        return $this->query()
            ->where('integration_id', $integrationId)
            ->where('entity_id', $entityId)
            ->update([
                'status' => StatusConnect::PENDING->value,
            ]);
    }

    public function markExpiredIfNeeded(IntegrationToken $token): IntegrationToken
    {
        if ($token->expires_at instanceof Carbon && $token->expires_at->isPast()) {
            $token->update([
                'status' => StatusConnect::ERROR->value,
            ]);
        }

        return $token->refresh();
    }

    protected function upsertToken(array $data, IntegrationTokenType $type): void
    {
        $this->query()->updateOrCreate(
            [
                'integration_id' => $data['integration_id'],
                'type' => $type->value,
                'entity_id' => $data['entity_id'] ?? null,
            ],
            [
                'token' => $data['token'],
                'scopes' => $data['scopes'] ?? [],
                'expires_at' => $data['expires_at'] ?? null,
                'status' => $data['status'] ?? StatusConnect::CONNECTED->value,
            ]
        );
    }
}
