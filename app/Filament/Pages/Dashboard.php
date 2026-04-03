<?php

namespace App\Filament\Pages;

use App\Services\OrganizationService;
use Filament\Pages\Dashboard as PagesDashboard;

class Dashboard extends PagesDashboard
{

    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation_label');
    }

    public function getTitle(): string
    {
        return __('dashboard.title');
    }

    public function getWidgets(): array
    {
        $widget = [];

        $result = app(OrganizationService::class)->getOrganizationById(auth()->user()->organization_id);
        if ($result->isSuccess()) {
            // Date filter
            $widget[] = \App\Filament\Widgets\SelectGap::class;

            // Primary KPIs
            $widget[] = \App\Filament\Widgets\OrderStatsWidget::class;
            $widget[] = \App\Filament\Widgets\LeadStatsWidget::class;

            // Trends
            $widget[] = \App\Filament\Widgets\OrderChartWidget::class;
            $widget[] = \App\Filament\Widgets\OrderStatusChartWidget::class;
            $widget[] = \App\Filament\Widgets\CustomerGrowthChartWidget::class;
            $widget[] = \App\Filament\Widgets\TopProductsWidget::class;

            // Operational table
            $widget[] = \App\Filament\Widgets\RecentOrdersWidget::class;

            // Foreign-currency organizations only
            if ($result->getData()->is_foreign) {
                $widget[] = \App\Filament\Widgets\FundStatsWidget::class;
                $widget[] = \App\Filament\Widgets\FundBalanceChartWidget::class;
            }
        }
        return $widget;
    }
}
