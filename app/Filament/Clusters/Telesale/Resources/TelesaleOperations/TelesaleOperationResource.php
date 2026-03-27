<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Pages\CreateTelesaleOperation;
use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Pages\EditTelesaleOperation;
use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Pages\ListTelesaleOperations;
use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Schemas\RegistrationRequestForm;
use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Schemas\TelesaleOperationForm;
use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Tables\TelesaleOperationsTable;
use App\Filament\Clusters\Telesale\TelesaleCluster;
use App\Models\Customer;
use App\Utils\Helper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class TelesaleOperationResource extends Resource
{
    protected static ?string $model = \App\Models\Customer::class;

    protected static string|BackedEnum|null $navigationIcon = '';

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_telesale');
    }

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
        return $schema->components(function (Customer $record = null) {
            if ($record && $record->customer_type === CustomerType::PARTNER_REQUEST->value) {
                return RegistrationRequestForm::getComponents();
            }
            return TelesaleOperationForm::getComponents();
        });
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        if (Auth::user()->role === UserRole::SUPER_ADMIN->value) {
            return $query;
        }

        $organizationId = Auth::user()->organization_id;
        return $query->where('organization_id', $organizationId);
    }

    public static function canAccess(): bool
    {
        return Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::WAREHOUSE->value,
            UserRole::ACCOUNTING->value,
            UserRole::MARKETING->value,
            UserRole::SALE->value,
        ], Auth::user()->role);
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
