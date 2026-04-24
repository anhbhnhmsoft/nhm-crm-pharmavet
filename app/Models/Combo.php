<?php

namespace App\Models;

use App\Common\Constants\Product\StatusCombo;
use App\Core\GenerateId\GenerateIdSnowflake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Combo extends Model
{
    use HasFactory, SoftDeletes, GenerateIdSnowflake;

    protected $table = 'combos';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'total_product',
        'total_cost',
        'total_combo_price',
        'status',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_cost' => 'decimal:2',
        'total_combo_price' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    protected static function booted()
    {
        static::saving(function (Combo $combo) {
            $combo->calculateTotals();
            $combo->syncStatusFromSchedule();
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'combo_product')
            ->withPivot(['quantity', 'price'])
            ->withTimestamps();
    }

    public function productsPivot(): HasMany
    {
        return $this->hasMany(ComboProduct::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function recalculateTotals(): void
    {
        $this->load('productsPivot.product');
        $this->calculateTotals();
        $this->saveQuietly();
    }

    public function calculateTotals(): void
    {
        $this->loadMissing('productsPivot.product');

        $totalProduct = 0;
        $totalCost = 0;
        $totalComboPrice = 0;

        foreach ($this->productsPivot as $pivot) {
            $product = $pivot->product;
            if (! $product) {
                continue;
            }

            $totalProduct += $pivot->quantity;
            $totalCost += ($product->cost_price ?? 0) * $pivot->quantity;
            $totalComboPrice += ($pivot->price ?? 0) * $pivot->quantity;
        }

        $this->total_product = $totalProduct;
        $this->total_cost = $totalCost;
        $this->total_combo_price = $totalComboPrice;
    }

    public function syncStatusFromSchedule(?Carbon $now = null): void
    {
        $this->status = $this->resolveStatusFromSchedule($now)->value;
    }

    public function resolveStatusFromSchedule(?Carbon $now = null): StatusCombo
    {
        $currentTime = ($now ?? now())->copy();
        $startDate = $this->start_date ? Carbon::parse($this->start_date) : null;
        $endDate = $this->end_date ? Carbon::parse($this->end_date) : null;

        if ($startDate && $currentTime->lt($startDate)) {
            return StatusCombo::UPCOMING;
        }

        if ($endDate && $currentTime->gt($endDate)) {
            return StatusCombo::EXPIRED;
        }

        return StatusCombo::ACTIVE;
    }

    public function getOriginalSaleTotalAttribute(): float
    {
        $this->loadMissing('productsPivot.product');

        return (float) $this->productsPivot->sum(function (ComboProduct $pivot) {
            return ((float) ($pivot->product?->sale_price ?? 0)) * ((int) ($pivot->quantity ?? 0));
        });
    }

    public function getDiscountPercentageAttribute(): float
    {
        $originalSaleTotal = $this->original_sale_total;

        if ($originalSaleTotal <= 0) {
            return 0;
        }

        $discount = (($originalSaleTotal - (float) $this->total_combo_price) / $originalSaleTotal) * 100;

        return round(max($discount, 0), 2);
    }
}
