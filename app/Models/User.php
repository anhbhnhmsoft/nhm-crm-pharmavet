<?php

namespace App\Models;

use App\Common\Constants\User\UserPosition;
use App\Common\Constants\User\UserRole;
use App\Core\GenerateId\GenerateIdSnowflake;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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
        return !$this->disable;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function logs(): HasMany
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    public function shifts(): HasManyThrough
    {
        return $this->hasManyThrough(Shift::class, UserShift::class);
    }

    public function assignedCustomers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, UserAssignedStaff::class);
    }
}
