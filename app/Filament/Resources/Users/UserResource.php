<?php

namespace App\Filament\Resources\Users;

use App\Common\Constants\User\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use App\Utils\Helper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string|BackedEnum|null $navigationIcon = '';
    protected static string|null|\UnitEnum $navigationGroup = 'unit_administration';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.unit_administration');
    }
    public static function getModelLabel(): string
    {
        return __('filament.user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.user.label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.user.label');
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
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $currentUser = Auth::user();

        if (!$currentUser->isSuperAdmin()) {
            $query->where('organization_id', $currentUser->organization_id);
        }

        // Hide SUPER_ADMIN users from the list as per original logic
        return $query->whereNotIn('role', [UserRole::SUPER_ADMIN->value])->withoutGlobalScopes([
            SoftDeletingScope::class,
        ]);
    }
}
