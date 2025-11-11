<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadDistributionRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lead_distribution_rules';

    protected $fillable = [
        'config_id',
        'customer_type',
        'staff_type',
        'distribution_method',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(LeadDistributionConfig::class, 'config_id');
    }
}
