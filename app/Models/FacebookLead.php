<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacebookLead extends Model
{
    protected $fillable = [
        'organization_id',
        'integration_id',
        'entity_id',
        'page_id',
        'leadgen_id',
        'form_id',
        'payload_json',
        'normalized_payload_json',
        'status',
        'retry_count',
        'last_error',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'normalized_payload_json' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    public function entity()
    {
        return $this->belongsTo(IntegrationEntity::class, 'entity_id');
    }
}
