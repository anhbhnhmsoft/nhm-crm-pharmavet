<?php

namespace App\Filament\Clusters\Warehouse\Resources\InventoryTickets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventoryTicketLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    public static function getTitle($ownerRecord, $pageClass): string
    {
        return __('warehouse.ticket.logs.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('warehouse.ticket.logs.created_at'))
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('action')
                    ->label(__('warehouse.ticket.logs.action'))
                    ->formatStateUsing(fn (?string $state): string => __('warehouse.ticket.logs.actions.' . $state) !== 'warehouse.ticket.logs.actions.' . $state
                        ? __('warehouse.ticket.logs.actions.' . $state)
                        : (string) $state)
                    ->badge(),
                TextColumn::make('product.name')
                    ->label(__('warehouse.ticket.logs.product'))
                    ->default('-')
                    ->wrap(),
                TextColumn::make('reason')
                    ->label(__('warehouse.ticket.logs.reason'))
                    ->formatStateUsing(fn (?string $state): string => $state && __('warehouse.ticket.reason_codes.' . $state) !== 'warehouse.ticket.reason_codes.' . $state
                        ? __('warehouse.ticket.reason_codes.' . $state)
                        : ($state ?: '-'))
                    ->wrap(),
                TextColumn::make('note')
                    ->label(__('warehouse.ticket.logs.note'))
                    ->default('-')
                    ->wrap(),
                TextColumn::make('old_status')
                    ->label(__('warehouse.ticket.logs.old_status'))
                    ->formatStateUsing(fn ($state): string => $state ? __('warehouse.status_ticket.' . match ((int) $state) {
                        1 => 'draft',
                        2 => 'completed',
                        3 => 'cancelled',
                        default => 'draft',
                    }) : '-')
                    ->badge(),
                TextColumn::make('new_status')
                    ->label(__('warehouse.ticket.logs.new_status'))
                    ->formatStateUsing(fn ($state): string => $state ? __('warehouse.status_ticket.' . match ((int) $state) {
                        1 => 'draft',
                        2 => 'completed',
                        3 => 'cancelled',
                        default => 'draft',
                    }) : '-')
                    ->badge(),
                TextColumn::make('user.name')
                    ->label(__('warehouse.ticket.logs.user'))
                    ->default('-')
                    ->wrap(),
            ])
            ->emptyStateHeading(__('warehouse.ticket.logs.empty_state_heading'))
            ->emptyStateDescription(__('warehouse.ticket.logs.empty_state_description'))
            ->defaultSort('created_at', 'desc');
    }
}
