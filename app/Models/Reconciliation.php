<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reconciliation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'reconciliation_date',
        'order_id',
        'ghn_order_code',
        'cod_amount',
        'shipping_fee',
        'storage_fee',
        'total_fee',
        'exchange_rate_id',
        'converted_amount',
        'status',
        'note',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'cod_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'storage_fee' => 'decimal:2',
        'total_fee' => 'decimal:2',
        'converted_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function exchangeRate(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
