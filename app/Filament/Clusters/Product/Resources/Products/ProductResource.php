<?php

namespace App\Filament\Clusters\Product\Resources\Products;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Product\ProductCluster;
use App\Filament\Clusters\Product\Resources\Products\Pages\CreateProduct;
use App\Filament\Clusters\Product\Resources\Products\Pages\EditProduct;
use App\Filament\Clusters\Product\Resources\Products\Pages\ListProducts;
use App\Filament\Clusters\Product\Resources\Products\Schemas\ProductForm;
use App\Filament\Clusters\Product\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use App\Utils\Helper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = '';

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_administration');
    }

    protected static ?string $recordTitleAttribute = 'Product';

    public static function getModelLabel(): string
    {
        return __('filament.product.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.product.label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.product.label');
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
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
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
        ], Auth::user()->role);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $currentUser = Auth::user();

        if (Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
        ], Auth::user()->role)) {
            return $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
        }

        return $query->where('organization_id', $currentUser->organization_id)
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
