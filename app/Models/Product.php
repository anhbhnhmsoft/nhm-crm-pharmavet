<?php

namespace App\Models;

use App\Common\Constants\Team\TeamType;
use App\Core\GenerateId\GenerateIdSnowflake;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, GenerateIdSnowflake, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'sku',
        'unit',
        'weight',
        'cost_price',
        'sale_price',
        'barcode',
        'type',
        'length',
        'width',
        'height',
        'quantity',
        'type_vat',
        'image',
        'description',
        'vat_rate',
        'is_business_product',
        'has_attributes',
    ];

    protected $casts = [
        'is_business_product' => 'boolean',
        'has_attributes' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function attributes()
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function userAssignments()
    {
        return $this->hasMany(ProductUserAssignment::class);
    }

    public function salesUsers()
    {
        return $this->belongsToMany(User::class, 'product_user_assignments')
            ->withTimestamps()
            ->wherePivot('type', TeamType::SALE->value);
    }

    public function cskhUsers()
    {
        return $this->belongsToMany(User::class, 'product_user_assignments')
            ->withTimestamps()
            ->wherePivot('type', TeamType::CSKH->value);
    }

    public function marketingUsers()
    {
        return $this->belongsToMany(User::class, 'product_user_assignments')
            ->withTimestamps()
            ->wherePivot('type', TeamType::MARKETING->value);
    }

    public function billOfLadingUsers()
    {
        return $this->belongsToMany(User::class, 'product_user_assignments')
            ->withTimestamps()
            ->wherePivot('type', TeamType::BILL_OF_LADING->value);
    }
}
