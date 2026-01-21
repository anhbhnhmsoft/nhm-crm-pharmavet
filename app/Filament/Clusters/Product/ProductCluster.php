<?php

namespace App\Filament\Clusters\Product;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class ProductCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = '';

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.product.cluster_label');
    }


    public static function getClusterBreadcrumb(): ?string
    {
        return __('filament.product.cluster_label');
    }
}
