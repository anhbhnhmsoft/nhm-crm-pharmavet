<?php

namespace App\Filament\Clusters\Warehouse\Resources\Warehouses;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Warehouse\Resources\Warehouses\Schemas\WarehouseForm;
use App\Filament\Clusters\Warehouse\Resources\Warehouses\Tables\WarehousesTable;
use App\Filament\Clusters\Warehouse\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Clusters\Warehouse\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Clusters\Warehouse\Resources\Warehouses\Pages\ListWarehouses;
use App\Models\Warehouse;
use App\Utils\Helper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = '';

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_warehouse');
    }
    public static function getModelLabel(): string
    {
        return __('warehouse.primary');
    }

    public static function getPluralModelLabel(): string
    {
        return __('warehouse.label');
    }

    public static function getNavigationLabel(): string
    {
        return __('warehouse.label');
    }


    public static function form(Schema $schema): Schema
    {
        return WarehouseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehousesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
