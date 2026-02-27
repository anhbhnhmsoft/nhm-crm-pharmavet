<?php

namespace App\Filament\Clusters\Organization\Resources\Teams;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Organization\OrganizationCluster;
use App\Filament\Clusters\Organization\Resources\Teams\Pages\CreateTeam;
use App\Filament\Clusters\Organization\Resources\Teams\Pages\EditTeam;
use App\Filament\Clusters\Organization\Resources\Teams\Pages\ListTeams;
use App\Filament\Clusters\Organization\Resources\Teams\Schemas\TeamForm;
use App\Filament\Clusters\Organization\Resources\Teams\Tables\TeamsTable;
use App\Models\Team;
use App\Utils\Helper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = '';

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_administration');
    }
    public static function getModelLabel(): string
    {
        return __('filament.team.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.team.label');
    }

    public static function form(Schema $schema): Schema
    {
        return TeamForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeamsTable::configure($table);
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
            'index' => ListTeams::route('/'),
            'create' => CreateTeam::route('/create'),
            'edit' => EditTeam::route('/{record}/edit'),
        ];
    }

    public static  function canAccess(): bool
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
