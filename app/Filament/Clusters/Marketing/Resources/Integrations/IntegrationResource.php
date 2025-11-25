<?php

namespace App\Filament\Clusters\Marketing\Resources\Integrations;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Clusters\Marketing\Resources\Integrations\Pages\CreateIntegration;
use App\Filament\Clusters\Marketing\Resources\Integrations\Pages\EditIntegration;
use App\Filament\Clusters\Marketing\Resources\Integrations\Pages\ListIntegrations;
use App\Filament\Clusters\Marketing\Resources\Integrations\Schemas\IntegrationForm;
use App\Filament\Clusters\Marketing\Resources\Integrations\Tables\IntegrationsTable;
use App\Models\Integration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class IntegrationResource extends Resource
{
    protected static ?string $model = Integration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = MarketingCluster::class;

    protected static ?string $recordTitleAttribute = 'Integration';

    public static function form(Schema $schema): Schema
    {
        return IntegrationForm::configure($schema);
    }

    public static function getModelLabel(): string
    {
        return __('filament.integration.plural_model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.integration.model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.integration.navigation_label');
    }

    public static function table(Table $table): Table
    {
        return IntegrationsTable::configure($table);
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
            'index' => ListIntegrations::route('/'),
            'create' => CreateIntegration::route('/create'),
            'edit' => EditIntegration::route('/{record}/edit'),
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
