<?php

namespace App\Filament\Widgets;

use App\Repositories\FinancialSummaryRepository;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class FinancialDashboardWidget extends BaseWidget
{
    protected static ?int $sort = 1;
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
        if (!$user) return [];

        $repository = app(FinancialSummaryRepository::class);
        $rawData = $repository->getSummaryByDateRange($user->organization_id, $this->startDate, $this->endDate);

        $netRevenue = $rawData->sum('net_revenue');
        $cogs = $rawData->sum('cogs');
        $grossProfit = $rawData->sum('gross_profit');
        $totalExpenses = $rawData->sum('total_expenses');
        $netProfit = $rawData->sum('net_profit');

        // Charts
        $profitChart = $rawData->sortBy('date')->pluck('gross_profit')->toArray();
        $revenueChart = $rawData->sortBy('date')->pluck('net_revenue')->toArray();

        $avgGrossMargin = $netRevenue > 0 ? ($grossProfit / $netRevenue) * 100 : 0;
        $otherRevenuesSum = $rawData->sum('other_revenues');
        $avgNetMargin = ($netRevenue + $otherRevenuesSum) > 0 
            ? ($netProfit / ($netRevenue + $otherRevenuesSum)) * 100 
            : 0;

        return [
            Stat::make(__('Lợi Nhuận Gộp'), number_format($grossProfit, 0, ',', '.') . ' đ')
                ->description('Biên lợi gộp: ' . round($avgGrossMargin, 1) . '%')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($profitChart)
                ->color('success'),

            Stat::make(__('Giá Vốn (COGS)'), number_format($cogs, 0, ',', '.') . ' đ')
                ->description('Tỉ lệ vốn/doanh thu: ' . ($netRevenue > 0 ? round(($cogs / $netRevenue) * 100, 1) : 0) . '%')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('warning'),

            Stat::make(__('Lợi Nhuận Ròng'), number_format($netProfit, 0, ',', '.') . ' đ')
                ->description('Sau khi trừ chi phí ' . number_format($totalExpenses, 0, ',', '.') . ' đ')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($revenueChart)
                ->color($netProfit >= 0 ? 'success' : 'danger'),

            Stat::make(__('Hiệu Suất (Profit Margin)'), round($avgNetMargin, 1) . '%')
                ->description('Dựa trên tổng thu - tổng chi')
                ->descriptionIcon('heroicon-m-presentation-chart-bar')
                ->color($avgNetMargin > 15 ? 'success' : 'warning'),
        ];
    }
}
