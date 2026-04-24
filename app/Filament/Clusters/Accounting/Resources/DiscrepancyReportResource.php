<?php

namespace App\Filament\Clusters\Accounting\Resources;

use App\Common\Constants\GateKey;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Accounting\AccountingCluster;
use App\Filament\Clusters\Accounting\Resources\DiscrepancyReportResource\Pages\ListDiscrepancyReports;
use App\Services\Accounting\DiscrepancyReportService;
use App\Utils\DateRangeGuard;
use App\Models\Order;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DiscrepancyReportResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?DiscrepancyReportService $discrepancyReportService = null;

    protected static ?string $cluster = AccountingCluster::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-scale';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('accounting.report.discrepancy');
    }

    public static function getModelLabel(): string
    {
        return __('accounting.report.discrepancy');
    }

    public static function canAccess(): bool
    {
        return Gate::allows(GateKey::IS_SUPER_ADMIN->name)
            || Gate::allows(GateKey::IS_ADMIN->name)
            || Gate::allows(GateKey::HAS_ROLE->name, [UserRole::ACCOUNTING]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('organization_id', Auth::user()->organization_id)
                ->with(['items', 'reconciliation', 'createdBy', 'inventoryTickets.details'])
            )
            ->columns([
                TextColumn::make('code')
                    ->label(__('order.table.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('order.table.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label(__('order.table.created_by'))
                    ->sortable(),

                TextColumn::make('debt_age')
                    ->label(__('accounting.report.debt_age'))
                    ->getStateUsing(fn (Order $record) => (int) now()->diffInDays($record->created_at, true))
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label(__('accounting.report.discrepancy_system'))
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('warehouse_value')
                    ->label(__('accounting.report.discrepancy_warehouse'))
                    ->getStateUsing(fn (Order $record): float => static::discrepancyReportService()->resolveWarehouseValue($record))
                    ->money('VND')
                    ->color(fn ($state, Order $record): string => static::discrepancyReportService()->valuesDifferent(
                        (float) $state,
                        static::discrepancyReportService()->resolveSystemValue($record),
                    ) ? 'danger' : 'success')
                    ->weight(fn ($state, Order $record): string => static::discrepancyReportService()->valuesDifferent(
                        (float) $state,
                        static::discrepancyReportService()->resolveSystemValue($record),
                    ) ? 'bold' : 'normal'),

                TextColumn::make('actual_payment')
                    ->label(__('accounting.report.discrepancy_actual'))
                    ->getStateUsing(fn (Order $record): float => static::discrepancyReportService()->resolveActualPayment($record))
                    ->money('VND')
                    ->color(fn ($state, Order $record): string => static::discrepancyReportService()->valuesDifferent(
                        (float) $state,
                        static::discrepancyReportService()->resolveWarehouseValue($record),
                    ) ? 'danger' : 'success')
                    ->weight('bold'),

                TextColumn::make('discrepancy_note')
                    ->label(__('accounting.report.discrepancy_note'))
                    ->getStateUsing(fn (Order $record): string => static::discrepancyReportService()->resolveDiscrepancyNote($record))
                    ->color(function ($state): string {
                        $matched = __('accounting.report.discrepancy_matched');
                        return $state === $matched ? 'success' : 'danger';
                    })
                    ->weight('bold'),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('date')
                    ->form([
                        DatePicker::make('from')
                            ->label(__('common.from_date'))
                            ->live()
                            ->beforeOrEqual('to')
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'before_or_equal' => __('validation.before_or_equal', [
                                    'attribute' => __('common.from_date'),
                                    'date' => __('common.to_date'),
                                ]),
                            ]),
                        DatePicker::make('to')
                            ->label(__('common.to_date'))
                            ->live()
                            ->afterOrEqual('from')
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'after_or_equal' => __('validation.after_or_equal', [
                                    'attribute' => __('common.to_date'),
                                    'date' => __('common.from_date'),
                                ]),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (DateRangeGuard::hasInvalidRange($data['from'] ?? null, $data['to'] ?? null)) {
                            DateRangeGuard::notifyInvalidRange(
                                __CLASS__ . ':date',
                                __('validation.after_or_equal', [
                                    'attribute' => __('common.to_date'),
                                    'date' => __('common.from_date'),
                                ]),
                            );

                            return $query;
                        }

                        return $query
                            ->when($data['from'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['to'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    })
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscrepancyReports::route('/'),
        ];
    }

    protected static function discrepancyReportService(): DiscrepancyReportService
    {
        return static::$discrepancyReportService ??= app(DiscrepancyReportService::class);
    }
}
