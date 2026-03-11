<?php

namespace App\Filament\Clusters\Telesale\Resources\CustomerOperations;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Telesale\Resources\CustomerOperations\Pages\ListCustomerOperations;
use App\Filament\Clusters\Telesale\Resources\CustomerOperations\Tables\CustomerOperationsTable;
use App\Filament\Clusters\Telesale\Resources\CustomerOperations\Schemas\CustomerOperationForm;
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

class CustomerOperationResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = '';

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

    public static function form(Schema $schema): Schema
    {
        return CustomerOperationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerOperationsTable::configure($table);
    }

    public static function canAccess(): bool
    {
        return Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::SALE->value,
        ], Auth::user()->role);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $user = Auth::user();

        // Xem hết
        if ($user->role === UserRole::SUPER_ADMIN->value) {
            return $query;
        }

        // Xem vừa
        if ($user->role === UserRole::ADMIN->value) {
            return $query->where('organization_id', $user->organization_id);
        }

        // Xem ít
        $userId = $user->id;
        return $query->where(function (Builder $subQuery) use ($userId) {
            $subQuery->where('assigned_staff_id', $userId)
                ->orWhereHas('assignedStaff', function (Builder $relQuery) use ($userId) {
                    $relQuery->where('user_assigned_staff.staff_id', $userId);
                });
        });
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
