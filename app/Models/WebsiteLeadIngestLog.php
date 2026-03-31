<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteLeadIngestLog extends Model
{
    protected $fillable = [
        'organization_id',
        'integration_id',
        'site_id',
        'request_id',
        'status',
        'payload_json',
        'normalized_json',
        'error_json',
        'received_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'normalized_json' => 'array',
        'error_json' => 'array',
        'received_at' => 'datetime',
    ];
}
