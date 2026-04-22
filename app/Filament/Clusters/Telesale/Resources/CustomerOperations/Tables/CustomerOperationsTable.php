<?php

namespace App\Filament\Clusters\Telesale\Resources\CustomerOperations\Tables;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Telesale\Resources\CustomerOperations\Actions\InteractionStepActions;
use App\Filament\Clusters\Telesale\Resources\CustomerOperations\Schemas\FinalizeOrderActionForm;
use App\Services\Telesale\TelesaleFinalizeOrderService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CustomerOperationsTable
{
    protected static function getLatestOrder($record)
    {
        return app(TelesaleFinalizeOrderService::class)->getLatestOrder($record);
    }

    protected static function getLatestOrderStatus($record): ?int
    {
        return app(TelesaleFinalizeOrderService::class)->getLatestOrderStatus($record);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->recordClasses(
                fn($record) => $record->orders()->where('status', OrderStatus::PENDING->value)->exists()
                    ? 'bg-red-50 dark:bg-red-900/10'
                    : null
            )
            ->columns([
                TextColumn::make('id')
                    ->label(__('telesale.table.data_code'))
                    ->searchable()
                    ->sortable()
                    ->size('sm')
                    ->weight('bold'),
                TextColumn::make('organization.name')
                    ->label(__('telesale.table.organization'))
                    ->visible(fn() => Auth::user()->role === UserRole::SUPER_ADMIN->value)
                    ->searchable()
                    ->sortable()
                    ->size('sm'),
                TextColumn::make('username')
                    ->label(__('telesale.table.customer_name'))
                    ->searchable(['username', 'phone'])
                    ->size('sm')
                    ->weight('medium'),
                TextColumn::make('latest_order_status')
                    ->label(__('telesale.customer360.latest_order_status'))
                    ->state(fn($record) => self::getLatestOrderStatus($record))
                    ->badge()
                    ->color(fn($state) => $state ? OrderStatus::color((int) $state) : 'gray')
                    ->formatStateUsing(fn($state) => $state ? OrderStatus::getLabel((int) $state) : '-')
                    ->description(fn($record) => self::getLatestOrder($record)?->code)
                    ->size('sm'),
                TextColumn::make('source')
                    ->label(__('telesale.table.source'))
                    ->badge()
                    ->color(fn($state) => IntegrationType::tryFrom((int) $state)?->color() ?? 'gray')
                    ->formatStateUsing(fn($state) => IntegrationType::getLabel((int) $state))
                    ->size('sm'),
                TextColumn::make('customer_type')
                    ->label(__('telesale.table.customer_type'))
                    ->badge()
                    ->color(fn(int $state): string => CustomerType::colors($state))
                    ->formatStateUsing(fn($state) => CustomerType::getLabel($state))
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
                TextColumn::make('interaction_status')
                    ->label(__('telesale.table.interaction_status'))
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? InteractionStatus::getLabelStatus((int) $state) : '-')
                    ->size('sm'),
                TextColumn::make('blackList')
                    ->label(__('telesale.table.blacklist'))
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn($state) => $state ? __('telesale.table.blacklist') : __('telesale.table.unblacklist'))
                    ->size('sm'),
            ])
            ->filters([
                SelectFilter::make('customer_type')
                    ->label(__('telesale.table.customer_type'))
                    ->options(CustomerType::toOptions()),
                SelectFilter::make('source')
                    ->label(__('telesale.table.source'))
                    ->options(IntegrationType::toOptions()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    FinalizeOrderActionForm::make(),
                    ViewAction::make()
                        ->label(__('common.action.view'))
                        ->tooltip(__('common.tooltip.view'))
                        ->icon('heroicon-o-eye'),
                    Action::make('blacklist')
                        ->label(__('telesale.table.blacklist'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->form([
                            Textarea::make('note')
                                ->label(__('common.table.note'))
                                ->required()
                                ->validationMessages([
                                    'required' => __('common.error.required'),
                                ]),
                        ])
                        ->action(function ($record, array $data) {
                            $record->blackList()->create([
                                'note' => $data['note'],
                                'user_id' => Auth::id(),
                            ]);

                            Notification::make()->title(__('common.success.update_success'))->success()->send();
                        })
                        ->visible(fn($record) => ! $record->blackList),
                    Action::make('unblacklist')
                        ->label(__('telesale.table.unblacklist'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->blackList()->delete();
                            Notification::make()->title(__('common.success.update_success'))->success()->send();
                        })
                        ->visible(fn($record) => (bool) $record->blackList),
                    ...InteractionStepActions::make(),
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
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->persistFiltersInSession()
            ->poll('30s');
    }
}
