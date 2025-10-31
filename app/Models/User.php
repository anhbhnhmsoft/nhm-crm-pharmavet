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
        "organization_id",
        "username",
        "password",
        "email",
        "name",
        "team_id",
        "disable",
        "phone",
        "role",
        "position",
        "salary",
        "disable",
        "online_hours",
        "last_logout_at",
        "last_login_at",
        "team_id"
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
        return $this->belongsTo(Organization::class);
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
