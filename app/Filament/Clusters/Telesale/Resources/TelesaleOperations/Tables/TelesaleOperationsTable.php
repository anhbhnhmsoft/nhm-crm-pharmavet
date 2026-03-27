<?php

namespace App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Tables;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\User\UserRole;
use App\Models\Customer;
use App\Models\User;
use Filament\Actions\BulkAction;
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
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
                TextColumn::make('organization.name')
                    ->label(__('telesale.form.organization'))
                    ->visible(fn() => Auth::user()->role === UserRole::SUPER_ADMIN->value)
                    ->searchable()
                    ->sortable()
                    ->size('sm'),

                TextColumn::make('username')
                    ->label(__('telesale.table.customer_name'))
                    ->description(fn(Customer $record) => $record->phone)
                    ->searchable(['username', 'phone'])
                    ->size('sm')
                    ->weight('medium'),

                TextColumn::make('source')
                    ->label(__('telesale.table.source'))
                    ->badge()
                    ->color(fn ($state) => IntegrationType::tryFrom((int)$state)?->color() ?? 'gray')
                    ->formatStateUsing(fn($state) => IntegrationType::getLabel((int) $state))
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
                Filter::make('advanced_search')
                    ->label(__('telesale.filters.advanced_search'))
                    ->form([
                        TextInput::make('keyword')
                            ->label(__('telesale.filters.search_keyword'))
                            ->placeholder(__('telesale.filters.search_keyword_placeholder')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $keyword = trim((string) ($data['keyword'] ?? ''));
                        if ($keyword === '') {
                            return $query;
                        }

                        return $query->where(function (Builder $subQuery) use ($keyword) {
                            $subQuery
                                ->where('username', 'like', "%{$keyword}%")
                                ->orWhere('phone', 'like', "%{$keyword}%")
                                ->orWhereHas('orders', fn(Builder $orderQuery) => $orderQuery->where('code', 'like', "%{$keyword}%"));
                        });
                    }),

                Filter::make('date_received')
                    ->label(__('telesale.filters.date_received'))
                    ->form([
                        DatePicker::make('from_date')->label(__('telesale.filters.from_date')),
                        DatePicker::make('to_date')->label(__('telesale.filters.to_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from_date'] ?? null, fn(Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['to_date'] ?? null, fn(Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),

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

                SelectFilter::make('customer_type')
                    ->label(__('telesale.filters.customer_temperature'))
                    ->options(CustomerType::toOptions()),

                SelectFilter::make('interaction_status')
                    ->label(__('telesale.filters.care_result'))
                    ->options(InteractionStatus::options()),

                Filter::make('duplicate_contact')
                    ->label(__('telesale.filters.duplicate_contact'))
                    ->toggle()
                    ->query(function (Builder $query): Builder {
                        return $query->where(function (Builder $duplicateQuery) {
                            $duplicateQuery
                                ->whereExists(function ($subQuery) {
                                    $subQuery
                                        ->select(DB::raw(1))
                                        ->from('customers as c2')
                                        ->whereNull('c2.deleted_at')
                                        ->whereColumn('c2.organization_id', 'customers.organization_id')
                                        ->whereColumn('c2.id', '<>', 'customers.id')
                                        ->whereNotNull('customers.phone')
                                        ->whereColumn('c2.phone', 'customers.phone');
                                })
                                ->orWhereExists(function ($subQuery) {
                                    $subQuery
                                        ->select(DB::raw(1))
                                        ->from('customers as c2')
                                        ->whereNull('c2.deleted_at')
                                        ->whereColumn('c2.organization_id', 'customers.organization_id')
                                        ->whereColumn('c2.id', '<>', 'customers.id')
                                        ->whereNotNull('customers.email')
                                        ->whereColumn('c2.email', 'customers.email');
                                });
                        });
                    }),
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
                        ->visible(fn($record) => !$record->trashed()),

                    RestoreAction::make()
                        ->label(__('common.action.restore'))
                        ->tooltip(__('common.tooltip.restore'))
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn($record) => $record->trashed()),
                ]),
            ], position: \Filament\Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assign_staff')
                        ->label(__('telesale.actions.assign_sale'))
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Select::make('staff_id')
                                ->label(__('telesale.actions.select_staff'))
                                ->options(
                                    User::query()
                                        ->where('role', UserRole::SALE->value)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                )
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $staffId = (int) $data['staff_id'];
                            $actorId = (int) Auth::id();

                            foreach ($records as $record) {
                                $record->update([
                                    'assigned_staff_id' => $staffId,
                                ]);
                                $record->assignedStaff()->syncWithoutDetaching([$staffId]);
                            }

                            DB::table('user_logs')->insert([
                                'user_id' => $actorId,
                                'desc' => __('telesale.messages.bulk_assign_log') . " | count={$records->count()} | staff_id={$staffId}",
                                'ip_address' => request()->ip(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            Notification::make()
                                ->title(__('telesale.messages.bulk_assign_success'))
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),
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
