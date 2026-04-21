<?php

namespace App\Models;

use App\Common\Constants\Marketing\FacebookConnectionStatus;
use App\Common\Constants\Marketing\IntegrationEntityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Integration extends Model
{
    use SoftDeletes;

    protected $table = 'integrations';

    protected $fillable = [
        'organization_id',
        'name',
        'status',
        'status_message',
        'last_sync_at',
        'type',
        'config',
        'field_mapping',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'config' => 'array',
        'field_mapping' => 'array',
        'last_sync_at' => 'datetime',
    ];

    public function entities()
    {
        return $this->hasMany(IntegrationEntity::class);
    }

    public function tokens()
    {
        return $this->hasMany(IntegrationToken::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function facebookPages()
    {
        return $this->entities()->where('type', IntegrationEntityType::PAGE_META->value);
    }

    public function pendingFacebookPages()
    {
        return $this->facebookPages()->where('status', FacebookConnectionStatus::PENDING->value);
    }

    public function approvedFacebookPages()
    {
        return $this->facebookPages()->where('status', FacebookConnectionStatus::APPROVED->value);
    }
}
