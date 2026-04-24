<?php

namespace App\Filament\Clusters\Organization\Resources\Shifts\Tables;

use App\Common\Constants\User\UserRole;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.table.name'))
                    ->sortable(),
                TextColumn::make('organization.name')
                    ->label(__('filament.organization.label'))
                    ->sortable()
                    ->visible(fn () => Auth::user()?->role === UserRole::SUPER_ADMIN->value),
                TextColumn::make('start_time')
                    ->label(__('filament.shift.table.start_time'))
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('end_time')
                    ->label(__('filament.shift.table.end_time'))
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('common.table.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('common.table.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label(__('common.table.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()
                    ->label(__('common.table.trashed')),
            ])
            ->recordActions([
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
                        ->before(function ($record, DeleteAction $action) {
                            if ($record->hasAssignedUsers()) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title(__('filament.shift.notifications.delete_failed.title'))
                                    ->body(__('filament.shift.notifications.delete_failed.body'))
                                    ->send();
                                $action->cancel();
                            }
                        })
                        ->visible(fn($record) => ! $record->trashed()),

                    RestoreAction::make()
                        ->label(__('common.action.restore'))
                        ->tooltip(__('common.tooltip.restore'))
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn($record) => $record->trashed()),
                ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('common.action.delete'))
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.delete_title'))
                        ->modalDescription(__('common.modal.delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete'))
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records, DeleteBulkAction $action) {
                            foreach ($records as $record) {
                                if ($record->hasAssignedUsers()) {
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title(__('filament.shift.notifications.delete_failed.title'))
                                        ->body(__('filament.shift.notifications.delete_failed.bulk_body'))
                                        ->send();
                                    $action->cancel();
                                }
                            }
                        }),

                    RestoreBulkAction::make()
                        ->label(__('common.action.restore'))
                        ->visible(fn($livewire) => $livewire->tableFilters['trashed']['value'] ?? null === 'only'),

                    ForceDeleteBulkAction::make()
                        ->label(__('common.action.force_delete'))
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.force_delete_title'))
                        ->modalDescription(__('common.modal.force_delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete'))
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records, ForceDeleteBulkAction $action) {
                            foreach ($records as $record) {
                                if ($record->hasAssignedUsers()) {
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title(__('filament.shift.notifications.delete_failed.title'))
                                        ->body(__('filament.shift.notifications.delete_failed.bulk_body'))
                                        ->send();
                                    $action->cancel();
                                }
                            }
                        }),
                ]),
            ]);
    }
}
