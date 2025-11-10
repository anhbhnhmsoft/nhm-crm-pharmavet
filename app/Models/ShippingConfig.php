<?php

namespace App\Models;

use App\Core\GenerateId\GenerateIdSnowflake;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingConfig extends Model
{
    use HasFactory, GenerateIdSnowflake, SoftDeletes;

    protected $table = 'shipping_configs';

    protected $fillable = [
        'organization_id',
        'account_name',
        'api_token',
        'default_store_id',
        'use_insurance',
        'insurance_limit',
        'required_note',
        'allow_cod_on_failed',
        'default_pickup_shift',
        'default_pickup_time'
    ];

    protected $casts = [
        'api_token' => 'encrypted',
        'use_insurance' => 'boolean',
        'allow_cod_on_failed' => 'boolean',
        'insurance_limit' => 'decimal:2',
        'default_pickup_time' => 'datetime',
    ];

    protected $attributes = [
        'use_insurance' => false,
        'insurance_limit' => 0,
        'allow_cod_on_failed' => false,
        'default_pickup_shift' => '1',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

}
