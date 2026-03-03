<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fund extends Model
{
    use SoftDeletes;

    protected $table = 'funds';

    protected $fillable = [
        'balance',
        'is_locked',
        'organization_id'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_locked' => 'boolean'
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function transactions()
    {
        return $this->hasMany(FundTransaction::class);
    }
}
