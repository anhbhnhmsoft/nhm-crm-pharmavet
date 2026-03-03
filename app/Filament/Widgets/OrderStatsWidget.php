<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class OrderStatsWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    public ?string $startDate = null;
    public ?string $endDate = null;

    public function mount(): void
    {
        $this->startDate = session('dashboard_start_date', now()->startOfMonth()->format('Y-m-d'));
        $this->endDate = session('dashboard_end_date', now()->endOfMonth()->format('Y-m-d'));
    }

    #[On('dateRangeUpdated')]
    public function updateDateRange($start_date, $end_date): void
    {
        $this->startDate = $start_date;
        $this->endDate = $end_date;
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $service = app(DashboardService::class);
        $data = $service->getOrderStats($user->organization_id, $this->startDate, $this->endDate);

        return [
            Stat::make(__('dashboard.order_stats.total_revenue'), number_format($data['totalRevenue'], 0, ',', '.') . ' đ')
                ->description($data['completedOrders'] . ' ' . __('dashboard.order_stats.completed_orders'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($data['revenueChart']),

            Stat::make(__('dashboard.order_stats.total_orders'), $data['totalOrders'])
                ->description(__('dashboard.order_stats.in_period'))
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary')
                ->chart($data['ordersChart']),

            Stat::make(__('dashboard.order_stats.pending_orders'), $data['pendingOrders'])
                ->description($data['shippingOrders'] . ' ' . __('dashboard.order_stats.shipping_orders'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($data['pendingOrders'] > 0 ? 'warning' : 'success'),

            Stat::make(__('dashboard.order_stats.cancelled_orders'), $data['cancelledOrders'])
                ->description(__('dashboard.order_stats.in_period'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($data['cancelledOrders'] > 0 ? 'danger' : 'success'),
        ];
    }
}
