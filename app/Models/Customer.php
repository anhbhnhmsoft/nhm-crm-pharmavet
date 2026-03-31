<?php

namespace App\Models;

use App\Common\Constants\Customer\CustomerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customers';

    protected $fillable = [
        'organization_id',
        'username',
        'phone',
        'email',
        'address',
        'customer_type',
        'assigned_staff_id',
        'note',
        'source',
        'source_detail',
        'source_id',
        'duplicate_hash',
        'birthday',
        'interaction_status',
        'next_action_at',
        'province_id',
        'district_id',
        'ward_id',
        'shipping_address',
        'avatar',
        'note_temp',
        'product_id',
        'product_field_id',
    ];

    protected $casts = [
        'customer_type' => 'integer',
        'birthday' => 'date',
        'next_action_at' => 'datetime',
        'product_field_id' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function assignedStaffPrimary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function interactions()
    {
        return $this->hasMany(CustomerInteraction::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(CustomerStatusLog::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function scopeNewLeads($query)
    {
        return $query->where('customer_type', CustomerType::NEW->value);
    }

    public function scopeNewDuplicate($query)
    {
        return $query->where('customer_type', CustomerType::NEW_DUPLICATE->value);
    }

    public function scopeOldCustomer($query)
    {
        return $query->where('customer_type', CustomerType::OLD_CUSTOMER->value);
    }

    public function scopeAssignedTo($query, int $staffId)
    {
        return $query->where('assigned_staff_id', $staffId);
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_staff_id');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeNeedFollowUp($query)
    {
        return $query->whereNotNull('next_action_at')
            ->where('next_action_at', '<=', now());
    }

    public function isNew(): bool
    {
        return $this->customer_type === CustomerType::NEW->value;
    }

    public function isDuplicate(): bool
    {
        return $this->customer_type === CustomerType::NEW_DUPLICATE->value;
    }

    public function isOldCustomer(): bool
    {
        return $this->customer_type === CustomerType::OLD_CUSTOMER->value;
    }

    public function isAssigned(): bool
    {
        return !is_null($this->assigned_staff_id);
    }

    public function getCustomerTypeLabel(): string
    {
        return CustomerType::tryFrom($this->customer_type)?->label() ?? __('Unknown');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function addInteraction(string $type, array $data = []): CustomerInteraction
    {
        return $this->interactions()->create(array_merge([
            'type' => $type,
            'user_id' => Auth::id(),
            'interacted_at' => now(),
        ], $data));
    }

    public function customerStatusLog(): HasMany
    {
        return $this->hasMany(CustomerStatusLog::class, 'customer_id');
    }

    public function assignedStaff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_assigned_staff', 'customer_id', 'staff_id');
    }

    public function blackList()
    {
        return $this->hasOne(BlackList::class, 'customer_id');
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }
}
