<?php

namespace App\Providers;

use App\Models\Organization;
use App\Observers\OrganizationObserver;
use App\Repositories\ComboRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\FundRepository;
use App\Repositories\FundTransactionRepository;
use App\Repositories\IntegrationEntityRepository;
use App\Repositories\IntegrationRepository;
use App\Repositories\IntegrationTokenRepository;
use App\Repositories\InventoryTicketRepository;
use App\Repositories\LeadDistributionConfigRepository;
use App\Repositories\LeadDistributionRuleRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrderStatusLogRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\ProductAttributeRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductUserAssignmentRepository;
use App\Repositories\ShippingConfigRepository;
use App\Repositories\TeamRepository;
use App\Repositories\UserAssignedStaffRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\ComboService;
use App\Services\CustomerService;
use App\Services\Integrations\IntegrationService;
use App\Services\LeadDistributionConfigService;
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

        $this->registerObserver();
    }

    private function registerRepository(): void
    {
        $this->app->bind(OrganizationRepository::class);
        $this->app->bind(ProductAttributeRepository::class);
        $this->app->bind(ProductRepository::class);
        $this->app->bind(ProductUserAssignmentRepository::class);
        $this->app->bind(TeamRepository::class);
        $this->app->bind(UserRepository::class);
        $this->app->bind(IntegrationRepository::class);
        $this->app->bind(IntegrationTokenRepository::class);
        $this->app->bind(IntegrationEntityRepository::class);
        $this->app->bind(CustomerRepository::class);
        $this->app->bind(UserAssignedStaffRepository::class);
        $this->app->bind(LeadDistributionConfigRepository::class);
        $this->app->bind(LeadDistributionRuleRepository::class);
        $this->app->bind(ComboRepository::class);
        $this->app->bind(ShippingConfigRepository::class);
        $this->app->bind(OrderRepository::class);
        $this->app->bind(OrderItemRepository::class);
        $this->app->bind(OrderStatusLogRepository::class);
        $this->app->bind(FundRepository::class);
        $this->app->bind(FundTransactionRepository::class);
        $this->app->bind(InventoryTicketRepository::class);

    }

    private function registerApplicationService(): void
    {
        $this->app->bind(AuthService::class);
        $this->app->bind(OrganizationService::class);
        $this->app->bind(ComboService::class);
        $this->app->bind(ProductService::class);
        $this->app->bind(TeamService::class);
        $this->app->bind(UserService::class);
        $this->app->bind(IntegrationService::class);
        $this->app->bind(CustomerService::class);
        $this->app->bind(LeadDistributionConfigService::class);
    }

    private function registerObserver(): void
    {
        Organization::observe(OrganizationObserver::class);
    }
}
