<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'organization_id',
        'customer_id',
        'code',
        'status',
        'total_amount',
        'discount',
        'shipping_fee',
        'shipping_method',
        'shipping_address',
        'province_id',
        'district_id',
        'ward_id',
        'note',
        'created_by',
        'updated_by',
        'deposit',
        'cod_fee',
        'ck1',
        'ck2',
        'required_note',
        'provider_shipping',
        'warehouse_id',
        'ghn_order_code',
        'ghn_expected_delivery_time',
        'ghn_service_type_id',
        'ghn_service_name',
        'ghn_payment_type_id',
        'ghn_total_fee',
        'ghn_response',
        'ghn_status',
        'ghn_posted_at',
        'ghn_cancelled_at',
        'weight',
        'length',
        'width',
        'height',
        'insurance_value',
        'coupon',
        'shipping_provider_code',
        'amount_recived_from_customer',
        'amout_support_fee',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'deposit' => 'decimal:2',
        'cod_fee' => 'decimal:2',
        'ck1' => 'decimal:2',
        'ck2' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeShipping($query)
    {
        return $query->where('status', 'shipping');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}
