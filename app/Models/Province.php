<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Province extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'code_name',
        'division_type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Lấy tất cả các quận/huyện thuộc tỉnh này
     */
    public function districts(): HasMany
    {
        return $this->hasMany(District::class, 'province_id');
    }

    /**
     * Lấy tất cả các phường/xã thuộc tỉnh này (thông qua districts)
     */
    public function wards(): HasManyThrough
    {
        return $this->hasManyThrough(
            Ward::class,
            District::class,
            'province_id',
            'district_id',
            'id',
            'id',
        );
    }

    /**
     * Lấy tên đầy đủ (bao gồm loại phân chia)
     */ 
    public function getFullNameAttribute(): string
    {
        return $this->division_type . ' ' . $this->name;
    }

    /**
     * Scope: Tìm theo code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Scope: Tìm theo code_name
     */
    public function scopeByCodeName($query, string $codeName)
    {
        return $query->where('code_name', $codeName);
    }

    /**
     * Scope: Chỉ lấy tỉnh (không phải thành phố)
     */
    public function scopeOnlyProvinces($query)
    {
        return $query->where('division_type', 'Tỉnh');
    }

    /**
     * Scope: Chỉ lấy thành phố
     */
    public function scopeOnlyCities($query)
    {
        return $query->where('division_type', 'Thành phố Trung ương');
    }
}
