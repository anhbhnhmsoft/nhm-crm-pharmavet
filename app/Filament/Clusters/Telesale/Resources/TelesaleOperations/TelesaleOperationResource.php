<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations;

use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Pages\CreateTelesaleOperation;
use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Pages\EditTelesaleOperation;
use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Pages\ListTelesaleOperations;
use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Schemas\TelesaleOperationForm;
use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Tables\TelesaleOperationsTable;
use App\Filament\Clusters\Telesale\TelesaleCluster;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TelesaleOperationResource extends Resource
{
    protected static ?string $model = \App\Models\Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = TelesaleCluster::class;

    public static function getModelLabel(): string
    {
        return __('telesale.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('telesale.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('telesale.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return TelesaleOperationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelesaleOperationsTable::configure($table);
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
            'index' => ListTelesaleOperations::route('/'),
            'create' => CreateTelesaleOperation::route('/create'),
            'edit' => EditTelesaleOperation::route('/{record}/edit'),
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
