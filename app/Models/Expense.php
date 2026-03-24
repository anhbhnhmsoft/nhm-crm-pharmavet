<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $date = $model->expense_date ?: now();
            if (AccountingPeriod::isClosed($model->organization_id, $date->month, $date->year)) {
                throw new \Exception(__('accounting.accounting_period.period_closed'));
            }
        });

        static::deleting(function ($model) {
            $date = $model->expense_date ?: now();
            if (AccountingPeriod::isClosed($model->organization_id, $date->month, $date->year)) {
                throw new \Exception(__('accounting.accounting_period.period_closed'));
            }
        });
    }

    protected $fillable = [
        'organization_id',
        'expense_date',
        'category',
        'description',
        'unit_price',
        'quantity',
        'amount',
        'attachments',
        'order_id',
        'reconciliation_id',
        'note',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'attachments' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
