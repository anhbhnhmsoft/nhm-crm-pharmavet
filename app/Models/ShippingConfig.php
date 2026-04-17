<?php

namespace App\Models;

use App\Core\GenerateId\GenerateIdSnowflake;
use Illuminate\Contracts\Encryption\DecryptException;
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
        'default_pickup_time',
        'warehouse_id',
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function hasDecryptableApiToken(): bool
    {
        try {
            $this->api_token;

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    public function getApiTokenSafely(): ?string
    {
        try {
            $token = $this->api_token;

            return filled($token) ? (string) $token : null;
        } catch (DecryptException) {
            return null;
        }
    }

    public function hasCompleteGhnCredentials(): bool
    {
        return filled($this->default_store_id) && filled($this->getApiTokenSafely());
    }

    public function hasInvalidEncryptedApiToken(): bool
    {
        return blank($this->getApiTokenSafely()) && filled($this->getRawOriginal('api_token'));
    }

    public function toSafeFormState(): array
    {
        return [
            'account_name' => $this->account_name,
            'api_token' => $this->getApiTokenSafely() ?? '',
            'default_store_id' => $this->default_store_id,
            'use_insurance' => (bool) $this->use_insurance,
            'insurance_limit' => $this->insurance_limit,
            'required_note' => $this->required_note,
            'allow_cod_on_failed' => (bool) $this->allow_cod_on_failed,
            'default_pickup_shift' => $this->default_pickup_shift,
            'default_pickup_time' => $this->default_pickup_time?->format('H:i'),
        ];
    }
}
