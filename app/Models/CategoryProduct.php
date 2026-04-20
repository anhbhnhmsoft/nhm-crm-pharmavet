<?php

namespace App\Models;

use App\Common\Constants\GateKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

class CategoryProduct extends Model
{
    protected $table = 'category_products';

    protected $fillable = [
        'name',
        'description',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
    ];

    public function getDisplayNameAttribute(): string
    {
        return $this->resolveLocalizedValue($this->name);
    }

    public function getDisplayDescriptionAttribute(): string
    {
        return $this->resolveLocalizedValue($this->description);
    }

    public function products() : HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function totalProducts() : int
    {
        return  Gate::allows(GateKey::IS_SUPER_ADMIN) ? $this->products()->count() : $this->products()->where('organization_id', auth()->user()->organization_id)->count();
    }

    protected function resolveLocalizedValue(mixed $value): string
    {
        if (is_array($value)) {
            foreach (['vi', 'en', 'lo'] as $locale) {
                $localizedValue = $value[$locale] ?? null;

                if (is_scalar($localizedValue) && trim((string) $localizedValue) !== '') {
                    return (string) $localizedValue;
                }
            }

            foreach ($value as $item) {
                if (is_scalar($item) && trim((string) $item) !== '') {
                    return (string) $item;
                }
            }

            return '';
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
