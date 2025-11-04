<?php

namespace App\Models;

use App\Common\Constants\User\UserPosition;
use App\Common\Constants\User\UserRole;
use App\Core\GenerateId\GenerateIdSnowflake;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
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

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function logs()
    {
        return $this->hasMany(UserLog::class, 'user_id', 'id');
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role == $role->value;
    }

    public function hasPosition(UserPosition $position): bool
    {
        return $this->position === $position->value;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SUPER_ADMIN);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
}
