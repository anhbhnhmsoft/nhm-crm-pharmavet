<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushsaleRuleSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'rules_json',
        'is_default',
    ];

    protected $casts = [
        'rules_json' => 'array',
        'is_default' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
