<?php

namespace App\Models;

use App\Common\Constants\Warehouse\InventoryMovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $table = 'inventory_movements';

    protected $fillable = [
        'organization_id',
        'warehouse_id',
        'product_id',
        'ref_type',
        'ref_id',
        'movement_type',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'pending_before',
        'pending_change',
        'pending_after',
        'reason_code',
        'reason_note',
        'actor_id',
        'occurred_at',
    ];

    protected $casts = [
        'movement_type' => InventoryMovementType::class,
        'occurred_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
