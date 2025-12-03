<?php

namespace App\Filament\Clusters\Warehouse\Resources\InventoryTickets;

use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages\CreateInventoryTicket;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages\EditInventoryTicket;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages\ListInventoryTickets;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Schemas\InventoryTicketForm;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Tables\InventoryTicketsTable;
use App\Filament\Clusters\Warehouse\WarehouseCluster;
use App\Models\InventoryTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InventoryTicketResource extends Resource
{
    protected static ?string $model = InventoryTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_warehouse');
    }

    public static function getModelLabel(): string
    {
        return __('warehouse.ticket.primary');
    }

    public static function getPluralModelLabel(): string
    {
        return __('warehouse.ticket.primary');
    }

    public static function getNavigationLabel(): string
    {
        return __('warehouse.ticket.primary');
    }


    public static function form(Schema $schema): Schema
    {
        return InventoryTicketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryTicketsTable::configure($table);
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
            'index' => ListInventoryTickets::route('/'),
            'create' => CreateInventoryTicket::route('/create'),
            'edit' => EditInventoryTicket::route('/{record}/edit'),
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
