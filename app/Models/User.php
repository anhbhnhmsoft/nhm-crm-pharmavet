<?php

namespace App\Models;

use App\Common\Constants\User\UserPosition;
use App\Common\Constants\User\UserRole;
use App\Core\GenerateId\GenerateIdSnowflake;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, GenerateIdSnowflake, SoftDeletes;

    protected $table = 'users';
    protected $fillable = [
        "organization_code",
        "team_id",
        "name",
        "username",
        "email",
        "password",
        "disable",
        "role",
        "position",
        "phone",
        "address",
        "avatar",
        "lang",
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'disable' => 'boolean',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_code', 'code');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'id', 'team_id');
    }

    public function logs()
    {
        return $this->hasMany(UserLog::class, 'user_id', 'id');
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role->value;
    }

    public function hasPosition(UserPosition $position): bool
    {
        return $this->position === $position->value;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SUPER_ADMIN);
    }


}
