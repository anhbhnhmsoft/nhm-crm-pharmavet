<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelesaleNotificationAggregate extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'duplicate_hash',
        'lead_count',
        'last_customer_id',
        'last_notified_at',
    ];

    protected $casts = [
        'last_notified_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lastCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'last_customer_id');
    }
}
