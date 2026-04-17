<?php

namespace App\Models;

use App\Services\ReconciliationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Throwable;

class ExchangeRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'rate_date',
        'from_currency',
        'to_currency',
        'rate',
        'source',
        'note',
        'created_by',
    ];

    protected $casts = [
        'rate_date' => 'date',
        'rate' => 'decimal:6',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $exchangeRate): void {
            if (
                $exchangeRate->from_currency !== 'USD'
                || $exchangeRate->to_currency !== 'VND'
            ) {
                return;
            }

            if (
                ! $exchangeRate->wasRecentlyCreated
                && ! $exchangeRate->wasChanged([
                    'organization_id',
                    'rate_date',
                    'from_currency',
                    'to_currency',
                    'rate',
                    'source',
                ])
            ) {
                return;
            }

            try {
                app(ReconciliationService::class)->applyExchangeRateForDateRange(
                    organizationId: (int) $exchangeRate->organization_id,
                    fromDate: (string) $exchangeRate->rate_date,
                    toDate: (string) $exchangeRate->rate_date,
                );
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
