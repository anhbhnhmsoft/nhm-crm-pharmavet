<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\FacebookLead;
use Illuminate\Database\Eloquent\Model;

class FacebookLeadRepository extends BaseRepository
{
    public function model(): Model
    {
        return new FacebookLead();
    }

    public function findByLeadgenId(string $leadgenId): ?FacebookLead
    {
        return $this->query()->where('leadgen_id', $leadgenId)->first();
    }

    public function firstOrCreateQueued(array $attributes): FacebookLead
    {
        return $this->query()->firstOrCreate(
            ['leadgen_id' => $attributes['leadgen_id']],
            $attributes + [
                'status' => 'queued',
                'retry_count' => 0,
                'received_at' => now(),
            ]
        );
    }

    public function markProcessed(FacebookLead $facebookLead): void
    {
        $facebookLead->update([
            'status' => 'processed',
            'processed_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markFailed(FacebookLead $facebookLead, string $error): void
    {
        $facebookLead->update([
            'status' => 'failed',
            'retry_count' => (int) $facebookLead->retry_count + 1,
            'last_error' => $error,
        ]);
    }
}
