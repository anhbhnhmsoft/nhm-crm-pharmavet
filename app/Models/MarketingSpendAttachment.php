<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingSpendAttachment extends Model
{
    protected $fillable = [
        'marketing_spend_id',
        'version',
        'file_path',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function spend(): BelongsTo
    {
        return $this->belongsTo(MarketingSpend::class, 'marketing_spend_id');
    }
}
