<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'balance_after',
        'amount',
        'description',
        'status',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

}
