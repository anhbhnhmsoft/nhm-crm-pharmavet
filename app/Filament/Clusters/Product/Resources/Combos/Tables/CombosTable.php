<?php

namespace App\Filament\Clusters\Product\Resources\Combos\Tables;

use Filament\Actions;
use Filament\Tables;
use App\Common\Constants\Product\StatusCombo;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;

class CombosTable
{
    public static function configure(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('filament.combo.code'))
                    ->badge()
                    ->color('primary')
                    ->copyable()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label(__('filament.combo.name'))
                    ->sortable()
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn($state) => strlen($state) > 50 ? $state : null),

                // TextColumn::make('total_product')
                //     ->label(__('filament.combo.total_product'))
                //     ->badge()
                //     ->sortable()
                //     ->color(fn(int $state): string => match (true) {
                //         $state < 2 => 'warning',
                //         $state >= 5 => 'success',
                //         default => 'info',
                //     }),

                TextColumn::make('total_cost')
                    ->label(__('filament.combo.total_cost'))
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('total_combo_price')
                    ->label(__('filament.combo.total_combo_price'))
                    ->money('VND')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('filament.combo.status'))
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        StatusCombo::ACTIVE->value => 'success',
                        StatusCombo::EXPIRED->value => 'danger',
                        StatusCombo::UPCOMING->value => 'warning',
                    })
                    ->formatStateUsing(function ($state) {
                        return StatusCombo::getLabel((int) $state);
                    }),

                TextColumn::make('total_product')
                    ->label(__('filament.combo.total_product'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label(__('filament.combo.created_by'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('filament.combo.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updatedBy.name')
                    ->label(__('filament.combo.updated_by'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('filament.combo.updated_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('filament.combo.status'))
                    ->options([
                        StatusCombo::getOptions()
                    ]),
                TrashedFilter::make()
                    ->label(__('common.table.trashed')),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label(__('common.action.view'))
                        ->tooltip(__('common.tooltip.view'))
                        ->icon('heroicon-o-eye'),

                    EditAction::make()
                        ->label(__('common.action.edit'))
                        ->tooltip(__('common.tooltip.edit'))
                        ->icon('heroicon-o-pencil-square'),

                    DeleteAction::make()
                        ->label(__('common.action.delete'))
                        ->tooltip(__('common.tooltip.delete'))
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.delete_title'))
                        ->modalDescription(__('common.modal.delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete'))
                        ->visible(fn($record) => ! $record->trashed()),

                    RestoreAction::make()
                        ->label(__('common.action.restore'))
                        ->tooltip(__('common.tooltip.restore'))
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn($record) => $record->trashed()),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('common.action.delete'))
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.delete_title'))
                        ->modalDescription(__('common.modal.delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete')),

                    RestoreBulkAction::make()
                        ->label(__('common.action.restore'))
                        ->visible(fn($livewire) => $livewire->tableFilters['trashed']['value'] ?? null === 'only'),

                    ForceDeleteBulkAction::make()
                        ->label(__('common.action.force_delete'))
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.force_delete_title'))
                        ->modalDescription(__('common.modal.force_delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete')),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->persistFiltersInSession()
            ->poll('30s');
    }
}
