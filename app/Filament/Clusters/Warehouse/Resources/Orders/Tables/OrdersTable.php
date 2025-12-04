<?php

namespace App\Filament\Clusters\Warehouse\Resources\Orders\Tables;

use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Order\PaymentType;
use App\Common\Constants\Order\ServiceType;
use App\Common\Constants\Shipping\RequiredNote;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('order.table.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip(__('order.table.click_to_copy')),

                TextColumn::make('customer.username')
                    ->label(__('order.table.customer'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.phone')
                    ->label(__('order.table.customer_phone'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('warehouse.name')
                    ->label(__('order.table.warehouse'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_amount')
                    ->label(__('order.table.total_amount'))
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('shipping_fee')
                    ->label(__('order.table.shipping_fee'))
                    ->money('VND')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('deposit')
                    ->label(__('order.table.deposit'))
                    ->money('VND')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label(__('order.table.status'))
                    ->badge()
                    ->color(fn(int $state): string => OrderStatus::color($state))
                    ->formatStateUsing(fn(int $state): string => OrderStatus::getLabel($state))
                    ->sortable(),

                TextColumn::make('ghn_order_code')
                    ->label(__('order.table.ghn_order_code'))
                    ->searchable()
                    ->copyable()
                    ->toggleable()
                    ->placeholder(__('order.table.not_posted')),

                TextColumn::make('ghn_status')
                    ->label(__('order.table.ghn_status'))
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'ready_to_pick' => 'info',
                        'picking' => 'warning',
                        'delivering' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable()
                    ->placeholder(__('order.table.not_posted')),

                TextColumn::make('ghn_posted_at')
                    ->label(__('order.table.ghn_posted_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('shipping_address')
                    ->label(__('order.table.shipping_address'))
                    ->limit(30)
                    ->tooltip(fn($record) => $record->shipping_address)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->label(__('order.table.created_at'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('createdBy.name')
                    ->label(__('order.table.created_by'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('order.filter.status'))
                    ->options(OrderStatus::toOptions())
                    ->multiple(),

                SelectFilter::make('warehouse_id')
                    ->label(__('order.filter.warehouse'))
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('ghn_status')
                    ->label(__('order.filter.ghn_status'))
                    ->options([
                        'ready_to_pick' => __('order.ghn_status.ready_to_pick'),
                        'picking' => __('order.ghn_status.picking'),
                        'delivering' => __('order.ghn_status.delivering'),
                        'delivered' => __('order.ghn_status.delivered'),
                        'cancelled' => __('order.ghn_status.cancelled'),
                    ])
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(fn(Order $record) => in_array($record->status, [
                        OrderStatus::PENDING->value,
                        OrderStatus::CONFIRMED->value
                    ])),

                Action::make('post_order')
                    ->label(__('order.action.post_order'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn(Order $record) => $record->status == OrderStatus::CONFIRMED->value)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('shipping_fee')
                                    ->label(__('order.form.shipping_fee'))
                                    ->numeric()
                                    ->required()
                                    ->default(fn(Order $record) => $record->shipping_fee)
                                    ->prefix('VND')
                                    ->minValue(1000)
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 1000]),
                                    ])
                                    ->suffixAction(
                                        Action::make('calculate_fee')
                                            ->icon('heroicon-o-calculator')
                                            ->tooltip(__('order.action.calculate_fee'))
                                            ->action(function ($set, $get, Order $record) {
                                                $data = [
                                                    'weight' => $get('weight'),
                                                    'ghn_service_type_id' => $get('ghn_service_type_id'),
                                                    'insurance_value' => $get('insurance_value'),
                                                    'length' => $get('length'),
                                                    'width' => $get('width'),
                                                    'height' => $get('height'),
                                                ];

                                                $orderService = app(OrderService::class);
                                                $result = $orderService->calculateShippingFee($record, $data);

                                                if ($result->isSuccess()) {
                                                    $feeData = $result->getData();
                                                    $set('shipping_fee', $feeData['total'] ?? 0);

                                                    Notification::make()
                                                        ->title(__('order.notification.fee_calculated'))
                                                        ->success()
                                                        ->send();
                                                } else {
                                                    Notification::make()
                                                        ->title(__('order.notification.calculate_fee_failed'))
                                                        ->body($result->getMessage())
                                                        ->danger()
                                                        ->send();
                                                }
                                            })
                                    ),

                                TextInput::make('weight')
                                    ->label(__('order.form.weight'))
                                    ->numeric()
                                    ->default(fn(Order $record) => $record->weight ?? 200)
                                    ->suffix('gram')
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                    ]),

                                Select::make('ghn_service_type_id')
                                    ->label(__('order.form.ghn_service_type'))
                                    ->options(ServiceType::toOptions())
                                    ->default(fn(Order $record) => $record->ghn_service_type_id ?? 2)
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                Select::make('ghn_payment_type_id')
                                    ->label(__('order.form.ghn_payment_type'))
                                    ->options(PaymentType::toOptions())
                                    ->default(fn(Order $record) => $record->ghn_payment_type_id ?? 2)
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                Select::make('required_note')
                                    ->label(__('order.form.required_note'))
                                    ->options(RequiredNote::getOptions())
                                    ->default(fn(Order $record) => $record->required_note ?? RequiredNote::ALLOW_VIEWING_NOT_TRIAL->value)
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                TextInput::make('insurance_value')
                                    ->label(__('order.form.insurance_value'))
                                    ->numeric()
                                    ->default(fn(Order $record) => $record->insurance_value)
                                    ->prefix('VND')
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                    ]),
                            ]),
                    ])
                    ->action(function (Order $record, array $data) {
                        $orderService = app(OrderService::class);
                        $result = $orderService->postOrder($record, $data);

                        if ($result->isSuccess()) {
                            Notification::make()
                                ->title(__('order.notification.post_order_queued'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('order.notification.post_order_failed'))
                                ->body($result->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('cancel_post')
                    ->label(__('order.action.cancel_post'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Order $record) => $record->status == OrderStatus::SHIPPING->value)
                    ->requiresConfirmation()
                    ->modalHeading(__('order.modal.cancel_post_title'))
                    ->modalDescription(__('order.modal.cancel_post_description'))
                    ->modalSubmitActionLabel(__('order.action.confirm_cancel'))
                    ->action(function (Order $record) {
                        $orderService = app(OrderService::class);
                        $result = $orderService->cancelOrder($record);

                        if ($result->isSuccess()) {
                            Notification::make()
                                ->title(__('order.notification.cancel_order_queued'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('order.notification.cancel_order_failed'))
                                ->body($result->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
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
