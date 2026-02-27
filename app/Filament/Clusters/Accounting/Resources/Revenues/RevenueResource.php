<?php

namespace App\Filament\Clusters\Accounting\Resources\Revenues;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Accounting\Resources\Revenues\Pages\ListRevenues;
use App\Filament\Clusters\Accounting\Resources\Revenues\Schemas\RevenueForm;
use App\Filament\Clusters\Accounting\Resources\Revenues\Tables\RevenuesTable;
use App\Models\Revenue;
use App\Utils\Helper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class RevenueResource extends Resource
{
    protected static ?string $model = Revenue::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_accounting');
    }

    public static function getModelLabel(): string
    {
        return __('accounting.revenue.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accounting.revenue.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('accounting.revenue.navigation_label');
    }

    public static function canAccess(): bool
    {
        return Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::ACCOUNTING->value,
        ], Auth::user()->role);
    }

    public static function form(Schema $schema): Schema
    {
        return RevenueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RevenuesTable::configure($table);
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
            'index' => ListRevenues::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $organizationId = Auth::user()->organization_id;
        return $query->where('organization_id', $organizationId);
    }
}
