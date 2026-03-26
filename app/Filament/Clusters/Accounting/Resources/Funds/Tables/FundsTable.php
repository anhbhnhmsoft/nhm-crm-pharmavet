<?php

namespace App\Filament\Clusters\Accounting\Resources\Funds\Tables;

use App\Models\Fund;
use App\Services\FundService;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FundsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization.name')
                    ->label(__('Organization'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('balance')
                    ->label(__('accounting.fund.balance'))
                    ->formatStateUsing(fn ($state, Fund $record) => number_format((float) $state, 2) . ' ' . ($record->currency ?? 'VND'))
                    ->sortable()
                    ->color(fn($state) => $state < 0 ? 'danger' : 'success'),
                IconColumn::make('is_locked')
                    ->label(__('accounting.fund.is_locked'))
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('success'),
                TextColumn::make('fund_type')
                    ->label(__('accounting.fund.fund_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('accounting.fund.fund_types.' . $state)),
                TextColumn::make('updated_at')
                    ->label(__('common.table.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                Action::make('lock')
                    ->label(__('accounting.fund.lock_action'))
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn(Fund $record) => !$record->is_locked)
                    ->requiresConfirmation()
                    ->action(function (Fund $record) {
                        /** @var FundService $service */
                        $service = app(FundService::class);
                        $result = $service->lockFund($record, auth()->user());
                        if ($result->isError()) {
                            Notification::make()
                                ->title($result->getMessage())
                                ->danger()
                                ->send();
                            return;
                        }
                        Notification::make()
                            ->title(__('accounting.fund.notifications.locked'))
                            ->success()
                            ->send();
                    }),
                Action::make('unlock')
                    ->label(__('accounting.fund.unlock_action'))
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn(Fund $record) => $record->is_locked)
                    ->requiresConfirmation()
                    ->action(function (Fund $record) {
                        /** @var FundService $service */
                        $service = app(FundService::class);
                        $result = $service->unlockFund($record, auth()->user());
                        if ($result->isError()) {
                            Notification::make()
                                ->title($result->getMessage())
                                ->danger()
                                ->send();
                            return;
                        }
                        Notification::make()
                            ->title(__('accounting.fund.notifications.unlocked'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
