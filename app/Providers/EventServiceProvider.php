<?php

namespace App\Providers;

use App\Listeners\HandleUserLogout;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ProvidersEventServiceProvider;

class EventServiceProvider extends ProvidersEventServiceProvider
{
    protected $listen = [
        Logout::class => [
            HandleUserLogout::class,
        ],
    ];

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
        //
    }
}
