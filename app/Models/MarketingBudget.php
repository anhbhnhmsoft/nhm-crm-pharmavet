<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingBudget extends Model
{
    protected $fillable = [
        'organization_id',
        'date',
        'channel',
        'campaign',
        'budget_amount',
    ];

    protected $casts = [
        'date' => 'date',
        'budget_amount' => 'decimal:2',
    ];
}
