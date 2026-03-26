<?php

namespace App\Filament\Clusters\Accounting\Resources\Funds\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FundLockAuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'lockAudits';

    public static function getTitle($ownerRecord, $pageClass): string
    {
        return __('accounting.fund_lock.audit_title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('changed_at')
                    ->label(__('accounting.fund_lock.changed_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('action')
                    ->label(__('accounting.fund_lock.action'))
                    ->badge(),
                TextColumn::make('scope_type')
                    ->label(__('accounting.fund_lock.scope'))
                    ->badge(),
                TextColumn::make('is_locked')
                    ->label(__('accounting.fund_lock.status'))
                    ->formatStateUsing(fn ($state) => $state ? __('accounting.fund_lock.locked') : __('accounting.fund_lock.unlocked'))
                    ->badge()
                    ->color(fn ($state) => $state ? 'danger' : 'success'),
                TextColumn::make('actor.name')
                    ->label(__('accounting.fund_lock.changed_by'))
                    ->default('-'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('changed_at', 'desc');
    }
}
