<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundLockAudit extends Model
{
    protected $table = 'fund_lock_audits';

    protected $fillable = [
        'fund_id',
        'action',
        'is_locked',
        'scope_type',
        'target_user_id',
        'target_team_id',
        'metadata_json',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'metadata_json' => 'array',
        'changed_at' => 'datetime',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
