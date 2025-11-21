<?php

namespace App\Providers;

use App\Repositories\OrganizationRepository;
use App\Repositories\ProductAttributeRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductUserAssignmentRepository;
use App\Repositories\TeamRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\ComboService;
use App\Services\OrganizationService;
use App\Services\ProductService;
use App\Services\TeamService;
use App\Services\UserService;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\URL;
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

        if (config('app.env') === 'local' && (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || isset($_SERVER['HTTP_X_FORWARDED_HOST']))) {
            URL::forceScheme('https');
        }
    }

    private function registerRepository(): void
    {
        $this->app->bind(OrganizationRepository::class);
        $this->app->bind(ProductAttributeRepository::class);
        $this->app->bind(ProductRepository::class);
        $this->app->bind(ProductUserAssignmentRepository::class);
        $this->app->bind(TeamRepository::class);
        $this->app->bind(UserRepository::class);
    }

    private function registerApplicationService(): void
    {
        $this->app->bind(AuthService::class);
        $this->app->bind(OrganizationService::class);
        $this->app->bind(ComboService::class);
        $this->app->bind(ProductService::class);
        $this->app->bind(TeamService::class);
        $this->app->bind(UserService::class);
    }
}
