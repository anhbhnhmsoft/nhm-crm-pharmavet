<?php

namespace App\Filament\Clusters\Marketing\Resources\Integrations\Tables;

use App\Common\Constants\Marketing\IntegrationEntityType;
use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\StatusConnect;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class IntegrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization.name')
                    ->label(__('filament.organization.cluster_label'))
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('filament.integration.table.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('filament.integration.table.type'))
                    ->formatStateUsing(fn ($state) => IntegrationType::getLabel((int) $state))
                    ->badge()
                    ->color(fn ($state) => IntegrationType::tryFrom((int) $state)?->color() ?? 'gray')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('filament.integration.table.status'))
                    ->formatStateUsing(function ($state) {
                        return match ((int) $state) {
                            StatusConnect::CONNECTED->value => __('filament.integration.status.connected'),
                            StatusConnect::PENDING->value => __('filament.integration.status.pending'),
                            StatusConnect::ERROR->value => __('filament.integration.status.error'),
                            default => __('filament.integration.status.not_connected'),
                        };
                    })
                    ->badge()
                    ->color(function ($state) {
                        return match ((int) $state) {
                            StatusConnect::CONNECTED->value => 'success',
                            StatusConnect::PENDING->value => 'warning',
                            StatusConnect::ERROR->value => 'danger',
                            default => 'gray',
                        };
                    })
                    ->sortable(),

                TextColumn::make('pages')
                    ->label(__('filament.integration.table.pages'))
                    ->state(fn ($record) => $record->entities()
                        ->where('type', IntegrationEntityType::PAGE_META->value)
                        ->where('status', StatusConnect::CONNECTED->value)
                        ->count()),

                TextColumn::make('last_sync_at')
                    ->label(__('filament.integration.table.last_sync'))
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('filament.integration.table.created_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('filament.integration.filters.type'))
                    ->options(IntegrationType::toOptions()),

                SelectFilter::make('status')
                    ->label(__('filament.integration.filters.status'))
                    ->options([
                        StatusConnect::CONNECTED->value => __('filament.integration.status.connected'),
                        StatusConnect::PENDING->value => __('filament.integration.status.pending'),
                        StatusConnect::ERROR->value => __('filament.integration.status.error'),
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
                        ->visible(fn ($record) => ! $record->trashed()),

                    RestoreAction::make()
                        ->label(__('common.action.restore'))
                        ->tooltip(__('common.tooltip.restore'))
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn ($record) => $record->trashed()),
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
                        ->visible(fn ($livewire) => $livewire->tableFilters['trashed']['value'] ?? null === 'only'),

                    ForceDeleteBulkAction::make()
                        ->label(__('common.action.force_delete'))
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.force_delete_title'))
                        ->modalDescription(__('common.modal.force_delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete')),
                ]),
            ]);
    }
}
