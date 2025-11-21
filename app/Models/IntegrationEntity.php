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
    ];

    protected $casts = [
        'metadata' => 'array',
        'connected_at' => 'datetime',
    ];

    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    public function tokens()
    {
        return $this->hasMany(IntegrationToken::class, 'entity_id');
    }
}
