<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Revenue extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $date = $model->revenue_date ?: now();
            if (AccountingPeriod::isClosed($model->organization_id, $date->month, $date->year)) {
                throw new \Exception(__('accounting.accounting_period.period_closed'));
            }
        });

        static::deleting(function ($model) {
            $date = $model->revenue_date ?: now();
            if (AccountingPeriod::isClosed($model->organization_id, $date->month, $date->year)) {
                throw new \Exception(__('accounting.accounting_period.period_closed'));
            }
        });
    }

    protected $fillable = [
        'organization_id',
        'revenue_date',
        'description',
        'amount',
        'note',
        'created_by',
    ];

    protected $casts = [
        'revenue_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
