<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingShop extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'phone',
        'address',
        'province_id',
        'district_id',
        'ward_code',
        'organization_id',
        'is_default',
        'status',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
