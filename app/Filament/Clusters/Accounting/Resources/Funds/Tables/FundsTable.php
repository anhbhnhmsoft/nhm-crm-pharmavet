<?php

namespace App\Filament\Clusters\Accounting\Resources\Funds\Tables;

use App\Models\Fund;
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
                    ->money('VND')
                    ->sortable()
                    ->color(fn($state) => $state < 0 ? 'danger' : 'success'),
                IconColumn::make('is_locked')
                    ->label(__('accounting.fund.is_locked'))
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('success'),
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
                        $record->update(['is_locked' => true]);
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
                        $record->update(['is_locked' => false]);
                        Notification::make()
                            ->title(__('accounting.fund.notifications.unlocked'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
