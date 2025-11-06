<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductUserAssignment extends Pivot
{
    use HasFactory;

    protected $table = 'product_user_assignments';

    protected $fillable = [
        'product_id',
        'user_id',
        'type'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
