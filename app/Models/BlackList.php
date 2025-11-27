<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlackList extends Model
{
    use HasFactory;

    protected $table = 'black_list';

    protected $fillable = [
        'customer_id',
        'user_id',
        'note',
        'reason',
    ];

    protected $casts = [
        'reason' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}