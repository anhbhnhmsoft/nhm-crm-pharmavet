<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingConfigForWarehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'organization_id',
        'account_name',
        'api_token',
        'store_id',
        'use_insurance',
        'insurance_limit',
        'required_note',
        'pickup_shift',
        'cod_failed_amount',
        'fix_receiver_phone',
        'is_default',
    ];

    protected $casts = [
        'use_insurance' => 'boolean',
        'fix_receiver_phone' => 'boolean',
        'is_default' => 'boolean',
        'cod_failed_amount' => 'decimal:0',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
