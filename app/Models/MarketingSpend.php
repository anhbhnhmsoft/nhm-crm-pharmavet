<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingSpend extends Model
{
    protected $fillable = [
        'organization_id',
        'date',
        'channel',
        'campaign',
        'actual_spend',
        'fee_amount',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'actual_spend' => 'decimal:2',
        'fee_amount' => 'decimal:2',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(MarketingSpendAttachment::class, 'marketing_spend_id');
    }
}
