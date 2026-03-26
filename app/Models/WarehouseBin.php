<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseBin extends Model
{
    use HasFactory;

    protected $table = 'warehouse_bins';

    protected $fillable = [
        'warehouse_id',
        'code',
        'name',
        'allow_mix_sku',
        'is_active',
    ];

    protected $casts = [
        'allow_mix_sku' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
