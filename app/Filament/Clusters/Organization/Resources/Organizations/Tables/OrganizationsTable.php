<?php

namespace App\Filament\Clusters\Organization\Resources\Organizations\Tables;

use Filament\Actions\ActionGroup;
use App\Common\Constants\Organization\ProductField;
use Filament\Actions\Action;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.table.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label(__('common.table.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product_field')
                    ->label(__('filament.organization.table.product_field'))
                    ->formatStateUsing(fn($state) => ProductField::getLabel($state))
                    ->sortable(),

                TextColumn::make('maximum_employees')
                    ->label(__('filament.organization.form.maximum_employees'))
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label(__('filament.organization.table.quantity_members'))
                    ->counts('users')
                    ->sortable(),

                IconColumn::make('disable')
                    ->label(__('filament.organization.form.status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                TextColumn::make('deleted_at')
                    ->label(__('common.table.deleted_at'))
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_field')
                    ->label(__('filament.organization.table.product_field'))
                    ->options(ProductField::toOptions()),

                TernaryFilter::make('disable')
                    ->label(__('common.status.label'))
                    ->trueLabel(__('common.status.disabled'))
                    ->falseLabel(__('common.status.enabled'))
                    ->nullable(),

                TrashedFilter::make()
                    ->label(__('common.table.trashed')),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('list-member')
                        ->label(__('filament.organization.table.list_member'))
                        ->tooltip(__('common.tooltip.view'))
                        ->icon('heroicon-o-ellipsis-horizontal')
                        ->action(fn($record) => redirect(route(
                            'filament.admin.resources.users.index',
                            [
                                'filters' => [
                                    'organization_id' => [
                                        'value' => $record->id
                                    ]
                                ]
                            ]
                        ))),
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
            ], position: \Filament\Tables\Enums\RecordActionsPosition::BeforeColumns)

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
            ]);
    }
}
