<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Tables;

use App\Common\Constants\Marketing\IntegrationType;
use App\Models\Customer;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TelesaleOperationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('telesale.table.data_code'))
                    ->searchable()
                    ->sortable()
                    ->size('sm')
                    ->weight('bold'),

                TextColumn::make('username')
                    ->label(__('telesale.table.customer_name'))
                    ->description(fn(Customer $record) => $record->phone)
                    ->searchable(['username', 'phone'])
                    ->size('sm')
                    ->weight('medium'),

                TextColumn::make('source')
                    ->label(__('telesale.table.source'))
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn(int $state) => IntegrationType::getLabel($state))
                    ->size('sm'),

                TextColumn::make('assignedStaff.name')
                    ->label(__('telesale.table.assigned_staff'))
                    ->sortable()
                    ->searchable()
                    ->placeholder(__('telesale.messages.unassigned'))
                    ->size('sm'),

                TextColumn::make('next_action_at')
                    ->label(__('telesale.table.next_action'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder(__('telesale.messages.no_schedule'))
                    ->color(fn($state) => $state && $state->isPast() ? 'danger' : 'success')
                    ->size('sm'),

                TextColumn::make('created_at')
                    ->label(__('telesale.table.date_received'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable()
                    ->size('sm'),
                TextColumn::make('blackList')
                    ->label(__('telesale.table.blacklist'))
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn($state) => $state ? __('telesale.table.blacklist') : __('telesale.table.unblacklist'))
                    ->size('sm'),
            ])
            ->filters([
                SelectFilter::make('assigned_staff_id')
                    ->label(__('telesale.filters.assigned_staff'))
                    ->options(User::pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('source')
                    ->label(__('telesale.filters.source'))
                    ->options(IntegrationType::toOptions()),

                SelectFilter::make('status')
                    ->label(__('telesale.filters.status'))
                    ->options([
                        'new' => __('telesale.status.new'),
                        'processing' => __('telesale.status.processing'),
                        'closed' => __('telesale.status.closed'),
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([

                    Action::make('blacklist')
                        ->label(__('telesale.actions.blacklist'))
                        ->action(function (Customer $record) {
                            $record->blackList()->create([
                                'user_id' => Auth::id(),
                                'customer_id' => $record->id,
                            ]);
                        })
                        ->color('danger')
                        ->visible(fn(Customer $record) => !$record->blackList()->exists()),
                    Action::make('unblacklist')
                        ->label(__('telesale.actions.unblacklist'))
                        ->action(function (Customer $record) {
                            $record->blackList()->delete();
                        })
                        ->color('success')
                        ->visible(fn(Customer $record) => $record->blackList()->exists()),

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
                ]),
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
