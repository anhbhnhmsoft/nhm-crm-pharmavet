<?php

namespace App\Filament\Clusters\Warehouse;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class WarehouseCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_warehouse');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.unit_warehouse');
    }


    public static function getClusterBreadcrumb(): ?string
    {
        return __('filament.navigation.unit_warehouse');
    }
}
