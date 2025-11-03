<?php

namespace App\Providers;

use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\OrganizationService;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerRepository();
        $this->registerApplicationService();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentAsset::register([
            Js::make('activity-tracker', resource_path('js/activity-tracker.js')),
        ]);
    }

    private function registerRepository(): void
    {
        $this->app->bind(OrganizationRepository::class);
        $this->app->bind(UserRepository::class);
    }

    private function registerApplicationService(): void
    {
        $this->app->bind(AuthService::class);
        $this->app->bind(OrganizationService::class);
    }
}
