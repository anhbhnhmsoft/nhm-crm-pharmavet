<?php

namespace App\Filament\Clusters\Organization\Resources\Organizations;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Organization\OrganizationCluster;
use App\Filament\Clusters\Organization\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Clusters\Organization\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Clusters\Organization\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Clusters\Organization\Resources\Organizations\Schemas\OrganizationForm;
use App\Filament\Clusters\Organization\Resources\Organizations\Tables\OrganizationsTable;
use App\Models\Organization;
use App\Utils\Helper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = OrganizationCluster::class;

    public static  function canAccess(): bool
    {
        return Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
        ], Auth::user()->role);
    }

    public static function getModelLabel(): string
    {
        return __('filament.organization.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.organization.label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.organization.cluster_label');
    }

    public static function form(Schema $schema): Schema
    {
        return OrganizationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganizationsTable::configure($table);
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
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'edit' => EditOrganization::route('/{record}/edit'),
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
