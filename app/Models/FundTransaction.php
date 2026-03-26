<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FundTransaction extends Model
{
    use SoftDeletes;

    protected $table = "fund_transactions";
    protected $fillable = [
        'fund_id',
        'type',
        'transaction_code',
        'transaction_id',
        'transaction_date',
        'balance_after',
        'amount',
        'counterparty_name',
        'currency',
        'exchange_rate',
        'amount_base',
        'description',
        'purpose',
        'note',
        'status',
        'updated_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'amount_base' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'balance_after' => 'decimal:2',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FundTransactionAttachment::class, 'fund_transaction_id');
    }

}
