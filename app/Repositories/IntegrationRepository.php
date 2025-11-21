<?php

namespace App\Repositories;

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
}
