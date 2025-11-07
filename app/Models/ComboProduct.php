<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboProduct extends Pivot
{
    protected $table = 'combo_product';

    protected $fillable = [
        'combo_id',
        'product_id',
        'quantity',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    protected static function booted()
    {
        static::saved(fn($pivot) => $pivot->combo?->recalculateTotals());
        static::deleted(fn($pivot) => $pivot->combo?->recalculateTotals());
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
