<?php

namespace App\Models;

use App\Core\GenerateId\GenerateIdSnowflake;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        "province_code",
        "district_code",
        "ward_code",
        "disable",
    ];

    protected $casts = [
        'disable' => 'boolean',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_code', 'code');
    }
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_code', 'code');
    }
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'ward_code', 'code');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'organization_code', 'code');
    }

}
