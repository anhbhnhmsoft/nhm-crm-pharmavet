<?php

namespace App\Filament\Clusters\Organization\Resources\Organizations;

use App\Common\Constants\GateKey;
use App\Filament\Clusters\Organization\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Clusters\Organization\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Clusters\Organization\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Clusters\Organization\Resources\Organizations\Schemas\OrganizationForm;
use App\Filament\Clusters\Organization\Resources\Organizations\Tables\OrganizationsTable;
use App\Models\Organization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Gate;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|BackedEnum|null $navigationIcon = '';

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_administration');
    }

    public static  function canAccess(): bool
    {
        return Gate::allows(GateKey::IS_SUPER_ADMIN);
    }

    public static function getModelLabel(): string
    {
        return __('organization.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('organization.label');
    }

    public static function getNavigationLabel(): string
    {
        return __('organization.cluster_label');
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
