<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ward extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'code_name',
        'division_type',
        'district_id',
        'district_code',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Lấy quận/huyện mà phường/xã này thuộc về
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    /**
     * Lấy tỉnh/thành phố mà phường/xã này thuộc về (thông qua district)
     */
    public function province(): BelongsTo
    {
        return $this->district->province();
    }

    /**
     * Lấy tên đầy đủ (bao gồm loại phân chia)
     */
    public function getFullNameAttribute(): string
    {
        return $this->division_type . ' ' . $this->name;
    }

    /**
     * Lấy địa chỉ đầy đủ (bao gồm quận và tỉnh)
     */
    public function getFullAddressAttribute(): string
    {
        if (!$this->relationLoaded('district')) {
            $this->load('district.province');
        }

        $address = $this->full_name;

        if ($this->district) {
            $address .= ', ' . $this->district->full_name;

            if ($this->district->province) {
                $address .= ', ' . $this->district->province->full_name;
            }
        }

        return $address;
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
     * Scope: Lọc theo quận/huyện
     */
    public function scopeByDistrict($query, int $districtId)
    {
        return $query->where('district_id', $districtId);
    }

    /**
     * Scope: Lọc theo district_code
     */
    public function scopeByDistrictCode($query, string $districtCode)
    {
        return $query->where('district_code', $districtCode);
    }

    /**
     * Scope: Lọc theo tỉnh/thành phố (thông qua district)
     */
    public function scopeByProvince($query, int $provinceId)
    {
        return $query->whereHas('district', function ($q) use ($provinceId) {
            $q->where('province_id', $provinceId);
        });
    }

    /**
     * Scope: Chỉ lấy phường
     */
    public function scopeOnlyWards($query)
    {
        return $query->where('division_type', 'Phường');
    }

    /**
     * Scope: Chỉ lấy xã
     */
    public function scopeOnlyCommunes($query)
    {
        return $query->whereIn('division_type', ['Xã', 'Thị trấn']);
    }
}
