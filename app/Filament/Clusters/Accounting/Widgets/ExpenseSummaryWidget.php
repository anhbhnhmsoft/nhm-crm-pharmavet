<?php

namespace App\Filament\Clusters\Accounting\Widgets;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ExpenseSummaryWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $organizationId = Auth::user()->organization_id;

        $totalThisMonth = Expense::where('organization_id', $organizationId)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');

        $totalToday = Expense::where('organization_id', $organizationId)
            ->whereDate('expense_date', now()->toDateString())
            ->sum('amount');

        $topCategoryThisMonth = Expense::where('organization_id', $organizationId)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->first();

        $topCategoryLabel = $topCategoryThisMonth
            ? ExpenseCategory::tryFrom($topCategoryThisMonth->category)?->getLabel()
            : 'N/A';

        return [
            Stat::make('Tổng chi tháng này', number_format($totalThisMonth, 0, ',', '.') . ' ₫')
                ->description('Tổng chi phí đã ghi nhận trong tháng ' . now()->format('m/Y'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger'),
            Stat::make('Chi hôm nay', number_format($totalToday, 0, ',', '.') . ' ₫')
                ->description('Chi phí phát sinh ngày hôm nay')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('warning'),
            Stat::make('Hạng mục chi nhiều nhất', $topCategoryLabel)
                ->description($topCategoryThisMonth ? 'Tổng: ' . number_format($topCategoryThisMonth->total, 0, ',', '.') . ' ₫' : 'Chưa có dữ liệu')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color('info'),
        ];
    }
}
