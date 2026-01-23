<?php

namespace App\Models;

use App\Core\GenerateId\GenerateIdSnowflake;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, GenerateIdSnowflake, SoftDeletes;

    protected $table = 'organizations';
    protected $fillable = [
        "name",
        "code",
        "description",
        "address",
        "phone",
        "product_field",
        'maximum_employees',
        "disable",
        "is_foreign"
    ];

    protected $casts = [
        'disable' => 'boolean',
        'is_foreign' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function shippingConfig(): HasOne
    {
        return $this->hasOne(ShippingConfig::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function fund () : HasOne
    {
        return $this->hasOne(Fund::class);
    }
}
