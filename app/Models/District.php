<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'code_name',
        'division_type',
        'province_id',
        'province_code',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Lấy tỉnh/thành phố mà quận/huyện này thuộc về
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    /**
     * Lấy tất cả các phường/xã thuộc quận/huyện này
     */
    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class, 'district_id');
    }

    /**
     * Lấy tên đầy đủ (bao gồm loại phân chia)
     */
    public function getFullNameAttribute(): string
    {
        return $this->division_type . ' ' . $this->name;
    }

    /**
     * Lấy địa chỉ đầy đủ (bao gồm tỉnh)
     */
    public function getFullAddressAttribute(): string
    {
        return $this->full_name . ', ' . $this->province->full_name;
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
     * Scope: Lọc theo tỉnh/thành phố
     */
    public function scopeByProvince($query, int $provinceId)
    {
        return $query->where('province_id', $provinceId);
    }

    /**
     * Scope: Lọc theo province_code
     */
    public function scopeByProvinceCode($query, string $provinceCode)
    {
        return $query->where('province_code', $provinceCode);
    }

    /**
     * Scope: Chỉ lấy quận
     */
    public function scopeOnlyDistricts($query)
    {
        return $query->where('division_type', 'Quận');
    }

    /**
     * Scope: Chỉ lấy huyện
     */
    public function scopeOnlyCounties($query)
    {
        return $query->whereIn('division_type', ['Huyện', 'Thị xã', 'Thành phố']);
    }
}
