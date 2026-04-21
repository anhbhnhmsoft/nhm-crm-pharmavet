<?php

namespace App\Filament\Clusters\Warehouse\Resources\InventoryTickets;

use App\Common\Constants\GateKey;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\RelationManagers\InventoryTicketLogsRelationManager;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages\CreateInventoryTicket;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages\EditInventoryTicket;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages\ListInventoryTickets;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages\ViewInventoryTicket;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Schemas\InventoryTicketForm;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Tables\InventoryTicketsTable;
use App\Models\InventoryTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class InventoryTicketResource extends Resource
{
    protected static ?string $model = InventoryTicket::class;

    protected static string|BackedEnum|null $navigationIcon = '';

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

    public static function canAccess(): bool
    {
        return Gate::allows(GateKey::IS_SUPER_ADMIN->name)
            || Gate::allows(GateKey::IS_ADMIN->name)
            || Gate::allows(GateKey::HAS_ROLE->name, [UserRole::WAREHOUSE, UserRole::ACCOUNTING]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $organizationId = Auth::user()->organization_id;
        return $query
            ->where(function (Builder $subQuery) use ($organizationId) {
                $subQuery->where('organization_id', $organizationId);
            })
            ->withSum('details as product_quantity_sum', 'quantity');
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
            InventoryTicketLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryTickets::route('/'),
            'create' => CreateInventoryTicket::route('/create'),
            'view' => ViewInventoryTicket::route('/{record}'),
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
