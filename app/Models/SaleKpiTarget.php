<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleKpiTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'month',
        'kpi_amount',
        'base_salary',
        'bonus_rules_json',
    ];

    protected $casts = [
        'kpi_amount' => 'decimal:2',
        'base_salary' => 'decimal:2',
        'bonus_rules_json' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
