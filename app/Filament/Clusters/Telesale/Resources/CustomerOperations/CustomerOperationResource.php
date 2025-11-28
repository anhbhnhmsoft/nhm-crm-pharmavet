<?php

namespace App\Filament\Clusters\Telesale\Resources\CustomerOperations;

use App\Filament\Clusters\Telesale\Resources\CustomerOperations\Pages\ListCustomerOperations;
use App\Filament\Clusters\Telesale\Resources\CustomerOperations\Tables\CustomerOperationsTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerOperationResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_telesale');
    }

    public static function getModelLabel(): string
    {
        return __('telesale.operation_page_title');
    }

    public static function getPluralModelLabel(): string
    {
        return __('telesale.operation_page_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('telesale.operation_navigation_label');
    }

    public static function table(Table $table): Table
    {
        return CustomerOperationsTable::configure($table);
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
            'index' => ListCustomerOperations::route('/'),
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
