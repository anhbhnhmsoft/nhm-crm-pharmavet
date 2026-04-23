<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Tables;

use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Common\Constants\CacheKey;
use App\Common\Constants\Order\GhnOrderStatus;
use App\Common\Constants\Order\OrderCareStatus;
use App\Common\Constants\Order\PaymentType;
use App\Common\Constants\Shipping\ProviderShipping;
use App\Common\Constants\Shipping\RequiredNote;
use App\Core\Caching;
use App\Services\ReconciliationService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use App\Models\Warehouse;
use App\Common\Constants\Order\OrderStatus;
use Filament\Tables\Table;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\BulkAction;

class ReconciliationsTable
{
    protected static function getOrganizationId(): ?int
    {
        return auth()->user()?->organization_id;
    }

    protected static function getReconciliationService(): ReconciliationService
    {
        return app(ReconciliationService::class);
    }

    protected static function getLikeOperator(Builder $query): string
    {
        return $query->getConnection()->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';
    }

    protected static function wrapLike(string $search): string
    {
        return '%' . trim($search) . '%';
    }

    protected static function getMatchingOptionValues(array $options, string $search, bool $castToInt = true): array
    {
        $normalizedSearch = mb_strtolower(trim(strip_tags($search)));

        if ($normalizedSearch === '') {
            return [];
        }

        return collect($options)
            ->filter(function ($label) use ($normalizedSearch): bool {
                $normalizedLabel = mb_strtolower(trim(strip_tags((string) $label)));

                return $normalizedLabel !== '' && str_contains($normalizedLabel, $normalizedSearch);
            })
            ->keys()
            ->map(fn ($value) => $castToInt ? (int) $value : (string) $value)
            ->values()
            ->all();
    }

    protected static function normalizeSearchTerm(?string $value): string
    {
        $normalized = mb_strtolower(trim(strip_tags((string) $value)));

        if ($normalized === '') {
            return '';
        }

        return preg_replace('/\s+/', ' ', Str::ascii($normalized)) ?? '';
    }

    protected static function matchesNotPostedSearch(string $search): bool
    {
        $normalizedSearch = self::normalizeSearchTerm($search);

        if ($normalizedSearch === '') {
            return false;
        }

        foreach ([
            __('order.table.not_posted'),
            'chua dang don',
        ] as $candidate) {
            $normalizedCandidate = self::normalizeSearchTerm($candidate);

            if ($normalizedCandidate !== '' && str_contains($normalizedCandidate, $normalizedSearch)) {
                return true;
            }
        }

        return false;
    }

