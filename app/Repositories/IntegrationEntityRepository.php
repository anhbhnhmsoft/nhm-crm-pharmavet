<?php

namespace App\Repositories;

use App\Common\Constants\Marketing\FacebookConnectionStatus;
use App\Common\Constants\Marketing\IntegrationEntityType;
use App\Core\BaseRepository;
use App\Models\IntegrationEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class IntegrationEntityRepository extends BaseRepository
{
    public function model(): Model
    {
        return new IntegrationEntity();
    }

    /**
     * @param int $integrationId
     * @param string $externalId
     * @param array $attributes
     * @return IntegrationEntity
     */
    public function upsertPageEntity(int $integrationId, string $externalId, array $attributes): IntegrationEntity
    {
        $entity = $this->query()->updateOrCreate(
            [
                'integration_id' => $integrationId,
                'type' => IntegrationEntityType::PAGE_META->value,
                'external_id' => $externalId,
            ],
            $attributes
        );
        return $entity;
    }

    public function upsertPendingFacebookPage(int $integrationId, array $pageData, array $metadata = []): IntegrationEntity
    {
        return $this->upsertPageEntity(
            $integrationId,
            (string) $pageData['id'],
            [
                'name' => $pageData['name'] ?? (string) $pageData['id'],
                'metadata' => $metadata,
                'status' => FacebookConnectionStatus::PENDING->value,
                'connected_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'disconnected_at' => null,
                'status_reason' => null,
            ]
        );
    }

    public function approvePages(int $integrationId, array $pageIds, User $actor, array $attributes = []): int
    {
        $query = $this->query()
            ->where('integration_id', $integrationId)
            ->where('type', IntegrationEntityType::PAGE_META->value);

        if ($pageIds !== []) {
            $query->whereIn('external_id', $pageIds);
        }

        return $query->update(array_merge([
            'status' => FacebookConnectionStatus::APPROVED->value,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'status_reason' => null,
            'disconnected_at' => null,
        ], $attributes));
    }

    public function rejectPages(int $integrationId, array $pageIds, User $actor, ?string $reason = null): int
    {
        $query = $this->query()
            ->where('integration_id', $integrationId)
            ->where('type', IntegrationEntityType::PAGE_META->value);

        if ($pageIds !== []) {
            $query->whereIn('external_id', $pageIds);
        }

        return $query->update([
            'status' => FacebookConnectionStatus::REJECTED->value,
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
            'status_reason' => $reason,
        ]);
    }

    public function findApprovedFacebookPageByExternalId(string $pageId): ?IntegrationEntity
    {
        return $this->query()
            ->where('type', IntegrationEntityType::PAGE_META->value)
            ->where('external_id', $pageId)
            ->where('status', FacebookConnectionStatus::APPROVED->value)
            ->first();
    }
}
