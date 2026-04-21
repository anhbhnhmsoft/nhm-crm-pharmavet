<?php

namespace App\Filament\Clusters\Accounting\Resources\Revenues;

use App\Common\Constants\GateKey;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Accounting\Resources\Revenues\Pages\ListRevenues;
use App\Filament\Clusters\Accounting\Resources\Revenues\Schemas\RevenueForm;
use App\Filament\Clusters\Accounting\Resources\Revenues\Tables\RevenuesTable;
use App\Models\Revenue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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
        return __('accounting.revenue.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accounting.revenue.resource_plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('accounting.revenue.resource_navigation_label');
    }

    public static function canAccess(): bool
    {
        return Gate::allows(GateKey::IS_SUPER_ADMIN->name)
            || Gate::allows(GateKey::IS_ADMIN->name)
            || Gate::allows(GateKey::HAS_ROLE->name, [UserRole::ACCOUNTING]);
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
        $query = parent::getEloquentQuery();

        $organizationId = Auth::user()->organization_id;
        return $query->where('organization_id', $organizationId);
    }
}
