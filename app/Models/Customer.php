<?php

namespace App\Models;

use App\Common\Constants\Customer\CustomerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    ];

    protected $casts = [
        'customer_type' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
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
}
