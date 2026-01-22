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
        'organization_id'
    ];

    protected $casts = [
      'balance' => 'decimal:2'
    ];

    public function organization() : BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
