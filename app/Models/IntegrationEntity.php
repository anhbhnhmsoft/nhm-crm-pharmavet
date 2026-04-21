<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntegrationEntity extends Model
{
    use SoftDeletes;

    protected $table = 'integration_entities';

    protected $fillable = [
        'integration_id',
        'type',
        'external_id',
        'name',
        'metadata',
        'status',
        'connected_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'webhook_subscribed_at',
        'last_lead_received_at',
        'status_reason',
        'disconnected_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'connected_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'webhook_subscribed_at' => 'datetime',
        'last_lead_received_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    public function tokens()
    {
        return $this->hasMany(IntegrationToken::class, 'entity_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
