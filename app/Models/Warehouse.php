<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'province_id',
        'district_id',
        'ward_id',
        'address',
        'phone',
        'note',
        'manager_id',
        'manager_phone',
        'sender_name',
        'sender_info',
        'is_active',
        'created_by',
        'updated_by',
    ];

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function deliveryProvinces()
    {
        return $this->belongsToMany(Province::class, 'warehouse_delivery_provinces', 'warehouse_id', 'province_id');
    }

    public function shippingConfig()
    {
        return $this->hasOne(ShippingConfigForWarehouse::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_warehouse')
            ->withPivot(['quantity', 'pending_quantity'])
            ->withTimestamps();
    }

    public function productWarehouses()
    {
        return $this->hasMany(ProductWarehouse::class);
    }

    public function bins()
    {
        return $this->hasMany(WarehouseBin::class);
    }
}
