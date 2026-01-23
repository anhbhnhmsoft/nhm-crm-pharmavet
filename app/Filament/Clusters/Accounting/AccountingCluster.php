<?php

namespace App\Filament\Clusters\Accounting;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class AccountingCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.unit_accounting');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('filament.navigation.unit_accounting');
    }
}
