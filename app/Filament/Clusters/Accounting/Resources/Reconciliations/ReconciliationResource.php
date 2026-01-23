<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations;

use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Accounting\Resources\Reconciliations\Pages\ListReconciliations;
use App\Filament\Clusters\Accounting\Resources\Reconciliations\Schemas\ReconciliationForm;
use App\Filament\Clusters\Accounting\Resources\Reconciliations\Tables\ReconciliationsTable;
use App\Models\Reconciliation;
use App\Utils\Helper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ReconciliationResource extends Resource
{
    protected static ?string $model = Reconciliation::class;

    protected static string|BackedEnum|null $navigationIcon = '';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_accounting');
    }

    public static function getModelLabel(): string
    {
        return __('accounting.reconciliation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accounting.reconciliation.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('accounting.reconciliation.navigation_label');
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
        return ReconciliationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReconciliationsTable::configure($table);
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
            'index' => ListReconciliations::route('/'),
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

