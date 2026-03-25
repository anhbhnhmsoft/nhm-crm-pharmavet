<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'date',
        'orders_count',
        'gross_revenue',
        'discounts',
        'returns_value',
        'net_revenue',
        'cogs',
        'gross_profit',
        'other_revenues',
        'total_expenses',
        'net_profit',
        'gross_margin_rate',
        'net_margin_rate',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_revenue' => 'decimal:2',
        'discounts' => 'decimal:2',
        'returns_value' => 'decimal:2',
        'net_revenue' => 'decimal:2',
        'cogs' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'other_revenues' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'gross_margin_rate' => 'decimal:2',
        'net_margin_rate' => 'decimal:2',
    ];
}
