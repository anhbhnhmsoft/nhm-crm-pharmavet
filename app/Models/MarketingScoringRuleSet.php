<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingScoringRuleSet extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'rules_json',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'rules_json' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}
