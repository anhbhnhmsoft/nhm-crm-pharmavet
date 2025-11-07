<?php

namespace App\Filament\Clusters\Product\Resources\Combos;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Product\ProductCluster;
use App\Filament\Clusters\Product\Resources\Combos\Pages\CreateCombo;
use App\Filament\Clusters\Product\Resources\Combos\Pages\EditCombo;
use App\Filament\Clusters\Product\Resources\Combos\Pages\ListCombos;
use App\Filament\Clusters\Product\Resources\Combos\Schemas\ComboForm;
use App\Filament\Clusters\Product\Resources\Combos\Tables\CombosTable;
use App\Models\Combo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ComboResource extends Resource
{
    protected static ?string $model = Combo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = ProductCluster::class;

    protected static ?string $recordTitleAttribute = 'Combo';

    public static function getNavigationParentItem(): ?string
    {
        return __('filament.navigation.unit_administration');
    }

    public static function getModelLabel(): string
    {
        return __('filament.combo.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.combo.label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.combo.label');
    }

    public static function form(Schema $schema): Schema
    {
        return ComboForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CombosTable::configure($table);
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
            'index' => ListCombos::route('/'),
            'create' => CreateCombo::route('/create'),
            'edit' => EditCombo::route('/{record}/edit'),
        ];
    }

    // public static  function canAccess(): bool
    // {
    //     return Auth::user()->hasRole(UserRole::ADMIN);
    // }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
        ->with(['products', 'createdBy', 'updatedBy'])
            ->withCount('products');
        $currentUser = Auth::user();

        if ($currentUser && $currentUser->hasRole(UserRole::SUPER_ADMIN)) {
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
