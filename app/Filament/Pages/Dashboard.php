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
        if ($result->isSuccess() && $result->getData()->is_foreign) {
            $widget[] = \App\Filament\Widgets\FundStatsWidget::class;
            $widget[] = \App\Filament\Widgets\FundBalanceChartWidget::class;
            $widget[] = \App\Filament\Widgets\SelectGap::class;
        }
        return $widget;
    }
}
