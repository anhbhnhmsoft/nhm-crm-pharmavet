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

    public function products() : HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function totalProducts() : int
    {
        return  Gate::allows(GateKey::IS_SUPER_ADMIN) ? $this->products()->count() : $this->products()->where('organization_id', auth()->user()->organization_id)->count();
    }
}
