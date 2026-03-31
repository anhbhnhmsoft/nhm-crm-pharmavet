<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacebookEventLog extends Model
{
    protected $fillable = [
        'organization_id',
        'integration_id',
        'entity_id',
        'event_name',
        'event_id',
        'payload_json',
        'hashed_payload_json',
        'status',
        'retry_count',
        'last_error',
        'next_retry_at',
        'processed_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'hashed_payload_json' => 'array',
        'next_retry_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
