<?php

namespace App\Models;

use App\Core\GenerateId\GenerateIdSnowflake;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserShift extends Pivot
{
    use HasFactory, GenerateIdSnowflake;

    protected $table = 'user_shift';

    protected $fillable = [
        "user_id",
        "shift_id",
    ];
}
