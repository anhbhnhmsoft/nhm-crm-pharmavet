<?php

namespace App\Filament\Clusters\Warehouse\Resources\Orders\RelationManagers;

use App\Common\Constants\Order\OrderStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderStatusLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'statusLogs';

    public static function getTitle($ownerRecord, $pageClass): string
    {
        return __('warehouse.order.logs.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('warehouse.order.logs.created_at'))
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('from_status')
                    ->label(__('warehouse.order.logs.from_status'))
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? OrderStatus::getLabel((int) $state)
                        : '-')
                    ->badge(),
                TextColumn::make('to_status')
                    ->label(__('warehouse.order.logs.to_status'))
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? OrderStatus::getLabel((int) $state)
                        : '-')
                    ->badge(),
                TextColumn::make('note')
                    ->label(__('warehouse.order.logs.note'))
                    ->default('-')
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label(__('warehouse.order.logs.user'))
                    ->default('-')
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
