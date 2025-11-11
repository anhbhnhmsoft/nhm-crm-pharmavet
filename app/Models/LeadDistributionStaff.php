<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LeadDistributionStaff extends Pivot
{
    protected $table = 'lead_distribution_staff';

    protected $fillable = [
        'config_id',
        'staff_id',
        'weight',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(LeadDistributionConfig::class, 'config_id');
    }

    public function staff() : BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
