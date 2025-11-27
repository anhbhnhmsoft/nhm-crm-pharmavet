<?php

namespace App\Filament\Clusters\Telesale;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class TelesaleCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_telesale');
    }

    public static function getNavigationLabel(): string
    {
        return __('telesale.cluster_label');
    }


    public static function getClusterBreadcrumb(): ?string
    {
        return __('telesale.cluster_label');
    }
}
