<?php

namespace App\Filament\Clusters\Telesale\Pages;

use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\User\UserRole;
use App\Models\Order;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class TelesaleCeoDashboardPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';
    protected string $view = 'filament.clusters.telesale.pages.telesale-ceo-dashboard-page';
    protected static ?int $navigationSort = 15;
    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_telesale');
    }

    public array $stats = [];

    public function mount(): void
    {
        $user = Auth::user();
        $query = Order::query();

        if ($user->role !== UserRole::SUPER_ADMIN->value) {
            $query->where('organization_id', $user->organization_id);
        }

        $grossRevenue = (float) (clone $query)->sum('total_amount');
        $discount = (float) (clone $query)->sum('discount');
        $codFee = (float) (clone $query)->sum('cod_fee');
        $codSupport = (float) (clone $query)->sum('cod_support_amount');

        $this->stats = [
            'pending' => (clone $query)->where('status', OrderStatus::PENDING->value)->count(),
            'shipping' => (clone $query)->where('status', OrderStatus::SHIPPING->value)->count(),
            'completed' => (clone $query)->where('status', OrderStatus::COMPLETED->value)->count(),
            'cancelled' => (clone $query)->where('status', OrderStatus::CANCELLED->value)->count(),
            'gross_revenue' => $grossRevenue,
            'net_revenue' => max(0, $grossRevenue - $discount - $codFee - $codSupport),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('telesale.reports.ceo_dashboard_navigation');
    }

    public function getTitle(): string
    {
        return __('telesale.reports.ceo_dashboard_title');
    }

    public static function canAccess(): bool
    {
        return in_array(Auth::user()->role, [
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
        ], true);
    }
}
