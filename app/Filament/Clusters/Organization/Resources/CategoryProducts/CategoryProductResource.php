<?php

namespace App\Filament\Clusters\Organization\Resources\CategoryProducts;

use App\Common\Constants\GateKey;
use App\Filament\Clusters\Organization\Resources\CategoryProducts\Pages\CreateCategoryProduct;
use App\Filament\Clusters\Organization\Resources\CategoryProducts\Pages\EditCategoryProduct;
use App\Filament\Clusters\Organization\Resources\CategoryProducts\Pages\ListCategoryProducts;
use App\Filament\Clusters\Organization\Resources\CategoryProducts\Schemas\CategoryProductForm;
use App\Filament\Clusters\Organization\Resources\CategoryProducts\Tables\CategoryProductsTable;
use App\Models\CategoryProduct;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class CategoryProductResource extends Resource
{
    protected static ?string $model = CategoryProduct::class;

    protected static string|BackedEnum|null $navigationIcon = '';

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_administration');
    }

    public static  function canAccess(): bool
    {
        return Gate::allows(GateKey::IS_SUPER_ADMIN);
    }

    public static function getModelLabel(): string
    {
        return __('organization.category_products.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('organization.category_products.label');
    }

    public static function getNavigationLabel(): string
    {
        return __('organization.category_products.label');
    }

    public static function form(Schema $schema): Schema
    {
        return CategoryProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoryProductsTable::configure($table);
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
            'index' => ListCategoryProducts::route('/'),
            'create' => CreateCategoryProduct::route('/create'),
            'edit' => EditCategoryProduct::route('/{record}/edit'),
        ];
    }
}
