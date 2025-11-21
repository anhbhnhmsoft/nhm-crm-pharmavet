<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntegrationToken extends Model
{
    use SoftDeletes;

    protected $table = 'integration_tokens';

    protected $fillable = [
        'integration_id',
        'entity_id',
        'type',
        'token',
        'scopes',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'type' => 'integer',
        'scopes' => 'array',
        'expires_at' => 'datetime',
    ];

    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    public function entity()
    {
        return $this->belongsTo(IntegrationEntity::class);
    }
}
