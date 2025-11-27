<?php

namespace App\Models;

use App\Core\GenerateId\GenerateIdSnowflake;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserAssignedStaff extends Pivot
{
    use GenerateIdSnowflake;

    protected $table = 'user_assigned_staff';

    protected $fillable = [
        'staff_id',
        'customer_id',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
