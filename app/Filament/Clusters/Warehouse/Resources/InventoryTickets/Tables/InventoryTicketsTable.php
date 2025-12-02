<?php

namespace App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Tables;

use App\Common\Constants\Warehouse\StatusTicket;
use App\Common\Constants\Warehouse\TypeTicket;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class InventoryTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('warehouse.ticket.form.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label(__('warehouse.ticket.form.type'))
                    ->badge()
                    ->formatStateUsing(fn($state) => TypeTicket::from($state)->getLabel())
                    ->color(fn($state) => TypeTicket::from($state)->getColor())
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('warehouse.ticket.form.status'))
                    ->badge()
                    ->formatStateUsing(fn($state) => StatusTicket::from($state)->getLabel())
                    ->color(fn($state) => StatusTicket::from($state)->getColor())
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label(__('warehouse.ticket.form.warehouse'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('sourceWarehouse.name')
                    ->label(__('warehouse.ticket.form.source_warehouse'))
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('targetWarehouse.name')
                    ->label(__('warehouse.ticket.form.target_warehouse'))
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('details_count')
                    ->label(__('Số SP'))
                    ->counts('details')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('note')
                    ->label(__('warehouse.ticket.form.note'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('status')
                    ->label(__('warehouse.ticket.form.status'))
                    ->badge()
                    ->formatStateUsing(fn($state) => StatusTicket::from($state)->getLabel())
                    ->color(fn($state) => StatusTicket::from($state)->getColor())
                    ->sortable(),


                TextColumn::make('createdBy.name')
                    ->label(__('common.column.created_by'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approvedBy.name')
                    ->label(__('warehouse.ticket.form.approved_by'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                TextColumn::make('approved_at')
                    ->label(__('warehouse.ticket.form.approved_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label(__('warehouse.ticket.form.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label(__('warehouse.ticket.form.updated_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('warehouse.ticket.form.type'))
                    ->options(TypeTicket::toArray())
                    ->native(false),

                SelectFilter::make('status')
                    ->label(__('warehouse.ticket.form.status'))
                    ->options(StatusTicket::toArray())
                    ->native(false),

                SelectFilter::make('warehouse_id')
                    ->label(__('warehouse.ticket.form.warehouse'))
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label(__('common.action.view'))
                        ->icon('heroicon-o-eye'),

                    EditAction::make()
                        ->label(__('common.action.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->visible(fn($record) => $record->status === StatusTicket::DRAFT->value && !$record->trashed()),

                    DeleteAction::make()
                        ->label(__('common.action.delete'))
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->visible(fn($record) => $record->status === StatusTicket::DRAFT->value && !$record->trashed()),

                    RestoreAction::make()
                        ->label(__('common.action.restore'))
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn($record) => $record->trashed()),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('common.action.delete'))
                        ->requiresConfirmation(),

                    RestoreBulkAction::make()
                        ->label(__('common.action.restore')),

                    ForceDeleteBulkAction::make()
                        ->label(__('common.action.force_delete'))
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
