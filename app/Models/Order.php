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

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $date = $model->created_at ?: now();
            if (AccountingPeriod::isClosed($model->organization_id, $date->month, $date->year)) {
                throw new \Exception(__('accounting.accounting_period.period_closed'));
            }
        });

        static::deleting(function ($model) {
            $date = $model->created_at ?: now();
            if (AccountingPeriod::isClosed($model->organization_id, $date->month, $date->year)) {
                throw new \Exception(__('accounting.accounting_period.period_closed'));
            }
        });
    }

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
        'cod_support_amount',
        'collect_amount',
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
        'ghn_cod_failed_amount',
        'ghn_content',
        'ghn_pick_station_id',
        'ghn_deliver_station_id',
        'ghn_province_id',
        'ghn_district_id',
        'ghn_ward_code',
        'invoice_code',
        'invoice_url',
        'invoice_status',
        'invoice_at',
        'debt_provision_amount',
        'is_written_off',
        'write_off_at',
        'write_off_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'deposit' => 'decimal:2',
        'cod_fee' => 'decimal:2',
        'cod_support_amount' => 'decimal:2',
        'collect_amount' => 'decimal:2',
        'ck1' => 'decimal:2',
        'ck2' => 'decimal:2',
        'ghn_posted_at' => 'datetime',
        'ghn_cancelled_at' => 'datetime',
        'ghn_expected_delivery_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

    public function reconciliation(): HasMany
    {
        return $this->hasMany(Reconciliation::class);
    }

    public function getRemainingDebtAttribute(): float
    {
        return (float)max(0, $this->collect_amount - $this->amount_recived_from_customer);
    }

    public function getDebtAgeAttribute(): int
    {
        if ($this->remaining_debt <= 0 || $this->is_written_off) {
            return 0;
        }

        return (int)now()->diffInDays($this->created_at);
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