    protected static function applyGlobalTableSearch(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $like = self::wrapLike($search);
        $likeOperator = self::getLikeOperator($query);
        $numericSearch = preg_replace('/\D+/', '', $search);
        $numericLike = filled($numericSearch) ? "%{$numericSearch}%" : null;

        $matchingReconciliationStatuses = self::getMatchingOptionValues(ReconciliationStatus::getOptions(), $search);
        $matchingCareStatuses = self::getMatchingOptionValues(OrderCareStatus::toOptions(), $search);
        $matchingShippingStatuses = self::getMatchingOptionValues(GhnOrderStatus::toOptions(), $search, castToInt: false);
        $matchesNotPosted = self::matchesNotPostedSearch($search);

        return $query->where(function (Builder $searchQuery) use (
            $like,
            $likeOperator,
            $numericLike,
            $matchingReconciliationStatuses,
            $matchingCareStatuses,
            $matchingShippingStatuses,
            $matchesNotPosted
        ): void {
            $searchQuery
                ->where('reconciliations.ghn_order_code', $likeOperator, $like)
                ->orWhere('reconciliations.ghn_to_name', $likeOperator, $like)
                ->orWhere('reconciliations.ghn_to_phone', $likeOperator, $like)
                ->orWhere('reconciliations.ghn_to_address', $likeOperator, $like)
                ->orWhere('reconciliations.ghn_status', $likeOperator, $like)
                ->orWhere('reconciliations.ghn_employee_note', $likeOperator, $like)
                ->orWhere('reconciliations.note', $likeOperator, $like);

            if ($matchingReconciliationStatuses !== []) {
                $searchQuery->orWhereIn('reconciliations.status', $matchingReconciliationStatuses);
            }

            if ($matchingShippingStatuses !== []) {
                $searchQuery->orWhereIn('reconciliations.ghn_status', $matchingShippingStatuses);
            }

            if ($matchesNotPosted) {
                $searchQuery->orWhere(function (Builder $notPostedQuery): void {
                    $notPostedQuery
                        ->whereNull('reconciliations.ghn_status')
                        ->whereHas('order', function (Builder $orderQuery): void {
                            $orderQuery
                                ->whereNull('orders.ghn_status')
                                ->whereNull('orders.ghn_posted_at');
                        });
                });
            }

            if ($numericLike !== null) {
                $searchQuery
                    ->orWhereRaw("regexp_replace(COALESCE(reconciliations.cod_amount::text, ''), '[^0-9]', '', 'g') LIKE ?", [$numericLike])
                    ->orWhereRaw("regexp_replace(COALESCE(reconciliations.total_fee::text, ''), '[^0-9]', '', 'g') LIKE ?", [$numericLike])
                    ->orWhereRaw("regexp_replace(COALESCE(reconciliations.shipping_fee::text, ''), '[^0-9]', '', 'g') LIKE ?", [$numericLike]);
            }

            $searchQuery->orWhereHas('order', function (Builder $orderQuery) use (
                $like,
                $likeOperator,
                $numericLike,
                $matchingCareStatuses,
                $matchingShippingStatuses
            ): void {
                $orderQuery
                    ->where('orders.code', $likeOperator, $like)
                    ->orWhere('orders.ghn_order_code', $likeOperator, $like)
                    ->orWhere('orders.provider_shipping', $likeOperator, $like)
                    ->orWhere('orders.shipping_method', $likeOperator, $like)
                    ->orWhere('orders.ghn_status', $likeOperator, $like)
                    ->orWhere('orders.shipping_address', $likeOperator, $like)
                    ->orWhere('orders.note', $likeOperator, $like)
                    ->orWhereHas('warehouse', fn (Builder $warehouseQuery) => $warehouseQuery->where('warehouses.name', $likeOperator, $like))
                    ->orWhereHas('createdBy', fn (Builder $userQuery) => $userQuery->where('users.name', $likeOperator, $like))
                    ->orWhereHas('careBy', fn (Builder $userQuery) => $userQuery->where('users.name', $likeOperator, $like))
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($like, $likeOperator): void {
                        $customerQuery
                            ->where('customers.username', $likeOperator, $like)
                            ->orWhere('customers.phone', $likeOperator, $like)
                            ->orWhere('customers.address', $likeOperator, $like)
                            ->orWhere('customers.shipping_address', $likeOperator, $like)
                            ->orWhereHas('assignedStaffPrimary', fn (Builder $userQuery) => $userQuery->where('users.name', $likeOperator, $like));
                    })
                    ->orWhereHas('items.product', fn (Builder $productQuery) => $productQuery->where('products.name', $likeOperator, $like));

                if ($matchingCareStatuses !== []) {
                    $orderQuery->orWhereIn('orders.care_status', $matchingCareStatuses);
                }

                if ($matchingShippingStatuses !== []) {
                    $orderQuery->orWhereIn('orders.ghn_status', $matchingShippingStatuses);
                }

                if ($numericLike !== null) {
                    $orderQuery
                        ->orWhereRaw("regexp_replace(COALESCE(orders.total_amount::text, ''), '[^0-9]', '', 'g') LIKE ?", [$numericLike])
                        ->orWhereRaw("regexp_replace(COALESCE(orders.discount::text, ''), '[^0-9]', '', 'g') LIKE ?", [$numericLike])
                        ->orWhereRaw("regexp_replace(COALESCE(orders.shipping_fee::text, ''), '[^0-9]', '', 'g') LIKE ?", [$numericLike])
                        ->orWhereRaw("regexp_replace(COALESCE(orders.deposit::text, ''), '[^0-9]', '', 'g') LIKE ?", [$numericLike])
                        ->orWhereRaw("regexp_replace(COALESCE(orders.amount_recived_from_customer::text, ''), '[^0-9]', '', 'g') LIKE ?", [$numericLike])
                        ->orWhereRaw("regexp_replace(COALESCE(orders.amout_support_fee::text, ''), '[^0-9]', '', 'g') LIKE ?", [$numericLike]);
                }
            });

            $searchQuery->orWhereRaw(
                "EXISTS (
                    SELECT 1
                    FROM json_array_elements(COALESCE(reconciliations.ghn_items, '[]'::json)) AS item
                    WHERE COALESCE(item->>'name', '') {$likeOperator} ?
                )",
                [$like]
            );
        });
    }

    protected static function hasInvalidDateRange(array $data): bool
    {
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;

        if (blank($from) || blank($to)) {
            return false;
        }

        try {
            return Carbon::parse($from)->gt(Carbon::parse($to));
        } catch (\Throwable) {
            return false;
        }
    }

    protected static function getProductFilterOptions(): array
    {
        return self::getReconciliationService()->getProductFilterOptions(self::getOrganizationId());
    }

    protected static function applyProductFilter(Builder $query, ?string $selectedProduct): Builder
    {
        return self::getReconciliationService()->applyProductFilter(
            $query,
            $selectedProduct,
            self::getOrganizationId(),
        );
    }

    protected static function getQuantityThresholdFilterOptions(): array
    {
        return collect([
            ...range(1, 10),
            ...range(20, 100, 10),
        ])
            ->mapWithKeys(fn (int $threshold): array => [
                (string) $threshold => __('accounting.reconciliation.filter_qty_from', ['quantity' => $threshold]),
            ])
            ->all();
    }

    protected static function applyQuantityThresholdFilter(Builder $query, int|string|null $minimumQuantity): Builder
    {
        if (blank($minimumQuantity)) {
            return $query;
        }

        $minimumQuantity = (int) $minimumQuantity;

        if ($minimumQuantity <= 0) {
            return $query;
        }

        return $query->where(function (Builder $quantityQuery) use ($minimumQuantity): void {
            $quantityQuery
                ->whereHas('order', fn (Builder $orderQuery): Builder => $orderQuery->whereRaw(
                    '(SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = orders.id) >= ?',
                    [$minimumQuantity]
                ))
                ->orWhere(function (Builder $ghnFallbackQuery) use ($minimumQuantity): void {
                    $ghnFallbackQuery
                        ->whereDoesntHave('order.items')
                        ->whereRaw(
                            "(SELECT COALESCE(SUM(COALESCE(NULLIF(item->>'quantity', '')::numeric, 0)), 0)
                                FROM json_array_elements(COALESCE(ghn_items, '[]'::json)) AS item) >= ?",
                            [$minimumQuantity]
                        );
                });
        });
    }

    protected static function getFollowOrderFilterDefinitions(): array
    {
        $deliveryThresholds = [
            ['key' => 'delivery_24h', 'index' => 1, 'hours' => 24, 'duration' => '24h'],
            ['key' => 'delivery_36h', 'index' => 2, 'hours' => 36, 'duration' => '36h'],
            ['key' => 'delivery_48h', 'index' => 3, 'hours' => 48, 'duration' => '48h'],
            ['key' => 'delivery_72h', 'index' => 4, 'hours' => 72, 'duration' => '72h'],
            ['key' => 'delivery_4d', 'index' => 5, 'hours' => 96, 'duration' => '4 ngày'],
            ['key' => 'delivery_5d', 'index' => 6, 'hours' => 120, 'duration' => '5 ngày'],
            ['key' => 'delivery_6d', 'index' => 7, 'hours' => 144, 'duration' => '6 ngày'],
            ['key' => 'delivery_7d', 'index' => 8, 'hours' => 168, 'duration' => '7 ngày'],
        ];

        $pickupThresholds = [
            ['key' => 'pickup_24h', 'index' => 21, 'hours' => 24, 'duration' => '24h'],
            ['key' => 'pickup_36h', 'index' => 22, 'hours' => 36, 'duration' => '36h'],
            ['key' => 'pickup_48h', 'index' => 23, 'hours' => 48, 'duration' => '48h'],
            ['key' => 'pickup_72h', 'index' => 24, 'hours' => 72, 'duration' => '72h'],
        ];

        $postingThresholds = [
            ['key' => 'posting_24h', 'index' => 31, 'hours' => 24, 'duration' => '24h'],
            ['key' => 'posting_36h', 'index' => 32, 'hours' => 36, 'duration' => '36h'],
            ['key' => 'posting_48h', 'index' => 33, 'hours' => 48, 'duration' => '48h'],
            ['key' => 'posting_72h', 'index' => 34, 'hours' => 72, 'duration' => '72h'],
        ];

        return collect($deliveryThresholds)
            ->mapWithKeys(fn (array $definition): array => [
                $definition['key'] => [
                    ...$definition,
                    'type' => 'delivery',
                    'label' => __('accounting.reconciliation.filter_follow_order_delivery', [
                        'index' => $definition['index'],
                        'duration' => $definition['duration'],
                    ]),
                ],
            ])
            ->merge(collect($pickupThresholds)->mapWithKeys(fn (array $definition): array => [
                $definition['key'] => [
                    ...$definition,
                    'type' => 'pickup',
                    'label' => __('accounting.reconciliation.filter_follow_order_pickup', [
                        'index' => $definition['index'],
                        'duration' => $definition['duration'],
                    ]),
                ],
            ]))
            ->merge(collect($postingThresholds)->mapWithKeys(fn (array $definition): array => [
                $definition['key'] => [
                    ...$definition,
                    'type' => 'posting',
                    'label' => __('accounting.reconciliation.filter_follow_order_posting', [
                        'index' => $definition['index'],
                        'duration' => $definition['duration'],
                    ]),
                ],
            ]))
            ->all();
    }

    protected static function getFollowOrderFilterOptions(): array
    {
        return collect(self::getFollowOrderFilterDefinitions())
            ->mapWithKeys(fn (array $definition, string $key): array => [$key => $definition['label']])
            ->all();
    }

    protected static function getFinalShippingStatuses(): array
    {
        return [
            ...GhnOrderStatus::finalStatusesForReconciliation(),
            GhnOrderStatus::LEGACY_CANCELLED,
        ];
    }

    protected static function getNotPickedShippingStatuses(): array
    {
        return [
            GhnOrderStatus::READY_TO_PICK->value,
            GhnOrderStatus::PICKING->value,
            GhnOrderStatus::MONEY_COLLECT_PICKING->value,
        ];
    }

    protected static function applyFollowOrderFilter(Builder $query, ?string $selectedValue): Builder
    {
        if (blank($selectedValue)) {
            return $query;
        }

        $definition = self::getFollowOrderFilterDefinitions()[$selectedValue] ?? null;

        if (! $definition) {
            return $query;
        }

        $cutoff = now()->subHours((int) $definition['hours']);

        return match ($definition['type']) {
            'delivery' => $query->whereHas('order', function (Builder $orderQuery) use ($cutoff): void {
                $orderQuery
                    ->whereNotNull('ghn_posted_at')
                    ->where('ghn_posted_at', '<=', $cutoff)
                    ->where(function (Builder $statusQuery): void {
                        $statusQuery
                            ->whereNull('ghn_status')
                            ->orWhereNotIn('ghn_status', self::getFinalShippingStatuses());
                    });
            }),
            'pickup' => $query->whereHas('order', function (Builder $orderQuery) use ($cutoff): void {
                $orderQuery
                    ->whereNotNull('ghn_posted_at')
                    ->where('ghn_posted_at', '<=', $cutoff)
                    ->where(function (Builder $statusQuery): void {
                        $statusQuery
                            ->whereNull('ghn_status')
                            ->orWhereIn('ghn_status', self::getNotPickedShippingStatuses());
                    });
            }),
            'posting' => $query->whereHas('order', function (Builder $orderQuery) use ($cutoff): void {
                $orderQuery
                    ->where('status', OrderStatus::CONFIRMED->value)
                    ->whereRaw(
                        'COALESCE(
                            (
                                SELECT MAX(osl.created_at)
                                FROM order_status_logs osl
                                WHERE osl.order_id = orders.id
                                    AND osl.to_status = ?
                            ),
                            orders.created_at
                        ) <= ?',
                        [OrderStatus::CONFIRMED->value, $cutoff]
                    )
                    ->whereNull('ghn_posted_at');
            }),
            default => $query,
        };
    }

    protected static function getEffectiveShippingStatus($record): ?string
    {
        return self::getReconciliationService()->getEffectiveShippingStatus($record);
    }

    protected static function isNotPostedRecord($record): bool
    {
        return self::getReconciliationService()->isNotPostedRecord($record);
    }

    protected static function getShippingStatusLabel($record): string
    {
        return self::getReconciliationService()->getShippingStatusLabel($record);
    }

    protected static function canConfirmRecord($record): bool
    {
        return self::getReconciliationService()->canConfirmRecord($record);
    }

    protected static function getAllowedCareStatusOptions($record): array
    {
        return self::getReconciliationService()->getAllowedCareStatusOptions($record);
    }

    protected static function getCareStatusLabel(int|string|null $status): string
    {
        return OrderCareStatus::getLabel(
            filled($status) ? (int) $status : null
        );
    }

    protected static function getCareStatusTextClass(int|string|null $status): string
    {
        return OrderCareStatus::color(
            filled($status) ? (int) $status : null
        );
    }

    protected static function getSaleLeaderFilterOptions(): array
    {
        return self::getReconciliationService()->getSaleLeaderFilterOptions(self::getOrganizationId());
    }

    protected static function getSaleLeaderTeamIds(int|string|null $leaderId): array
    {
        return self::getReconciliationService()->getSaleLeaderTeamIds(self::getOrganizationId(), $leaderId);
    }

    protected static function getSaleTeamFilterOptions(int|string|null $leaderId = null): array
    {
        return self::getReconciliationService()->getSaleTeamFilterOptions(self::getOrganizationId(), $leaderId);
    }

    protected static function getSaleFilterOptions(int|string|null $leaderId = null, int|string|null $teamId = null): array
    {
        return self::getReconciliationService()->getSaleFilterOptions(
            self::getOrganizationId(),
            $leaderId,
            $teamId,
        );
    }

    protected static function applySaleTeamConstraintToReconciliationQuery(Builder $query, array $teamIds): Builder
    {
        if ($teamIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('order.createdBy', function (Builder $userQuery) use ($teamIds): void {
            $userQuery->where(function (Builder $teamScopedUserQuery) use ($teamIds): void {
                $teamScopedUserQuery
                    ->whereIn('team_id', $teamIds)
                    ->orWhereHas('teams', fn (Builder $teamQuery) => $teamQuery->whereIn('teams.id', $teamIds));
            });
        });
    }

    protected static function getActiveFilterValue(object $livewire, string $filterName): int|string|null
    {
        return data_get($livewire, "tableDeferredFilters.{$filterName}.value")
            ?? data_get($livewire, "tableFilters.{$filterName}.value");
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->label(new HtmlString(
                        '<div class="text-center font-semibold text-[11px] leading-tight">' .
                        __('accounting.reconciliation.status_compact_label') .
                        '</div>'
                    ))
                    ->formatStateUsing(fn($state) => ReconciliationStatus::getOptions()[$state] ?? '-')
                    ->alignCenter()
                    ->size('xs'),
                TextColumn::make('ghn_order_code')
                    ->label(new HtmlString('<div class="text-center font-semibold">' .
                        __('accounting.reconciliation.data_arrival_date') . '<br>' .
                        __('accounting.reconciliation.short_order_code') . '<br>' .
                        __('accounting.reconciliation.short_confirmation_date') .
                        '</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $dataDate = $record->created_at?->format('d/m/Y H:i') ?? '-';
                        $code = $record->order?->code ?? $record->ghn_order_code ?? '-';
                        $confirmedDate = $record->status !== ReconciliationStatus::PENDING->value
                            ? ($record->confirmed_at?->format('d/m/Y H:i') ?? '-')
                            : '-';

                        return "
                            <div class='text-[10px] text-gray-500 whitespace-nowrap mb-1 text-center'>{$dataDate}</div>
                            <div class='text-xs font-bold text-primary-600 mb-1 text-center'>{$code}</div>
                            <div class='text-[10px] text-gray-500 whitespace-nowrap text-center'>{$confirmedDate}</div>
                        ";
                    })
                    ->copyable()
                    ->copyableState(fn($record) => $record->order?->code ?? $record->ghn_order_code)
                    ->searchable(
                        query: fn (Builder $query, string $search): Builder => self::applyGlobalTableSearch($query, $search)
                    )
                    ->alignCenter()
                    ->size('xs'),

                TextColumn::make('ghn_to_address')
                    ->label(new HtmlString('<div class="text-center font-semibold">' .
                        __('accounting.reconciliation.warehouse') . '<br>' .
                        __('accounting.reconciliation.shipping_method_short') . '<br>' .
                        __('accounting.reconciliation.shipping_code') .
                        '</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $warehouse = $record->order?->warehouse?->name ?? '-';
                        $provider = $record->order?->provider_shipping ?? ($record->order?->shipping_method ?? 'GHN');
                        $ghnCode = $record->ghn_order_code ?? $record->order?->ghn_order_code ?? '-';

                        return "
                            <div class='text-xs font-semibold text-gray-900 text-center'>{$warehouse}</div>
                            <div class='text-[10px] text-emerald-600 font-medium text-center'>{$provider}</div>
                            <div class='text-[10px] text-primary-600 font-semibold text-center'>{$ghnCode}</div>
                        ";
                    })
                    ->alignCenter()
                    ->size('xs'),

                TextColumn::make('note')
                    ->label(new HtmlString('<div class="text-center font-semibold">' .
                        __('accounting.reconciliation.care_update_date') . '<br>' .
                        __('accounting.reconciliation.care_staff') . '<br>' .
                        __('accounting.reconciliation.accounting_note') .
                        '</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $updated = $record->order?->care_updated_at?->format('d/m/Y H:i')
                            ?? $record->updated_at?->format('d/m/Y H:i')
                            ?? '-';

                        $careStaff = $record->order?->careBy
                            ?? $record->order?->customer?->assignedStaffPrimary
                            ?? $record->order?->createdBy;

                        $care = e($careStaff?->name ?? '-');
                        $careStatus = e(self::getCareStatusLabel($record->order?->care_status));
                        $careStatusClass = self::getCareStatusTextClass($record->order?->care_status);

                        $noteText = !empty($record->ghn_employee_note) ? strip_tags($record->ghn_employee_note) : strip_tags($record->note ?? '');

                        if (empty($noteText)) {
                            $note = "<span style='color: #2563eb; font-weight: 600;'>" . __('accounting.reconciliation.no_note') . "</span>";
                        } else {
                            $note = e($noteText);
                        }

                        $editLink = e(__('accounting.reconciliation.edit_link'));

                        return "
                            <div class='w-full h-full cursor-pointer py-1'>
                                <div class='text-[10px] text-gray-500 text-center'>{$updated}</div>
                                <div class='text-xs text-gray-900 text-center'>{$care}</div>
                                <div class='text-[10px] font-semibold text-center {$careStatusClass}'>{$careStatus}</div>
                                <div class='text-[10px] text-center line-clamp-2'>{$note}</div>
                                <div style='font-size:12px;color:#3b82f6;font-weight:600;cursor:pointer;margin-top:2px;text-align:center'>{$editLink}</div>
                            </div>
                        ";
                    })
                    ->extraAttributes(['class' => 'cursor-pointer'])
                    ->alignCenter()
                    ->size('xs')
                    ->action(
                        Action::make('update_care_status')
                            ->label(__('accounting.reconciliation.care_status_action'))
                            ->icon('heroicon-o-pencil-square')
                            ->modalWidth('4xl')
                            ->modalHeading(__('accounting.reconciliation.care_status_heading'))
                            ->modalSubmitActionLabel(__('common.action.save'))
                            ->form([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('order_code_display')
                                            ->label(__('accounting.reconciliation.order_code'))
                                            ->disabled()
                                            ->dehydrated(false),
                                        TextInput::make('customer_name_display')
                                            ->label(__('accounting.reconciliation.customer_name'))
                                            ->disabled()
                                            ->dehydrated(false),
                                        TextInput::make('customer_phone_display')
                                            ->label(__('accounting.reconciliation.customer_phone'))
                                            ->disabled()
                                            ->dehydrated(false),
                                        TextInput::make('shipping_provider_display')
                                            ->label(__('accounting.reconciliation.shipping_provider'))
                                            ->disabled()
                                            ->dehydrated(false),
                                        TextInput::make('ghn_order_code_display')
                                            ->label(__('accounting.reconciliation.shipping_code'))
                                            ->disabled()
                                            ->dehydrated(false),
                                        TextInput::make('current_care_status_display')
                                            ->label(__('accounting.reconciliation.care_status_current'))
                                            ->disabled()
                                            ->dehydrated(false),
                                    ]),
                                Select::make('care_status')
                                    ->label(__('accounting.reconciliation.care_status_next'))
                                    ->options(fn ($record): array => self::getAllowedCareStatusOptions($record))
                                    ->placeholder(__('accounting.reconciliation.care_status_placeholder'))
                                    ->native(false)
                                    ->searchable(),
                                Textarea::make('ghn_employee_note')
                                    ->label(__('accounting.reconciliation.accounting_note'))
                                    ->columnSpanFull()
                                    ->rows(6),
                            ])
                            ->fillForm(fn ($record) => [
                                'order_code_display' => $record->order?->code ?? $record->ghn_order_code ?? '-',
                                'customer_name_display' => $record->order?->customer?->username ?? $record->ghn_to_name ?? '-',
                                'customer_phone_display' => $record->order?->customer?->phone ?? $record->ghn_to_phone ?? '-',
                                'shipping_provider_display' => $record->order?->provider_shipping ?? $record->order?->shipping_method ?? 'GHN',
                                'ghn_order_code_display' => $record->ghn_order_code ?? $record->order?->ghn_order_code ?? '-',
                                'current_care_status_display' => self::getCareStatusLabel($record->order?->care_status),
                                'care_status' => $record->order?->care_status,
                                'ghn_employee_note' => trim((string) strip_tags($record->ghn_employee_note ?? '')),
                            ])
                            ->action(function ($record, array $data) {
                                $order = $record->order()->first([
                                    'id',
                                    'organization_id',
                                    'created_at',
                                    'care_status',
                                    'care_by_id',
                                    'care_updated_at',
                                ]);

                                $currentNote = trim((string) strip_tags($record->ghn_employee_note ?? ''));
                                $nextNote = trim((string) ($data['ghn_employee_note'] ?? ''));
                                $noteChanged = $currentNote !== $nextNote;

                                $currentStatus = $order?->care_status;
                                $nextStatus = filled($data['care_status'] ?? null)
                                    ? (int) $data['care_status']
                                    : null;
                                $statusChanged = $order && ((string) ($currentStatus ?? '') !== (string) ($nextStatus ?? ''));

                                if (! $noteChanged && ! $statusChanged) {
                                    Notification::make()
                                        ->info()
                                        ->title(__('accounting.reconciliation.no_changes'))
                                        ->send();

                                    return;
                                }

                                if (
                                    $statusChanged
                                    && ! OrderCareStatus::isAllowedForShippingStatus(
                                        $nextStatus,
                                        self::getEffectiveShippingStatus($record),
                                    )
                                ) {
                                    Notification::make()
                                        ->warning()
                                        ->title(__('accounting.reconciliation.care_status_invalid_title'))
                                        ->body(__('accounting.reconciliation.care_status_invalid_body'))
                                        ->send();

                                    return;
                                }

                                if ($noteChanged) {
                                    $record->update([
                                        'ghn_employee_note' => $nextNote !== '' ? $nextNote : null,
                                    ]);
                                }

                                if ($statusChanged) {
                                    $order->update([
                                        'care_status' => $nextStatus,
                                        'care_by_id' => auth()->id(),
                                        'care_updated_at' => now(),
                                    ]);
                                }

                                Notification::make()
                                    ->success()
                                    ->title($statusChanged
                                        ? __('accounting.reconciliation.care_status_updated')
                                        : __('accounting.reconciliation.order_updated'))
                                    ->send();
                            })
                    ),
                TextColumn::make('ghn_status')
                    ->label(new HtmlString('<div class="text-center font-semibold">' .
                        __('accounting.reconciliation.status_update_date') . '<br>' .
                        __('accounting.reconciliation.shipping_status') . '<br>' .
                        __('accounting.reconciliation.ghn_post_date') .
                        '</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $updated = $record->updated_at?->format('d/m/Y H:i') ?? '-';
                        $label = self::getShippingStatusLabel($record);

                        $posted = $record->order?->ghn_posted_at ? Carbon::parse($record->order->ghn_posted_at)->format('d/m/Y H:i') : ($record->ghn_created_at ? $record->ghn_created_at->format('d/m/Y H:i') : ($record->order?->created_at?->format('d/m/Y H:i') ?? '-'));

                        return "
                            <div class='text-[10px] text-gray-500 text-center'>{$updated}</div>
                            <div class='text-xs font-semibold text-center text-primary-600'>{$label}</div>
                            <div class='text-[10px] text-gray-500 text-center'>{$posted}</div>
                        ";
                    })
                    ->alignCenter()
                    ->size('xs'),

                TextColumn::make('ghn_items')
                    ->label(new HtmlString('<div class="text-center font-semibold">' . __('accounting.reconciliation.product_info') . '</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $items = $record->order?->items;

                        if ((!$items || $items->isEmpty()) && !empty($record->ghn_items)) {
                            return collect($record->ghn_items)->take(3)->map(function ($item) {
                                $name = e($item['name'] ?? 'SP');
                                $qty = number_format((float) ($item['quantity'] ?? 0), 0, ',', '.');
                                $price = number_format((float) ($item['price'] ?? 0), 0, ',', '.');

                                return "<div class='text-xs mb-1'><span class='font-medium'>{$name}</span> x{$qty} <span class='text-gray-500'>{$price}</span></div>";
                            })->implode('');
                        }

                        if (!$items || $items->isEmpty()) {
                            return '-';
                        }

                        return $items->take(3)->map(function ($item) {
                            $name = e($item->product?->name ?? 'SP');
                            $qty = number_format((float) $item->quantity, 0, ',', '.');
                            $price = number_format((float) $item->price, 0, ',', '.');

                            return "<div class='text-xs mb-1'><span class='font-medium'>{$name}</span> x{$qty} <span class='text-gray-500'>{$price}</span></div>";
                        })->implode('');
                    })
                    ->alignLeft(),

                TextColumn::make('cod_amount')
                    ->label(new HtmlString(
                        '<div class="text-center font-semibold">' .
                        e(__('accounting.reconciliation.total_amount')) .
                        ' <span class="text-primary-500 cursor-help align-middle text-sm" title="' . e(__('accounting.reconciliation.total_amount_formula')) . '">&#9432;</span>' .
                        '</div>'
                    ))
                    ->alignCenter()
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $amount = $record->order?->total_amount ?? $record->cod_amount;
                        $formatted = number_format((float) $amount, 0, ',', '.') . ' đ';
                        return "
                            <div style='text-align:center'>
                                <div style='font-size:12px;font-weight:600'>{$formatted}</div>
                                <div style='font-size:12px;color:#3b82f6;font-weight:600;cursor:pointer;margin-top:2px'>" . e(__('accounting.reconciliation.finance_detail_link')) . "</div>
                            </div>
                        ";
                    })
                    ->size('xs')
                    ->action(
                        Action::make('view_finance_detail')
                            ->label(__('accounting.reconciliation.finance_detail_label'))
                            ->modalHeading(__('accounting.reconciliation.finance_detail_heading'))
                            ->modalWidth('2xl')
                            ->mountUsing(function ($form, $record) {
                                $order  = $record->order;
                                $exchangeRate = $record->exchangeRate;
                                $isForeignOrganization = (bool) ($record->organization?->is_foreign ?? auth()->user()?->organization?->is_foreign);
                                $fmtDecimal = fn($v, int $decimals = 2) => rtrim(rtrim(number_format((float) $v, $decimals, '.', ','), '0'), '.');
                                $fmtCurrency = fn($v) => number_format((float) $v, 0, ',', '.') . ' VND';
                                $fmt    = fn($v) => number_format((float) $v, 0, ',', '.') . ' đ';
                                $form->fill([
                                    'total_amount'          => $fmtCurrency($order?->total_amount ?? $record->cod_amount),
                                    'discount'              => $fmtCurrency($order?->discount ?? 0),
                                    'shipping_fee_customer' => $fmtCurrency($order?->shipping_fee ?? 0),
                                    'total_fee'             => $fmtCurrency($record->total_fee ?? 0),
                                    'deposit'               => $fmtCurrency($order?->deposit ?? 0),
                                    'amount_received'       => $fmtCurrency($record->cod_amount ?? $order?->collect_amount ?? 0),
                                    'shipping_fee_service'  => $fmtCurrency($record->shipping_fee ?? 0),
                                    'storage_fee'           => $fmtCurrency($record->storage_fee ?? 0),
                                    'amount_support_fee'    => $fmtCurrency($order?->amout_support_fee ?? 0),
                                    'exchange_rate_applied' => ($isForeignOrganization && $exchangeRate)
                                        ? '1 ' . $exchangeRate->from_currency . ' = ' . $fmtDecimal((float) $exchangeRate->rate, 6) . ' ' . $exchangeRate->to_currency
                                        : '-',
                                    'converted_amount'      => ($isForeignOrganization && $record->converted_amount !== null && $exchangeRate)
                                        ? $fmtDecimal((float) $record->converted_amount, 2) . ' ' . $exchangeRate->from_currency
                                        : '-',
                                    'exchange_rate_source'  => ($isForeignOrganization && $exchangeRate) ? match ($exchangeRate?->source) {
                                        'manual' => __('accounting.exchange_rate.source_manual'),
                                        'api' => __('accounting.exchange_rate.source_api'),
                                        default => '-',
                                    } : '-',
                                    'exchange_rate_date'    => ($isForeignOrganization && $exchangeRate)
                                        ? $exchangeRate->rate_date?->format('d/m/Y')
                                        : '-',
                                ]);
                            })
                            ->form([
                                TextInput::make('total_amount')
                                    ->label(__('accounting.reconciliation.total_amount'))
                                    ->disabled()
                                    ->extraInputAttributes(['class' => 'font-semibold text-primary-600']),
                                TextInput::make('discount')->label(__('accounting.reconciliation.finance_ck'))->disabled(),
                                TextInput::make('shipping_fee_customer')->label(__('accounting.reconciliation.finance_shipping_fee_customer'))->disabled(),
                                TextInput::make('total_fee')
                                    ->label(new HtmlString(
                                        e(__('accounting.reconciliation.finance_total_fee')) .
                                        ' <span class="text-primary-500 cursor-help align-middle text-sm" title="' .
                                        e(__('accounting.reconciliation.finance_total_fee_formula')) .
                                        '">&#9432;</span>'
                                    ))
                                    ->disabled(),
                                TextInput::make('deposit')->label(__('accounting.reconciliation.finance_deposit'))->disabled(),
                                TextInput::make('amount_received')->label(__('accounting.reconciliation.finance_amount_received'))->disabled(),
                                TextInput::make('shipping_fee_service')->label(__('accounting.reconciliation.finance_shipping_fee_service'))->disabled(),
                                TextInput::make('storage_fee')->label(__('accounting.reconciliation.storage_fee'))->disabled(),
                                TextInput::make('amount_support_fee')->label(__('accounting.reconciliation.finance_amount_support_fee'))->disabled(),
                                Section::make(__('accounting.reconciliation.exchange_rate_summary_label'))
                                    ->schema([
                                        TextInput::make('exchange_rate_applied')->label(__('accounting.reconciliation.exchange_rate_applied_label'))->disabled(),
                                        TextInput::make('converted_amount')->label(__('accounting.reconciliation.converted_amount'))->disabled(),
                                        TextInput::make('exchange_rate_source')->label(__('accounting.reconciliation.exchange_rate_source_label'))->disabled(),
                                        TextInput::make('exchange_rate_date')->label(__('accounting.reconciliation.exchange_rate_date_label'))->disabled(),
                                    ])
                                    ->columns(1)
                                    ->visible(fn() => (bool) auth()->user()?->organization?->is_foreign),
                            ])
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel(__('accounting.reconciliation.finance_detail_close'))
                    ),

                TextColumn::make('exchange_rate_summary')
                    ->label(new HtmlString('<div class="text-center font-semibold text-[11px] leading-tight">' . __('accounting.reconciliation.exchange_rate_summary_label') . '</div>'))
                    ->state(fn ($record) => $record->exchange_rate_id ?? $record->id)
                    ->visible(fn () => (bool) auth()->user()?->organization?->is_foreign)
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $exchangeRate = $record->exchangeRate;

                        if (! $exchangeRate) {
                            return "
                                <div class='text-[10px] text-gray-400 text-center'>" . e(__('accounting.reconciliation.exchange_rate_summary_empty')) . "</div>
                                <div class='text-[10px] text-gray-400 text-center'>-</div>
                            ";
                        }

                        $rate = e($exchangeRate->from_currency . '/' . $exchangeRate->to_currency) . ': ' .
                            rtrim(rtrim(number_format((float) $exchangeRate->rate, 6, '.', ','), '0'), '.');
                        $converted = $record->converted_amount !== null
                            ? rtrim(rtrim(number_format((float) $record->converted_amount, 2, '.', ','), '0'), '.') . ' ' . e($exchangeRate->from_currency)
                            : '-';

                        return "
                            <div class='text-xs font-semibold text-gray-900 text-center'>{$rate}</div>
                            <div class='text-[10px] text-primary-600 text-center'>{$converted}</div>
                        ";
                    })
                    ->alignCenter()
                    ->size('xs'),

                TextColumn::make('order.discount')
                    ->label(__('accounting.reconciliation.finance_ck'))
                    ->money('VND')
                    ->alignEnd()
                    ->size('xs')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('order.shipping_fee')
                    ->label(new HtmlString('<div class="text-center font-semibold text-[11px] leading-tight">' . __('accounting.reconciliation.finance_shipping_fee_customer') . '</div>'))
                    ->money('VND')
                    ->alignEnd()
                    ->size('xs')
                    ->width('80px')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_fee')
                    ->label(__('accounting.reconciliation.finance_total_fee'))
                    ->money('VND')
                    ->alignEnd()
                    ->size('xs')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('order.deposit')
                    ->label(__('accounting.reconciliation.finance_deposit'))
                    ->money('VND')
                    ->alignEnd()
                    ->size('xs')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('id_received')
                    ->label(new HtmlString(
                        '<div class="text-center font-semibold text-[11px] leading-tight">' .
                        __('accounting.reconciliation.finance_amount_received_short_label') .
                        '</div>'
                    ))
                    ->money('VND')
                    ->formatStateUsing(function ($state, $record) {
                        return $record->order?->amount_recived_from_customer ?? $record->cod_amount;
                    })
                    ->alignEnd()
                    ->size('xs')
                    ->width('80px')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('shipping_fee')
                    ->label(new HtmlString(
                        '<div class="text-center font-semibold text-[11px] leading-tight">' .
                        __('accounting.reconciliation.finance_shipping_fee_service_short_label') .
                        '</div>'
                    ))
                    ->money('VND')
                    ->alignEnd()
                    ->size('xs')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('order.amout_support_fee')
                    ->label(new HtmlString(
                        '<div class="text-center font-semibold text-[11px] leading-tight">' .
                        __('accounting.reconciliation.finance_amount_support_fee_short_label') .
                        '</div>'
                    ))
                    ->money('VND')
                    ->alignEnd()
                    ->size('xs')
                    ->toggleable(isToggledHiddenByDefault: true),



                TextColumn::make('ghn_to_phone')
                    ->label(new HtmlString('<div class="text-center font-semibold">' .
                        __('accounting.reconciliation.customer_name') . '<br>' .
                        __('accounting.reconciliation.customer_phone') .
                        '</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $name = e($record->order?->customer?->username ?? $record->ghn_to_name ?? '-');
                        $phone = e($record->order?->customer?->phone ?? $record->ghn_to_phone ?? '');

                        return "
                            <div class='text-xs font-semibold text-center'>{$name}</div>
                            <div class='text-[10px] text-primary-600 text-center'>{$phone}</div>
                        ";
                    })
                    ->alignCenter()
                    ->size('sm'),

                TextColumn::make('ghn_to_name')
                    ->label(new HtmlString('<div class="text-center font-semibold">' .
                        __('accounting.reconciliation.shipping_address') . '<br>' .
                        __('accounting.reconciliation.shipping_note') .
                        '</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $address = e($record->order?->shipping_address ?? $record->ghn_to_address ?? '-');
                        $note = e($record->order?->note ?? '-');

                        return "
                            <div class='text-xs text-gray-900'>{$address}</div>
                            <div class='text-[10px] text-gray-500'>{$note}</div>
                        ";
                    }),


            ])
            ->filters([
                Filter::make('date_range')
                    ->label(__('accounting.reconciliation.filter_date_range'))
                    ->form([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('from')
                                    ->label(__('accounting.reconciliation.filter_from_date'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->beforeOrEqual('to')
                                    ->validationMessages([
                                        'before_or_equal' => __('accounting.reconciliation.filter_date_invalid_range'),
                                    ]),
                                DatePicker::make('to')
                                    ->label(__('accounting.reconciliation.filter_to_date'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->afterOrEqual('from')
                                    ->validationMessages([
                                        'after_or_equal' => __('accounting.reconciliation.filter_date_invalid_range'),
                                    ]),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data, $livewire): Builder {
                        if (self::hasInvalidDateRange($data)) {
                            return $query;
                        }

                        $dateType = $livewire->tableFilters['date_type']['value'] ?? 'reconciliation_date';
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => ($dateType === 'order_created_at' ? $query->whereHas('order', fn($o) => $o->whereDate('created_at', '>=', $date)) : $query->whereDate($dateType, '>=', $date)),
                            )
                            ->when(
                                $data['to'],
                                fn(Builder $query, $date): Builder => ($dateType === 'order_created_at' ? $query->whereHas('order', fn($o) => $o->whereDate('created_at', '<=', $date)) : $query->whereDate($dateType, '<=', $date)),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        if (self::hasInvalidDateRange($data)) {
                            return [];
                        }

                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make(__('accounting.reconciliation.filter_from_indicator', ['date' => Carbon::parse($data['from'])->format('d/m/Y')]))
                                ->removeField('from');
                        }
                        if ($data['to'] ?? null) {
                            $indicators[] = Indicator::make(__('accounting.reconciliation.filter_to_indicator', ['date' => Carbon::parse($data['to'])->format('d/m/Y')]))
                                ->removeField('to');
                        }
                        return $indicators;
                    }),

                SelectFilter::make('date_type')
                    ->label(__('accounting.reconciliation.filter_date_type'))
                    ->options([
                        'reconciliation_date' => __('accounting.reconciliation.filter_date_reconciliation'),
                        'ghn_created_at'      => __('accounting.reconciliation.filter_date_ghn'),
                        'confirmed_at'        => __('accounting.reconciliation.filter_date_confirmed'),
                        'order_created_at'    => __('accounting.reconciliation.filter_date_order_created'),
                    ])
                    ->default('reconciliation_date')
                    ->query(fn($query) => $query),

                TernaryFilter::make('is_printed')
                    ->label(__('accounting.reconciliation.filter_is_printed'))
                    ->placeholder(__('accounting.reconciliation.filter_deposit_all'))
                    ->trueLabel(__('accounting.reconciliation.filter_printed'))
                    ->falseLabel(__('accounting.reconciliation.filter_not_printed'))
                    ->queries(
                        true:  fn($query) => $query->whereHas('order', fn($o) => $o->where('is_printed', true)),
                        false: fn($query) => $query->whereHas('order', fn($o) => $o->where('is_printed', false)),
                        blank: fn($query) => $query,
                    ),

                SelectFilter::make('care_status')
                    ->label(__('accounting.reconciliation.filter_care'))
                    ->placeholder(__('accounting.reconciliation.filter_deposit_all'))
                    ->options(fn (): array => [
                        '__not_cared__' => __('accounting.reconciliation.filter_not_cared'),
                    ] + OrderCareStatus::toOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        if ($value === '__not_cared__') {
                            return $query->whereHas('order', fn (Builder $orderQuery): Builder => $orderQuery->whereNull('care_status'));
                        }

                        return $query->whereHas('order', fn (Builder $orderQuery): Builder => $orderQuery->where('care_status', $value));
                    })
                    ->searchable(),

                SelectFilter::make('product_name')
                    ->label(__('accounting.reconciliation.filter_product'))
                    ->options(fn() => self::getProductFilterOptions())
                    ->query(fn (Builder $query, array $data): Builder => self::applyProductFilter($query, $data['value'] ?? null))
                    ->searchable(),

                TernaryFilter::make('internal_reconciliation')
                    ->label(__('accounting.reconciliation.filter_internal_reconciliation'))
                    ->placeholder(__('accounting.reconciliation.filter_deposit_all'))
                    ->trueLabel(__('accounting.reconciliation.filter_reconciled'))
                    ->falseLabel(__('accounting.reconciliation.filter_not_reconciled'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->whereNotNull('confirmed_at')
                            ->where('status', '!=', ReconciliationStatus::PENDING->value),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $statusQuery): void {
                            $statusQuery
                                ->whereNull('confirmed_at')
                                ->orWhere('status', ReconciliationStatus::PENDING->value);
                        }),
                        blank: fn($query) => $query,
                    ),

                SelectFilter::make('shipping_method')
                    ->label(__('accounting.reconciliation.filter_shipping_method'))
                    ->options(ProviderShipping::getOptions())
                    ->query(fn($query, $data) => $query->when($data['value'], fn($q, $val) => $q->whereHas('order', fn($o) => $o->where('provider_shipping', $val)))),

                SelectFilter::make('warehouse_id')
                    ->label(__('accounting.reconciliation.filter_warehouse'))
                    ->options(function () {
                        return Warehouse::pluck('name', 'id');
                    })
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value']) || $data['value'] === '0') {
                            $query->whereHas('order', fn($q) => $q->where('warehouse_id', $data['value']));
                        }
                        return $query;
                    }),

                TernaryFilter::make('has_deposit')
                    ->label(__('accounting.reconciliation.filter_deposit'))
                    ->placeholder(__('accounting.reconciliation.filter_deposit_all'))
                    ->trueLabel(__('accounting.reconciliation.filter_deposit_yes'))
                    ->falseLabel(__('accounting.reconciliation.filter_deposit_no'))
                    ->queries(
                        true: fn(Builder $query) => $query->whereHas('order', fn($q) => $q->where('deposit', '>', 0)),
                        false: fn(Builder $query) => $query->whereHas('order', fn($q) => $q->where(function ($sub) {
                            $sub->whereNull('deposit')->orWhere('deposit', '<=', 0);
                        })),
                        blank: fn(Builder $query) => $query,
                    ),

                SelectFilter::make('sale_leader_id')
                    ->label(__('accounting.reconciliation.filter_sale_leader'))
                    ->options(fn () => self::getSaleLeaderFilterOptions())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return self::applySaleTeamConstraintToReconciliationQuery(
                            $query,
                            self::getSaleLeaderTeamIds($data['value'])
                        );
                    }),

                SelectFilter::make('sale_team_id')
                    ->label(__('accounting.reconciliation.filter_sale_team'))
                    ->options(fn ($livewire) => self::getSaleTeamFilterOptions(
                        self::getActiveFilterValue($livewire, 'sale_leader_id')
                    ))
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return self::applySaleTeamConstraintToReconciliationQuery($query, [(int) $data['value']]);
                    }),

                SelectFilter::make('sale_id')
                    ->label(__('accounting.reconciliation.filter_sale'))
                    ->options(fn ($livewire) => self::getSaleFilterOptions(
                        self::getActiveFilterValue($livewire, 'sale_leader_id'),
                        self::getActiveFilterValue($livewire, 'sale_team_id'),
                    ))
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value']) || $data['value'] === '0') {
                            $query->whereHas('order', fn($q) => $q->where('created_by', $data['value']));
                        }
                        return $query;
                    }),
                SelectFilter::make('follow_order')
                    ->label(__('accounting.reconciliation.filter_follow_order'))
                    ->placeholder(__('accounting.reconciliation.filter_follow_order_placeholder'))
                    ->options(fn (): array => self::getFollowOrderFilterOptions())
                    ->query(fn (Builder $query, array $data): Builder => self::applyFollowOrderFilter($query, $data['value'] ?? null))
                    ->searchable(),

                SelectFilter::make('qty_status')
                    ->label(__('accounting.reconciliation.filter_qty_status'))
                    ->placeholder(__('accounting.reconciliation.filter_qty_placeholder'))
                    ->options(fn (): array => self::getQuantityThresholdFilterOptions())
                    ->query(fn (Builder $query, array $data): Builder => self::applyQuantityThresholdFilter($query, $data['value'] ?? null))
                    ->searchable(),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(6)
            ->actions([
                Action::make('view_detail')
                    ->label(__('accounting.reconciliation.view_detail'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(__('accounting.reconciliation.order_detail_modal_title'))
                    ->modalWidth('4xl')
                    ->mountUsing(function ($form, $record, ReconciliationService $service) {
                        $result = $service->getOrderDetailFromGHN($record->id);

                        if ($result->isError()) {
                            $form->fill(['error' => $result->getMessage()]);
                            return;
                        }

                        $orderDetail = $result->getData();
                        $fee = $orderDetail['fee'] ?? [];

                        Caching::setCache(CacheKey::GHN_ORDER_DETAIL, $orderDetail, (string) $record->id, 15);

                        $form->fill([
                            'order_code' => $orderDetail['order_code'] ?? '',
                            'status' => GhnOrderStatus::tryFrom($orderDetail['status'] ?? '')?->label()
                                ?? ($orderDetail['status'] ?? ''),
                            'created_date' => $orderDetail['created_date'] ?? '',
                            'to_name' => $orderDetail['to_name'] ?? '',
                            'to_phone' => $orderDetail['to_phone'] ?? '',
                            'to_address' => $orderDetail['to_address'] ?? '',
                            'cod_amount' => $orderDetail['cod_amount'] ?? 0,
                            'payment_type_id' => $orderDetail['payment_type_id'] ?? null,
                            'weight' => $orderDetail['weight'] ?? 0,
                            'length' => $orderDetail['length'] ?? 0,
                            'width' => $orderDetail['width'] ?? 0,
                            'height' => $orderDetail['height'] ?? 0,
                            'note' => $orderDetail['note'] ?? '',
                            'content' => $orderDetail['content'] ?? '',
                            'required_note' => $orderDetail['required_note'] ?? '',
                            'main_service_fee' => $fee['main_service'] ?? 0,
                            'cod_fee' => $fee['cod_fee'] ?? 0,
                            'storage_fee' => $fee['station_do'] ?? 0,
                            'total_fee' => $orderDetail['total_fee'] ?? 0,
                        ]);
                    })
                    ->extraModalFooterActions(fn($record) => [
                        Action::make('sync_from_ghn')
                            ->label(__('accounting.reconciliation.sync_from_ghn'))
                            ->icon('heroicon-o-arrow-path')
                            ->color('info')
                            ->action(function ($record, ReconciliationService $service) {
                                $result = $service->getOrderDetailFromGHN($record->id);

                                if ($result->isError()) {
                                    Notification::make()
                                        ->danger()
                                        ->title(__('accounting.reconciliation.detail_load_failed'))
                                        ->body($result->getMessage())
                                        ->send();
                                    return;
                                }

                                $orderDetail = $result->getData();
                                Caching::setCache(CacheKey::GHN_ORDER_DETAIL, $orderDetail, (string) $record->id, 15);

                                Notification::make()
                                    ->success()
                                    ->title(__('accounting.reconciliation.detail_loaded'))
                                    ->body(__('accounting.reconciliation.close_and_reopen_modal'))
                                    ->send();
                            }),
                    ])
                    ->form([
                        Section::make(__('accounting.reconciliation.basic_info'))
                            ->schema([
                                TextInput::make('order_code')
                                    ->label(__('accounting.reconciliation.ghn_order_code'))
                                    ->disabled(),
                                TextInput::make('status')
                                    ->label(__('accounting.reconciliation.status'))
                                    ->disabled(),
                                TextInput::make('created_date')
                                    ->label(__('accounting.reconciliation.created_date'))
                                    ->disabled(),
                            ])
                            ->columns(3),

                        Section::make(__('accounting.reconciliation.receiver_info'))
                            ->schema([
                                TextInput::make('to_name')
                                    ->label(__('accounting.reconciliation.to_name'))
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                TextInput::make('to_phone')
                                    ->label(__('accounting.reconciliation.to_phone'))
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                Textarea::make('to_address')
                                    ->label(__('accounting.reconciliation.to_address'))
                                    ->rows(2)
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                            ])
                            ->columns(2),

                        Section::make(__('accounting.reconciliation.product_info'))
                            ->schema([
                                TextInput::make('weight')
                                    ->label(__('accounting.reconciliation.weight'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(20000)
                                    ->suffix('g')
                                    ->helperText(__('accounting.reconciliation.weight_help'))
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 1]),
                                        'max' => __('common.error.max_value', ['max' => 20000]),
                                    ]),
                                TextInput::make('length')
                                    ->label(__('accounting.reconciliation.length'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(200)
                                    ->suffix('cm')
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 1]),
                                        'max' => __('common.error.max_value', ['max' => 200]),
                                    ]),
                                TextInput::make('width')
                                    ->label(__('accounting.reconciliation.width'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(200)
                                    ->suffix('cm')
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 1]),
                                        'max' => __('common.error.max_value', ['max' => 200]),
                                    ]),
                                TextInput::make('height')
                                    ->label(__('accounting.reconciliation.height'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(200)
                                    ->suffix('cm')
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 1]),
                                        'max' => __('common.error.max_value', ['max' => 200]),
                                    ]),
                            ])
                            ->columns(4),

                        Section::make(__('accounting.reconciliation.notes'))
                            ->schema([
                                Textarea::make('note')
                                    ->label(__('accounting.reconciliation.note'))
                                    ->rows(2),
                                Textarea::make('content')
                                    ->label(__('accounting.reconciliation.content'))
                                    ->rows(2),
                                Select::make('required_note')
                                    ->label(__('accounting.reconciliation.required_note'))
                                    ->options(RequiredNote::getOptions())
                                    ->native(false)
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'in' => __('common.error.in'),
                                    ]),
                            ])
                            ->columns(1),

                        Section::make(__('accounting.reconciliation.financial_info'))
                            ->schema([
                                TextInput::make('cod_amount')
                                    ->label(__('accounting.reconciliation.cod_amount'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 0]),
                                    ])
                                    ->prefix('₫'),
                                Select::make('payment_type_id')
                                    ->label(__('accounting.reconciliation.payment_type'))
                                    ->options(PaymentType::toOptions())
                                    ->native(false)
                                    ->required()
                                    ->helperText(__('accounting.reconciliation.payment_type_help'))
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'in' => __('common.error.in'),
                                    ]),
                                TextInput::make('main_service_fee')
                                    ->label(__('accounting.reconciliation.main_service_fee'))
                                    ->prefix('₫')
                                    ->disabled(),
                                TextInput::make('cod_fee')
                                    ->label(__('accounting.reconciliation.cod_fee'))
                                    ->prefix('₫')
                                    ->disabled(),
                                TextInput::make('storage_fee')
                                    ->label(__('accounting.reconciliation.storage_fee'))
                                    ->prefix('₫')
                                    ->disabled(),
                                TextInput::make('total_fee')
                                    ->label(__('accounting.reconciliation.total_fee'))
                                    ->prefix('₫')
                                    ->disabled(),
                            ])
                            ->columns(3),

                        Section::make(__('accounting.reconciliation.notes'))
                            ->schema([
                                RichEditor::make('ghn_employee_note')
                                    ->label(__('accounting.reconciliation.accounting_note'))
                                    ->columnSpanFull()
                                    ->extraAttributes(['style' => 'min-height: 300px;']),
                            ]),

                        Textarea::make('error')
                            ->label(__('accounting.reconciliation.error'))
                            ->disabled()
                            ->visible(fn($get) => !empty($get('error'))),
                    ])
                    ->action(function ($record, array $data, ReconciliationService $service) {
                        $orderDetail = Caching::getCache(CacheKey::GHN_ORDER_DETAIL, (string) $record->id);

                        $fields = [
                            'cod_amount',
                            'to_name',
                            'to_phone',
                            'to_address',
                            'payment_type_id',
                            'weight',
                            'length',
                            'width',
                            'height',
                            'note',
                            'content',
                            'required_note',
                        ];

                        $updateData = [];

                        foreach ($fields as $field) {
                            if ($field === 'to_address' && isset($data['to_address'])) {
                                if ($data['to_address'] !== ($orderDetail['to_address'] ?? '')) {
                                    $updateData['to_address'] = $data['to_address'];
                                    $updateData['to_ward_code'] = $orderDetail['to_ward_code'] ?? null;
                                    $updateData['to_district_id'] = $orderDetail['to_district_id'] ?? null;
                                }
                                continue;
                            }
                            if (isset($data[$field]) && $data[$field] !== ($orderDetail[$field] ?? (is_numeric($data[$field]) ? 0 : ''))) {
                                $updateData[$field] = $data[$field];
                            }
                        }

                        if (count($updateData) === 0) {
                            Notification::make()
                                ->title(__('accounting.reconciliation.no_changes'))
                                ->body(__('accounting.reconciliation.no_update_required'))
                                ->info()
                                ->send();
                            return;
                        }

                        $result = $service->updateOrderOnGHN($record->id, $updateData);

                        if ($result->isError()) {
                            Notification::make()
                                ->title(__('accounting.reconciliation.order_update_failed'))
                                ->body($result->getMessage())
                                ->danger()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('accounting.reconciliation.order_updated'))
                                ->success()
                                ->send();
                        }
                    }),
                Action::make('confirm')
                    ->label(__('accounting.reconciliation.confirm_order'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === ReconciliationStatus::PENDING->value)
                    ->disabled(fn($record) => ! self::canConfirmRecord($record))
                    ->tooltip(fn($record) => ! self::canConfirmRecord($record)
                        ? __('accounting.reconciliation.confirm_requires_final_shipping_status')
                        : null)
                    ->action(function ($record, ReconciliationService $service) {
                        $result = $service->confirmReconciliation($record->id);

                        if ($result->isError()) {
                            Notification::make()
                                ->danger()
                                ->title(__('accounting.reconciliation.confirm_failed'))
                                ->body($result->getMessage())
                                ->send();
                        } else {
                            Notification::make()
                                ->success()
                                ->title(__('accounting.reconciliation.confirmed'))
                                ->send();
                        }
                    }),
                Action::make('print_order')
                    ->label(__('accounting.reconciliation.confirm_print'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(__('accounting.reconciliation.confirm_print'))
                    ->modalDescription(fn($record) => __('accounting.reconciliation.confirm_print_modal_desc', [
                        'code' => $record->order?->code ?? $record->ghn_order_code,
                    ]))
                    ->modalSubmitActionLabel(__('accounting.reconciliation.confirm_print_submit'))
                    ->visible(fn($record) => !($record->order?->is_printed ?? false))
                    ->action(function ($record) {
                        if ($record->order) {
                            $record->order->update(['is_printed' => true]);
                        }
                        Notification::make()
                            ->title(__('accounting.reconciliation.confirm_print_success_title'))
                            ->body(__('accounting.reconciliation.confirm_print_success_body', [
                                'code' => $record->order?->code ?? $record->ghn_order_code,
                            ]))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('bulk_confirm')
                    ->label(__('accounting.reconciliation.batch_confirm'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($livewire) => $livewire->activeTab === strtolower(ReconciliationStatus::PENDING->name) || $livewire->activeTab === 'all')
                    ->action(function (Collection $records, ReconciliationService $service) {
                        $count = 0;
                        $skipped = 0;

                        foreach ($records as $record) {
                            if ($service->canConfirmRecord($record)) {
                                $result = $service->confirmReconciliation($record->id);
                                if (!$result->isError()) {
                                    $count++;
                                } else {
                                    $skipped++;
                                }
                            } else {
                                $skipped++;
                            }
                        }

                        $notification = Notification::make()
                            ->success()
                            ->title(__('accounting.reconciliation.batch_success', ['count' => $count]));

                        if ($skipped > 0) {
                            $notification->body(__('accounting.reconciliation.bulk_confirm_skipped', ['count' => $skipped]));
                        }

                        $notification->send();
                    }),
                BulkAction::make('bulk_pay')
                    ->label(__('accounting.reconciliation.batch_pay'))
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn($livewire) => $livewire->activeTab === strtolower(ReconciliationStatus::CONFIRMED->name) || $livewire->activeTab === 'all')
                    ->action(function (Collection $records, ReconciliationService $service) {
                        $count = 0;
                        $skipped = 0;
                        foreach ($records as $record) {
                            if ($record->status === ReconciliationStatus::CONFIRMED->value) {
                                $record->update(['status' => ReconciliationStatus::PAID->value]);
                                $count++;
                            } else {
                                $skipped++;
                            }
                        }

                        $notification = Notification::make()
                            ->success()
                            ->title(__('accounting.reconciliation.batch_success', ['count' => $count]));

                        if ($skipped > 0) {
                            $notification->body(__('accounting.reconciliation.bulk_pay_skipped', ['count' => $skipped]));
                        }

                        $notification->send();
                    }),
                BulkAction::make('bulk_print')
                    ->label(__('accounting.reconciliation.bulk_print'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(__('accounting.reconciliation.bulk_print_modal_heading'))
                    ->modalSubmitActionLabel(__('accounting.reconciliation.bulk_print_submit'))
                    ->action(function (Collection $records) {
                        $orderIds = $records->pluck('order_id')->filter()->unique()->values();
                        \App\Models\Order::whereIn('id', $orderIds)->update(['is_printed' => true]);
                        Notification::make()
                            ->title(__('accounting.reconciliation.bulk_print_success', ['count' => $records->count()]))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('reconciliation_date', 'desc')
            ->actionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActionsAlignment('start')
            ->actionsColumnLabel(new HtmlString(
                '<div style="display: flex; width: 100%; justify-content: center; text-align: center;">'
                . e(__('accounting.reconciliation.actions_column_label')) .
                '</div>'
            ));
    }
}
