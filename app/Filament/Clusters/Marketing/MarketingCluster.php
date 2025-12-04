<?php

namespace App\Filament\Clusters\Marketing;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class MarketingCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = '';

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_marketing');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.integration.plural_model_label');
    }


    public static function getClusterBreadcrumb(): ?string
    {
        return __('filament.navigation.unit_marketing');
    }
}
