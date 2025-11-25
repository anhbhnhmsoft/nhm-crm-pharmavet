<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\IntegrationEntity;
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
                'type' => \App\Common\Constants\Marketing\IntegrationEntityType::PAGE_META->value,
                'external_id' => $externalId,
            ],
            $attributes
        );
        return $entity;
    }
}
