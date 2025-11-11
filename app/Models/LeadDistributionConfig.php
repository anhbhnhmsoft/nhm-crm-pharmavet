<?php

namespace App\Models;

use App\Common\Constants\Team\TeamType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadDistributionConfig extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lead_distribution_configs';

    protected $fillable = [
        'organization_id',
        'product_id',
        'name',
        'created_by',
        'updated_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(LeadDistributionRule::class, 'config_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'lead_distribution_staff',
            'config_id',
            'staff_id'
        )
        ->using(LeadDistributionStaff::class)
        ->withPivot(['weight']);
    }

    protected function staffByType(string $type): BelongsToMany
    {
        return $this->staff()
            ->whereHas('team', function ($query) use ($type) {
                $query->where('type', $type);
            })
            ->withPivot('weight');
    }

    public function staffSale(): BelongsToMany
    {
        return $this->staffByType(TeamType::SALE->value);
    }

    public function staffCSKH(): BelongsToMany
    {
        return $this->staffByType(TeamType::CSKH->value);
    }
}
