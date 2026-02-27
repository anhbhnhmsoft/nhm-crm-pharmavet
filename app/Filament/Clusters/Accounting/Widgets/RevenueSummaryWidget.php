<?php

namespace App\Filament\Clusters\Accounting\Widgets;

use App\Models\Revenue;
use App\Models\Order;
use App\Common\Constants\Order\OrderStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class RevenueSummaryWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $organizationId = Auth::user()->organization_id;

        // Doanh thu từ đơn hàng hoàn thành trong tháng
        $orderRevenue = Order::where('organization_id', $organizationId)
            ->where('status', OrderStatus::COMPLETED->value)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');

        // Doanh thu khác trong tháng
        $otherRevenue = Revenue::where('organization_id', $organizationId)
            ->whereMonth('revenue_date', now()->month)
            ->whereYear('revenue_date', now()->year)
            ->sum('amount');

        $totalRevenue = $orderRevenue + $otherRevenue;

        return [
            Stat::make('Tổng doanh thu tháng này', number_format($totalRevenue, 0, ',', '.') . ' ₫')
                ->description('Bao gồm đơn hàng và doanh thu khác')
                ->descriptionIcon('heroicon-m-presentation-chart-line')
                ->color('success'),
            Stat::make('Doanh thu từ đơn hàng', number_format($orderRevenue, 0, ',', '.') . ' ₫')
                ->description('Chỉ tính các đơn hàng đã hoàn thành')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('primary'),
            Stat::make('Doanh thu khác', number_format($otherRevenue, 0, ',', '.') . ' ₫')
                ->description('Các nguồn thu nhập ngoài đơn hàng')
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('info'),
        ];
    }
}
