<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Tables;

use App\Models\Customer;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
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
                    ->formatStateUsing(fn(string $state): string => __("telesale.source.{$state}"))
                    ->size('sm'),

                TextColumn::make('assignedStaff.name')
                    ->label(__('telesale.table.assigned_staff'))
                    ->sortable()
                    ->searchable()
                    ->placeholder(__('telesale.messages.unassigned'))
                    ->size('sm'),

                TextColumn::make('status')
                    ->label(__('telesale.table.status'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'new' => 'gray',
                        'processing' => 'warning',
                        'potential' => 'danger',
                        'closed' => 'success',
                        'cancelled' => 'danger',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn(string $state): string => __("telesale.status.{$state}"))
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
                    ->options([
                        'facebook' => __('telesale.source.facebook'),
                        'google' => __('telesale.source.google'),
                        'zalo' => __('telesale.source.zalo'),
                    ]),

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
                EditAction::make()
                    ->label(__('telesale.actions.operation')),
                // Action::make('call')
                //     ->label(__('telesale.actions.call'))
                //     ->icon('heroicon-o-phone')
                //     ->url(fn(Customer $record) => "tel:{$record->phone}"),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    BulkAction::make('assign_sale')
                        ->label(__('telesale.actions.assign_sale'))
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Select::make('staff_id')
                                ->label(__('telesale.actions.select_staff'))
                                ->options(User::pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each->update(['assigned_staff_id' => $data['staff_id']]);
                        }),
                ]),
            ]);
    }
}
