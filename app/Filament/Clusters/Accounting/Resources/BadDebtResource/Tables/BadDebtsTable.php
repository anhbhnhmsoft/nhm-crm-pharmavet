<?php

namespace App\Filament\Clusters\Accounting\Resources\BadDebtResource\Tables;

use App\Models\Order;
use App\Services\Accounting\DebtService;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class BadDebtsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('order.table.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label(__('order.table.customer'))
                    ->description(fn (Order $record) => $record->customer->phone)
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->label(__('order.table.total_amount'))
                    ->money('VND')
                    ->sortable(),
                TextColumn::make('collect_amount')
                    ->label(__('order.table.collect_amount'))
                    ->money('VND')
                    ->sortable(),
                TextColumn::make('amount_recived_from_customer')
                    ->label(__('order.table.paid_amount'))
                    ->money('VND')
                    ->sortable(),
                TextColumn::make('remaining_debt')
                    ->label(__('accounting.bad_debt.remaining_debt'))
                    ->money('VND')
                    ->color('danger')
                    ->weight('bold'),
                TextColumn::make('debt_age')
                    ->label(__('accounting.bad_debt.debt_age'))
                    ->sortable()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 90 => 'danger',
                        $state >= 60 => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('debt_provision_amount')
                    ->label(__('accounting.bad_debt.provision_amount'))
                    ->money('VND'),
                IconColumn::make('is_written_off')
                    ->label(__('accounting.bad_debt.is_written_off'))
                    ->boolean(),
            ])
            ->actions([
                Action::make('provision')
                    ->label(__('accounting.bad_debt.actions.provision'))
                    ->icon('heroicon-o-banknotes')
                    ->form([
                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->maxValue(fn (Order $record) => $record->remaining_debt)
                            ->label(__('accounting.expense.amount')),
                        Textarea::make('note')
                            ->label(__('accounting.expense.note')),
                    ])
                    ->action(function (Order $record, array $data, DebtService $service) {
                        $result = $service->provisionDebt($record, (float)$data['amount'], $data['note'] ?? '');
                        if ($result->isSuccess()) {
                            Notification::make()->success()->title(__('common_success.add_success'))->send();
                        } else {
                            Notification::make()->danger()->title($result->getMessage())->send();
                        }
                    })
                    ->visible(fn (Order $record) => $record->remaining_debt > 0 && !$record->is_written_off),

                Action::make('write_off')
                    ->label(__('accounting.bad_debt.actions.write_off'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('accounting.bad_debt.actions.write_off'))
                    ->modalDescription(__('accounting.bad_debt.actions.confirm_write_off'))
                    ->form([
                        Textarea::make('note')
                            ->label(__('accounting.expense.note')),
                    ])
                    ->action(function (Order $record, array $data, DebtService $service) {
                        $result = $service->writeOffDebt($record, Auth::id(), $data['note'] ?? '');
                        if ($result->isSuccess()) {
                            Notification::make()->success()->title(__('common_success.update_success'))->send();
                        } else {
                            Notification::make()->danger()->title($result->getMessage())->send();
                        }
                    })
                    ->visible(fn (Order $record) => $record->remaining_debt > 0 && !$record->is_written_off),
            ])
            ->filters([
                //
            ]);
    }
}
