<?php

namespace App\Providers;

use App\Common\Constants\GateKey;
use App\Common\Constants\User\UserPosition;
use App\Common\Constants\User\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class GateProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::define(GateKey::IS_SUPER_ADMIN, function (User $user) {
            return $user->isSuperAdmin();
        });

        Gate::define(GateKey::HAS_ROLE, fn(User $user, UserRole|array $roles) =>
            $user->isSuperAdmin() ||
            in_array(
                $user->role,
                collect(is_array($roles) ? $roles : [$roles])
                    ->map(fn($r) => $r instanceof UserRole ? $r->value : $r)
                    ->all(),
                true
            )
        );

        Gate::define(GateKey::HAS_POSITION, fn (User $user, UserPosition|array $position) =>
            $user->isSuperAdmin() ||
            in_array(
                $user->position,
                collect(is_array($position) ? $position : [$position])
                    ->map(fn($p) => $p instanceof UserPosition ? $p->value : $p)
                    ->all(),
                true
            )
        );

        Gate::define(GateKey::IS_CHIEF_ACCOUNTANT->value, function (User $user) {
            return $user->isSuperAdmin() ||
                $user->hasRole(UserRole::ADMIN) ||
                ($user->hasRole(UserRole::ACCOUNTING) && $user->hasPosition(UserPosition::LEADER));
        });

        Gate::define(GateKey::IS_ACCOUNTING->value, function (User $user) {
            return $user->isSuperAdmin() ||
                $user->hasRole(UserRole::ADMIN) ||
                $user->hasRole(UserRole::ACCOUNTING);
        });
    }

}
