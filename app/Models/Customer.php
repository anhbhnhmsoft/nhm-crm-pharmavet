<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customers';

    protected $fillable = [
        'organization_id',
        'username',
        'phone',
        'address',
        'customer_type',
        'assigned_staff_id',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function staffRoles(): HasMany
    {
        return $this->hasMany(CustomerStaffRole::class, 'customer_id');
    }

    public function staffs()
    {
        return $this->belongsToMany(
            User::class,
            'customer_staff_roles',
            'customer_id',
            'staff_id'
        )->withPivot('staff_type')
            ->withTimestamps();
    }
}
