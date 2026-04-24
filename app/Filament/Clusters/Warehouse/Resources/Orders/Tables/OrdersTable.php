<?php

namespace App\Filament\Clusters\Warehouse\Resources\Orders\Tables;

use App\Common\Constants\Order\GhnOrderStatus;
use App\Common\Constants\Order\InvoiceStatus;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Order\PaymentType;
use App\Common\Constants\Order\ServiceType;
use App\Common\Constants\Shipping\RequiredNote;
use App\Models\Order;
use App\Services\OrderService;
use App\Utils\AccountingPeriodGuard;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Common\Constants\User\UserRole;
use Illuminate\Support\Facades\Auth;
class OrdersTable
{
    protected static function refreshTableAfterStatusChange(object $livewire, Order $record): void
    {
        $record->refresh();

        if (method_exists($livewire, 'resetTable')) {
            $livewire->resetTable();

            return;
        }

        if (method_exists($livewire, 'dispatch')) {
            $livewire->dispatch('$refresh');
        }
    }

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

                TextColumn::make('organization.name')
                    ->label(__('order.table.organization'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

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

                TextColumn::make('shipping_exception_reason_code')
                    ->label(__('warehouse.order.form.reason_code'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('redelivery_attempt')
                    ->label(__('warehouse.order.action.redelivery'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignCenter(),

                TextColumn::make('ghn_posted_at')
                    ->label(__('order.table.ghn_posted_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('invoice_code')
                    ->label(__('order.invoice_code'))
                    ->searchable()
                    ->sortable()
                    ->url(fn(Order $record): ?string => $record->invoice_url)
                    ->openUrlInNewTab()
                    ->icon(fn(Order $record): ?string => $record->invoice_url ? 'heroicon-o-arrow-top-right-on-square' : null)
                    ->placeholder(__('order.invoice_status_options.unissued'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('invoice_status')
                    ->label(__('order.invoice_status'))
                    ->badge()
                    ->color(fn(int $state): string => InvoiceStatus::tryFrom($state)?->getColor() ?? 'gray')
                    ->formatStateUsing(fn(int $state): string => InvoiceStatus::tryFrom($state)?->getLabel() ?? '')
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

                SelectFilter::make('organization_id')
                    ->label(__('order.filter.organization'))
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),
                    
                SelectFilter::make('invoice_status')
                    ->label(__('order.invoice_status'))
                    ->options(InvoiceStatus::toArray())
                    ->multiple(),
            ])
            ->recordActions([
                self::makePostOrderAction(),
                self::makeCancelPostAction(),
                self::makeRequestRedeliveryAction(),
                self::makePrintExportTicketAction(),
                ActionGroup::make([
                    ViewAction::make(),

                    EditAction::make()
                        ->visible(fn(Order $record) => in_array($record->status, [
                            OrderStatus::PENDING->value,
                            OrderStatus::CONFIRMED->value
                        ]))
                        ->disabled(fn(Order $record): bool => AccountingPeriodGuard::isClosedForRecord($record, 'created_at'))
                        ->tooltip(fn(Order $record): ?string => AccountingPeriodGuard::isClosedForRecord($record, 'created_at') ? __('accounting.accounting_period.period_closed') : null),
                    self::makeUpdateInvoiceAction(),
                ])
            ], position: RecordActionsPosition::BeforeColumns)
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

    protected static function makePostOrderAction(): Action
    {
        return Action::make('post_order')
            ->label(__('warehouse.order.action.post_order_handover'))
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->button()
            ->visible(fn(Order $record) => $record->status == OrderStatus::CONFIRMED->value)
            ->disabled(fn(Order $record): bool => AccountingPeriodGuard::isClosedForRecord($record, 'created_at'))
            ->tooltip(fn(Order $record): ?string => AccountingPeriodGuard::isClosedForRecord($record, 'created_at') ? __('accounting.accounting_period.period_closed') : null)
            ->modalSubmitAction(fn(Action $action) => $action->extraAttributes(['formnovalidate' => true]))
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
                            ->extraInputAttributes([
                                'type' => 'text',
                                'inputmode' => 'decimal',
                                'required' => false,
                                'min' => null,
                                'max' => null,
                                'step' => null,
                            ])
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
                            ->extraInputAttributes([
                                'type' => 'text',
                                'inputmode' => 'numeric',
                                'required' => false,
                                'min' => null,
                                'max' => null,
                                'step' => null,
                            ])
                            ->validationMessages([
                                'numeric' => __('common.error.numeric'),
                            ]),

                        Select::make('ghn_service_type_id')
                            ->label(__('order.form.ghn_service_type'))
                            ->options(ServiceType::toOptions())
                            ->default(fn(Order $record) => $record->ghn_service_type_id ?? 2)
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'in' => __('common.error.in', ['attribute' => __('order.form.ghn_service_type')]),
                            ]),

                        Select::make('ghn_payment_type_id')
                            ->label(__('order.form.ghn_payment_type'))
                            ->options(PaymentType::toOptions())
                            ->default(fn(Order $record) => $record->ghn_payment_type_id ?? 2)
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'in' => __('common.error.in', ['attribute' => __('order.form.ghn_payment_type')]),
                            ]),

                        Select::make('required_note')
                            ->label(__('order.form.required_note'))
                            ->options(RequiredNote::getOptions())
                            ->default(fn(Order $record) => $record->required_note ?? RequiredNote::ALLOW_VIEWING_NOT_TRIAL->value)
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'in' => __('common.error.in', ['attribute' => __('order.form.required_note')]),
                            ]),

                        TextInput::make('insurance_value')
                            ->label(__('order.form.insurance_value'))
                            ->numeric()
                            ->default(fn(Order $record) => $record->insurance_value)
                            ->prefix('VND')
                            ->extraInputAttributes([
                                'type' => 'text',
                                'inputmode' => 'decimal',
                                'required' => false,
                                'min' => null,
                                'max' => null,
                                'step' => null,
                            ])
                            ->validationMessages([
                                'numeric' => __('common.error.numeric'),
                            ]),
                    ]),
            ])
            ->action(function (Order $record, array $data, $livewire) {
                $orderService = app(OrderService::class);
                $result = $orderService->postOrder($record, $data);

                if ($result->isSuccess()) {
                    Notification::make()
                        ->title($result->getMessage())
                        ->success()
                        ->send();

                    self::refreshTableAfterStatusChange($livewire, $record);
                } else {
                    Notification::make()
                        ->title(__('order.notification.post_order_failed'))
                        ->body($result->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    protected static function makeCancelPostAction(): Action
    {
        return Action::make('cancel_post')
            ->label(__('order.action.cancel_post'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->button()
            ->visible(fn(Order $record) => $record->status == OrderStatus::SHIPPING->value)
            ->disabled(fn(Order $record): bool => AccountingPeriodGuard::isClosedForRecord($record, 'created_at'))
            ->tooltip(fn(Order $record): ?string => AccountingPeriodGuard::isClosedForRecord($record, 'created_at') ? __('accounting.accounting_period.period_closed') : null)
            ->requiresConfirmation()
            ->modalHeading(__('order.modal.cancel_post_title'))
            ->modalDescription(__('order.modal.cancel_post_description'))
            ->modalSubmitActionLabel(__('order.action.confirm_cancel'))
            ->action(function (Order $record, $livewire) {
                $orderService = app(OrderService::class);
                $result = $orderService->cancelOrder($record);

                if ($result->isSuccess()) {
                    Notification::make()
                        ->title($result->getMessage())
                        ->success()
                        ->send();

                    self::refreshTableAfterStatusChange($livewire, $record);
                } else {
                    Notification::make()
                        ->title(__('order.notification.cancel_order_failed'))
                        ->body($result->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    protected static function makeRequestRedeliveryAction(): Action
    {
        return Action::make('request_redelivery')
            ->label(__('warehouse.order.action.redelivery'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->button()
            ->visible(fn(Order $record) => in_array((string) $record->ghn_status, [
                GhnOrderStatus::DELIVERY_FAIL->value,
                GhnOrderStatus::WAITING_TO_RETURN->value,
                GhnOrderStatus::RETURN->value,
                GhnOrderStatus::RETURNING->value,
            ], true))
            ->disabled(fn(Order $record): bool => AccountingPeriodGuard::isClosedForRecord($record, 'created_at'))
            ->tooltip(fn(Order $record): ?string => AccountingPeriodGuard::isClosedForRecord($record, 'created_at') ? __('accounting.accounting_period.period_closed') : null)
            ->schema([
                Select::make('reason_code')
                    ->label(__('warehouse.order.form.reason_code'))
                    ->options(__('warehouse.shipping_exception'))
                    ->required()
                    ->native(false),
                Textarea::make('reason_note')
                    ->label(__('warehouse.order.form.reason_note'))
                    ->required()
                    ->rows(3),
                DateTimePicker::make('redelivery_schedule_at')
                    ->label(__('warehouse.order.form.redelivery_schedule_at'))
                    ->seconds(false)
                    ->required()
                    ->default(now()->addHours(2)),
            ])
            ->action(function (Order $record, array $data) {
                $orderService = app(OrderService::class);
                $result = $orderService->requestRedelivery($record, $data);

                if ($result->isSuccess()) {
                    Notification::make()
                        ->title(__('warehouse.order.action.redelivery'))
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title(__('warehouse.order.action.redelivery'))
                        ->body($result->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    protected static function makePrintExportTicketAction(): Action
    {
        return Action::make('print_export_ticket')
            ->label(__('warehouse.order.action.print_export_ticket'))
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->button()
            ->url(fn(Order $record): string => route('orders.export-ticket.print', ['order' => $record->id]))
            ->openUrlInNewTab()
            ->visible(fn(Order $record): bool => in_array((int) $record->status, [
                OrderStatus::CONFIRMED->value,
                OrderStatus::SHIPPING->value,
                OrderStatus::COMPLETED->value,
            ], true));
    }

    protected static function makeUpdateInvoiceAction(): Action
    {
        return Action::make('update_invoice')
            ->label(__('order.invoice_action.update_invoice'))
            ->icon('heroicon-o-document-text')
            ->color('info')
            ->visible(fn(Order $record) => in_array(Auth::user()->role, [UserRole::SUPER_ADMIN->value, UserRole::ADMIN->value, UserRole::ACCOUNTING->value]) && $record->status == OrderStatus::COMPLETED->value)
            ->disabled(fn(Order $record): bool => AccountingPeriodGuard::isClosedForRecord($record, 'created_at'))
            ->tooltip(fn(Order $record): ?string => AccountingPeriodGuard::isClosedForRecord($record, 'created_at') ? __('accounting.accounting_period.period_closed') : null)
            ->form([
                Select::make('invoice_status')
                    ->label(__('order.invoice_status'))
                    ->options(InvoiceStatus::toArray())
                    ->native(false)
                    ->required()
                    ->extraInputAttributes(['required' => false])
                    ->validationMessages([
                        'required' => __('common.error.required'),
                    ])
                    ->default(fn(Order $record) => $record->invoice_status),
                TextInput::make('invoice_code')
                    ->label(__('order.invoice_code'))
                    ->default(fn(Order $record) => $record->invoice_code),
                TextInput::make('invoice_url')
                    ->label(__('order.invoice_url'))
                    ->url()
                    ->default(fn(Order $record) => $record->invoice_url),
                DateTimePicker::make('invoice_at')
                    ->label(__('order.invoice_at'))
                    ->default(fn(Order $record) => $record->invoice_at ?? now()),
            ])
            ->action(function (Order $record, array $data) {
                $result = app(OrderService::class)->updateInvoice($record, $data);

                if ($result->isError()) {
                    Notification::make()
                        ->title(__('common.error.update_error'))
                        ->body($result->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($result->getMessage())
                    ->success()
                    ->send();
            });
    }
}
