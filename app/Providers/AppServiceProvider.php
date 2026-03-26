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
use App\Repositories\ExchangeRateRepository;
use App\Repositories\ReconciliationRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\RevenueRepository;
use App\Repositories\ProductAttributeRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductUserAssignmentRepository;
use App\Repositories\ShippingConfigForWareHouseRepository;
use App\Repositories\ShippingConfigRepository;
use App\Repositories\TeamRepository;
use App\Repositories\UserAssignedStaffRepository;
use App\Repositories\UserRepository;
use App\Repositories\FinancialSummaryRepository;
use App\Repositories\DiscrepancyReportRepository;
use App\Repositories\Accounting\DebtRepository;
use App\Services\AuthService;
use App\Services\ComboService;
use App\Services\CustomerService;
use App\Services\Integrations\IntegrationService;
use App\Services\LeadDistributionConfigService;
use App\Services\DebtNotificationService;
use App\Services\OrganizationService;
use App\Services\AccountingService;
use App\Services\Accounting\FinancialSummaryService;
use App\Services\Accounting\DiscrepancyReportService;
use App\Services\Accounting\DebtService;
use App\Services\ExchangeRateService;
use App\Services\ReconciliationService;
use App\Services\ReportService;
use App\Services\ProductService;
use App\Services\TeamService;
use App\Services\UserService;
use App\Repositories\InventoryTicketDetailRepository;
use App\Repositories\ProductWarehouseRepository;
use App\Repositories\WarehouseRepository;
use App\Services\DashboardService;
use App\Services\FundService;
use App\Services\GHNService;
use App\Services\GhnShippingService;
use App\Services\Integrations\MetaBusinessService;
use App\Services\LeadDistributionService;
use App\Services\OrderService;
use App\Services\ShippingConfigService;
use App\Services\WarehouseService;
use App\Repositories\InventoryTicketLogRepository;
use App\Repositories\InventoryMovementRepository;
use App\Repositories\PushsaleRuleSetRepository;
use App\Repositories\ReportExportJobRepository;
use App\Repositories\SaleKpiTargetRepository;
use App\Repositories\SaleLevelRepository;
use App\Repositories\TeamReportScopeRepository;
use App\Repositories\TelesaleNotificationAggregateRepository;
use App\Services\ExpenseService;
use App\Services\Telesale\Customer360Service;
use App\Services\Telesale\HonorBoardService;
use App\Services\Telesale\LeadNotificationService;
use App\Services\Telesale\OrderFinanceService;
use App\Services\Telesale\PushsaleRuleService;
use App\Services\Telesale\TelesaleKpiService;
use App\Services\Telesale\TelesaleReportExportService;
use App\Services\Telesale\TelesaleReportScopeService;
use App\Services\Warehouse\InventoryMovementService;
use App\Services\Warehouse\WarehouseExportService;
use App\Services\Warehouse\WarehouseReportService;
use App\Services\Warehouse\ShippingStatusSyncService;
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
        $this->app->bind(ExchangeRateRepository::class);
        $this->app->bind(ReconciliationRepository::class);
        $this->app->bind(ExpenseRepository::class);
        $this->app->bind(RevenueRepository::class);
        $this->app->bind(ShippingConfigForWareHouseRepository::class);
        $this->app->bind(InventoryTicketDetailRepository::class);
        $this->app->bind(ProductWarehouseRepository::class);
        $this->app->bind(WarehouseRepository::class);
        $this->app->bind(InventoryTicketLogRepository::class);
        $this->app->bind(InventoryMovementRepository::class);
        $this->app->bind(PushsaleRuleSetRepository::class);
        $this->app->bind(ReportExportJobRepository::class);
        $this->app->bind(SaleKpiTargetRepository::class);
        $this->app->bind(SaleLevelRepository::class);
        $this->app->bind(TeamReportScopeRepository::class);
        $this->app->bind(TelesaleNotificationAggregateRepository::class);
        $this->app->bind(FinancialSummaryRepository::class);
        $this->app->bind(DiscrepancyReportRepository::class);
        $this->app->bind(DebtRepository::class);
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
        $this->app->bind(AccountingService::class);
        $this->app->bind(ExchangeRateService::class);
        $this->app->bind(ReconciliationService::class);
        $this->app->bind(ReportService::class);
        $this->app->bind(DashboardService::class);
        $this->app->bind(FundService::class);
        $this->app->bind(GHNService::class);
        $this->app->bind(GhnShippingService::class);
        $this->app->bind(MetaBusinessService::class);
        $this->app->bind(LeadDistributionService::class);
        $this->app->bind(OrderService::class);
        $this->app->bind(ShippingConfigService::class);
        $this->app->bind(WarehouseService::class);
        $this->app->bind(ExpenseService::class);
        $this->app->bind(Customer360Service::class);
        $this->app->bind(HonorBoardService::class);
        $this->app->bind(LeadNotificationService::class);
        $this->app->bind(OrderFinanceService::class);
        $this->app->bind(PushsaleRuleService::class);
        $this->app->bind(TelesaleKpiService::class);
        $this->app->bind(TelesaleReportExportService::class);
        $this->app->bind(TelesaleReportScopeService::class);
        $this->app->bind(InventoryMovementService::class);
        $this->app->bind(ShippingStatusSyncService::class);
        $this->app->bind(WarehouseReportService::class);
        $this->app->bind(WarehouseExportService::class);
        $this->app->bind(DebtNotificationService::class);
        $this->app->bind(FinancialSummaryService::class);
        $this->app->bind(DiscrepancyReportService::class);
        $this->app->bind(DebtService::class);
    }

    private function registerObserver(): void
    {
        Organization::observe(OrganizationObserver::class);
    }
}
