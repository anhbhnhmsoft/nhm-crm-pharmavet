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

        Gate::define(GateKey::IS_ADMIN, function (User $user) {
            return $user->hasRole(UserRole::ADMIN);
        });

        Gate::define(GateKey::HAS_ROLE, function (User $user, UserRole ...$roles) {
            return $user->hasAnyRole(...$roles);
        });

        Gate::define(GateKey::HAS_POSITION, function (User $user, UserPosition ...$positions) {
            return $user->hasAnyPosition(...$positions);
        });

//        Gate::define(GateKey::IS_CHIEF_ACCOUNTANT->value, function (User $user) {
//            return $user->isSuperAdmin() ||
//                $user->hasRole(UserRole::ADMIN) ||
//                ($user->hasRole(UserRole::ACCOUNTING) && $user->hasPosition(UserPosition::LEADER));
//        });
//
//        Gate::define(GateKey::IS_ACCOUNTING->value, function (User $user) {
//            return $user->isSuperAdmin() ||
//                $user->hasRole(UserRole::ADMIN) ||
//                $user->hasRole(UserRole::ACCOUNTING);
//        });
    }

}
