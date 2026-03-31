<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingAlertLog extends Model
{
    protected $fillable = [
        'organization_id',
        'alert_type',
        'severity',
        'channel',
        'campaign',
        'payload_json',
        'triggered_at',
        'resolved_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'triggered_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
