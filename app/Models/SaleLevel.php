<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'kpi_target',
        'warning_thresholds_json',
        'is_active',
    ];

    protected $casts = [
        'kpi_target' => 'decimal:2',
        'warning_thresholds_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
