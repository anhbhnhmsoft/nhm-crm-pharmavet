<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Tables;

use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Common\Constants\CacheKey;
use App\Common\Constants\Order\GhnOrderStatus;
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
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Team;
use App\Models\Product;
use App\Common\Constants\User\UserRole;
use App\Common\Constants\User\UserPosition;
use App\Common\Constants\Team\TeamType;
use App\Common\Constants\Order\OrderStatus;
use App\Services\Telesale\OrderFinanceService;
use Filament\Tables\Table;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Facades\Auth;

class ReconciliationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->label(new HtmlString('<div class="text-center font-semibold text-[11px] leading-tight">Trạng thái<br>đối soát</div>'))
                    ->formatStateUsing(fn($state) => ReconciliationStatus::getOptions()[$state] ?? '-')
                    ->alignCenter()
                    ->size('xs'),
                TextColumn::make('order.createdBy.name')
                    ->label(__('accounting.reconciliation.sale'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->order || !$record->order->createdBy) {
                            return '-';
                        }

                        $name = mb_strtoupper($record->order->createdBy->name);
                        $username = strtolower($record->order->createdBy->username);

                        return "<div class='text-sm font-medium text-gray-900 mb-0.5 text-center'>{$name}</div>
                                <div class='text-xs text-gray-400 text-center'>({$username})</div>";
                    })
                    ->searchable()
                    ->sortable()
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
                        $confirmedDate = $record->confirmed_at?->format('d/m/Y H:i') ?? '-';

                        return "
                            <div class='text-[10px] text-gray-500 whitespace-nowrap mb-1 text-center'>{$dataDate}</div>
                            <div class='text-xs font-bold text-primary-600 mb-1 text-center'>{$code}</div>
                            <div class='text-[10px] text-gray-500 whitespace-nowrap text-center'>{$confirmedDate}</div>
                        ";
                    })
                    ->copyable()
                    ->copyableState(fn($record) => $record->order?->code ?? $record->ghn_order_code)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $search = trim($search);

                        if ($search === '') {
                            return $query;
                        }

                        return $query->where(function (Builder $subQuery) use ($search): void {
                            $subQuery
                                ->where('ghn_order_code', 'like', "%{$search}%")
                                ->orWhereHas('order', fn(Builder $orderQuery) => $orderQuery->where('code', 'like', "%{$search}%"));
                        });
                    })
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
                        $updated = $record->updated_at?->format('d/m/Y H:i') ?? '-';
                        $care = $record->order?->updatedBy?->name ?? '-';
                        
                        $noteText = !empty($record->ghn_employee_note) ? strip_tags($record->ghn_employee_note) : strip_tags($record->note ?? '');
                        
                        if (empty($noteText)) {
                            $note = "<span style='color: #2563eb; font-weight: 600;'>" . __('accounting.reconciliation.no_note') . "</span>";
                        } else {
                            $note = e($noteText);
                        }

                        return "
                            <div class='w-full h-full cursor-pointer py-1'>
                                <div class='text-[10px] text-gray-500 text-center'>{$updated}</div>
                                <div class='text-xs text-gray-900 text-center'>{$care}</div>
                                <div class='text-[10px] text-center line-clamp-2'>{$note}</div>
                            </div>
                        ";
                    })
                    ->extraAttributes(['class' => 'cursor-pointer'])
                    ->alignCenter()
                    ->size('xs')
                    ->action(
                        Action::make('edit_note_inline_stacked')
                            ->label(__('accounting.reconciliation.accounting_note'))
                            ->icon('heroicon-o-pencil-square')
                            ->slideOver()
                            ->modalWidth('4xl')
                            ->form([
                                RichEditor::make('ghn_employee_note')
                                    ->label(__('accounting.reconciliation.accounting_note'))
                                    ->columnSpanFull()
                                    ->extraAttributes(['style' => 'min-height: 500px;']),
                            ])
                            ->fillForm(fn ($record) => ['ghn_employee_note' => $record->ghn_employee_note])
                            ->action(function ($record, array $data) {
                                $record->update(['ghn_employee_note' => $data['ghn_employee_note']]);
                                
                                Notification::make()
                                    ->success()
                                    ->title(__('accounting.reconciliation.order_updated'))
                                    ->send();
                            })
                    ),
                TextColumn::make('ghn_status_label')
                    ->label(new HtmlString('<div class="text-center font-semibold">' .
                        __('accounting.reconciliation.status_update_date') . '<br>' .
                        __('accounting.reconciliation.shipping_status') . '<br>' .
                        __('accounting.reconciliation.ghn_post_date') .
                        '</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $updated = $record->updated_at?->format('d/m/Y H:i') ?? '-';
                        $status = $record->order?->ghn_status;
                        $label = GhnOrderStatus::getLabel($status);
                        if (($label === '-' || empty($label)) && !empty($record->ghn_status_label)) {
                            $label = $record->ghn_status_label;
                        }

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
                    ->label(new HtmlString('<div class="text-center font-semibold text-[11px] leading-tight">Tiền thu<br>của khách</div>'))
                    ->money('VND')
                    ->formatStateUsing(function ($state, $record) {
                        return $record->order?->amount_recived_from_customer ?? $record->cod_amount;
                    })
                    ->alignEnd()
                    ->size('xs')
                    ->width('80px')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('shipping_fee')
                    ->label(new HtmlString('<div class="text-center font-semibold text-[11px] leading-tight">Giá dịch vụ<br>VC</div>'))
                    ->money('VND')
                    ->alignEnd()
                    ->size('xs')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('order.cod_support_amount')
                    ->label(new HtmlString('<div class="text-center font-semibold text-[11px] leading-tight">Phí VC<br>hỗ trợ khách</div>'))
                    ->money('VND')
                    ->formatStateUsing(fn($state, $record) => $record->order?->cod_support_amount ?? $record->order?->amout_support_fee)
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
                        $dateType = $livewire->tableFilters['date_type']['value'] ?? 'reconciliation_date';

                        if (
                            filled($data['from'] ?? null)
                            && filled($data['to'] ?? null)
                            && Carbon::parse($data['from'])->gt(Carbon::parse($data['to']))
                        ) {
                            return $query->whereRaw('1 = 0');
                        }

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

                TernaryFilter::make('care_status')
                    ->label(__('accounting.reconciliation.filter_care'))
                    ->placeholder(__('accounting.reconciliation.filter_deposit_all'))
                    ->trueLabel(__('accounting.reconciliation.filter_cared'))
                    ->falseLabel(__('accounting.reconciliation.filter_not_cared'))
                    ->queries(
                        true:  fn($query) => $query->whereHas('order', fn($o) => $o->whereNotNull('care_updated_at')),
                        false: fn($query) => $query->whereHas('order', fn($o) => $o->whereNull('care_updated_at')),
                        blank: fn($query) => $query,
                    ),

                SelectFilter::make('care_staff_id')
                    ->label(__('accounting.reconciliation.filter_care_staff'))
                    ->options(fn() => User::where('role', UserRole::SALE->value)->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn($query, $data) => $query->when($data['value'], fn($q, $val) => $q->whereHas('order', fn($o) => $o->where('updated_by', $val)))),

                SelectFilter::make('product_id')
                    ->label(__('accounting.reconciliation.filter_product'))
                    ->options(fn() => Product::pluck('name', 'id'))
                    ->query(fn($query, $data) => $query->when($data['value'], fn($q, $val) => $q->whereHas('order.items', fn($it) => $it->where('product_id', $val))))
                    ->searchable(),

                TernaryFilter::make('internal_reconciliation')
                    ->label(__('accounting.reconciliation.filter_internal_reconciliation'))
                    ->placeholder(__('accounting.reconciliation.filter_deposit_all'))
                    ->trueLabel(__('accounting.reconciliation.filter_reconciled'))
                    ->falseLabel(__('accounting.reconciliation.filter_not_reconciled'))
                    ->queries(
                        true:  fn($query) => $query->where('is_internal_reconciled', true),
                        false: fn($query) => $query->where('is_internal_reconciled', false),
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
                    ->options(fn() => User::where('role', UserRole::SALE->value)->where('position', UserPosition::LEADER->value)->pluck('name', 'id'))
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $leader = filled($data['value'] ?? null)
                            ? User::query()
                                ->where('organization_id', self::currentOrganizationId())
                                ->find($data['value'])
                            : null;

                        if (!$leader?->team_id) {
                            return $query;
                        }

                        return $query->whereHas('order.createdBy', fn(Builder $userQuery) => $userQuery->where('team_id', $leader->team_id));
                    }),

                SelectFilter::make('sale_team_id')
                    ->label(__('accounting.reconciliation.filter_sale_team'))
                    ->options(fn() => Team::where('type', TeamType::SALE->value)->pluck('name', 'id'))
                    ->query(fn($query, $data) => $query->when($data['value'], fn($q, $val) => $q->whereHas('order.createdBy', fn($uq) => $uq->where('team_id', $val)))),

                SelectFilter::make('sale_id')
                    ->label(__('accounting.reconciliation.filter_sale'))
                    ->options(function () {
                        return User::where('role', UserRole::SALE->value)->pluck('name', 'id');
                    })
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value']) || $data['value'] === '0') {
                            $query->whereHas('order', fn($q) => $q->where('created_by', $data['value']));
                        }
                        return $query;
                    }),

                SelectFilter::make('mkt_leader_id')
                    ->label(__('accounting.reconciliation.filter_mkt_leader'))
                    ->options(fn() => User::where('role', UserRole::MARKETING->value)->where('position', UserPosition::LEADER->value)->pluck('name', 'id'))
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $leader = filled($data['value'] ?? null)
                            ? User::query()
                                ->where('organization_id', self::currentOrganizationId())
                                ->find($data['value'])
                            : null;

                        if (!$leader?->team_id) {
                            return $query;
                        }

                        return self::applyMarketingTeamFilter($query, (int) $leader->team_id);
                    }),

                SelectFilter::make('mkt_team_id')
                    ->label(__('accounting.reconciliation.filter_mkt_team'))
                    ->options(fn() => Team::where('type', TeamType::MARKETING->value)->pluck('name', 'id'))
                    ->query(fn($query, $data) => $query->when($data['value'], fn($q, $val) => $q->whereHas('order.customer.assignedStaff', fn($uq) => $uq->where('team_id', $val)))),

                SelectFilter::make('mkt_id')
                    ->label(__('accounting.reconciliation.filter_mkt'))
                    ->options(fn() => User::where('role', UserRole::MARKETING->value)->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn($query, $data) => $query->when($data['value'], fn($q, $val) => $q->whereHas('order.customer.assignedStaff', fn($uq) => $uq->where('id', $val)))),

                SelectFilter::make('status')
                    ->label(__('accounting.reconciliation.filter_follow_order'))
                    ->options(OrderStatus::toOptions())
                    ->query(fn($query, $data) => $query->when($data['value'], fn($q, $val) => $q->whereHas('order', fn($o) => $o->where('status', $val)))),

                SelectFilter::make('qty_status')
                    ->label(__('accounting.reconciliation.filter_qty_status'))
                    ->options([
                        'all'     => __('accounting.reconciliation.filter_qty_all'),
                        'partial' => __('accounting.reconciliation.filter_qty_partial'),
                    ])
                    ->query(fn($query, $data) => $query->when(
                        isset($data['value']) && $data['value'] !== '',
                        function ($q) use ($data) {
                            if ($data['value'] === 'all') {
                                // Toàn bộ: tổng qty order_items == tổng qty đã xuất trong inventory_ticket_details
                                return $q->whereHas('order', function ($o) {
                                    $o->whereColumn(
                                        'id',
                                        'id'
                                    )->whereRaw(
                                        '(SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi WHERE oi.order_id = orders.id)
                                         = (SELECT COALESCE(SUM(itd.quantity),0) FROM inventory_ticket_details itd
                                            INNER JOIN inventory_tickets it ON itd.inventory_ticket_id = it.id
                                            WHERE it.order_id = orders.id AND it.type = 2 AND it.status = 2)'
                                    );
                                });
                            }
                            // Một phần: số lượng đã xuất < tổng số lượng đặt
                            return $q->whereHas('order', function ($o) {
                                $o->whereRaw(
                                    '(SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi WHERE oi.order_id = orders.id)
                                     > (SELECT COALESCE(SUM(itd.quantity),0) FROM inventory_ticket_details itd
                                        INNER JOIN inventory_tickets it ON itd.inventory_ticket_id = it.id
                                        WHERE it.order_id = orders.id AND it.type = 2 AND it.status = 2)'
                                );
                            });
                        }
                    )),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(6)
            ->actions([
                Action::make('edit_order_financials')
                    ->label('Sửa số liệu đơn hàng')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn($record) => (bool) $record->order)
                    ->mountUsing(function ($form, $record) {
                        $order = $record->order?->loadMissing('items');

                        if (!$order) {
                            return;
                        }

                        $productTotal = (float) $order->items->sum(fn($item) => (float) $item->price * (int) $item->quantity);
                        $productDiscount = $productTotal * (((float) $order->ck1 + (float) $order->ck2) / 100);
                        $manualDiscount = max(0, (float) $order->discount - $productDiscount);

                        $form->fill([
                            'product_total' => round($productTotal, 2),
                            'ck1' => (float) $order->ck1,
                            'ck2' => (float) $order->ck2,
                            'discount' => round($manualDiscount, 2),
                            'shipping_fee' => (float) $order->shipping_fee,
                            'cod_fee' => (float) $order->cod_fee,
                            'deposit' => (float) $order->deposit,
                            'cod_support_amount' => (float) ($order->cod_support_amount ?? $order->amout_support_fee ?? 0),
                            'total_amount' => (float) $order->total_amount,
                            'collect_amount' => (float) $order->collect_amount,
                        ]);
                    })
                    ->form([
                        Hidden::make('product_total'),
                        Hidden::make('ck1'),
                        Hidden::make('ck2'),
                        Hidden::make('cod_fee'),
                        TextInput::make('discount')
                            ->label('Chiết khấu điều chỉnh')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('₫')
                            ->live()
                            ->helperText('Hệ thống giữ nguyên CK sản phẩm, chỉ sửa phần điều chỉnh kế toán.')
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'numeric' => __('common.error.numeric'),
                                'min' => __('common.error.min_value', ['min' => 0]),
                            ])
                            ->afterStateUpdated(fn($state, $set, $get) => self::updateOrderFinancialPreview($set, $get)),
                        TextInput::make('shipping_fee')
                            ->label('Phí VC thu của khách')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('₫')
                            ->live()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'numeric' => __('common.error.numeric'),
                                'min' => __('common.error.min_value', ['min' => 0]),
                            ])
                            ->afterStateUpdated(fn($state, $set, $get) => self::updateOrderFinancialPreview($set, $get)),
                        TextInput::make('deposit')
                            ->label('Đặt cọc')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('₫')
                            ->live()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'numeric' => __('common.error.numeric'),
                                'min' => __('common.error.min_value', ['min' => 0]),
                            ])
                            ->afterStateUpdated(fn($state, $set, $get) => self::updateOrderFinancialPreview($set, $get)),
                        TextInput::make('cod_support_amount')
                            ->label('Phí VC hỗ trợ khách')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('₫')
                            ->live()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'numeric' => __('common.error.numeric'),
                                'min' => __('common.error.min_value', ['min' => 0]),
                            ])
                            ->afterStateUpdated(fn($state, $set, $get) => self::updateOrderFinancialPreview($set, $get)),
                        TextInput::make('total_amount')
                            ->label('Tổng tiền')
                            ->numeric()
                            ->readOnly()
                            ->prefix('₫'),
                        TextInput::make('collect_amount')
                            ->label('Tiền thu của khách')
                            ->numeric()
                            ->readOnly()
                            ->prefix('₫'),
                    ])
                    ->action(function ($record, array $data, OrderFinanceService $financeService) {
                        $order = $record->order?->loadMissing('items');

                        if (!$order) {
                            Notification::make()
                                ->danger()
                                ->title('Không tìm thấy đơn hàng cần chỉnh sửa')
                                ->send();

                            return;
                        }

                        $finance = $financeService->calculateCollectAmount([
                            'product_total' => (float) ($data['product_total'] ?? 0),
                            'discount' => (float) ($data['discount'] ?? 0),
                            'ck1' => (float) ($data['ck1'] ?? 0),
                            'ck2' => (float) ($data['ck2'] ?? 0),
                            'shipping_fee' => (float) ($data['shipping_fee'] ?? 0),
                            'cod_fee' => (float) ($data['cod_fee'] ?? 0),
                            'deposit' => (float) ($data['deposit'] ?? 0),
                            'cod_support_amount' => (float) ($data['cod_support_amount'] ?? 0),
                        ]);

                        $supportAmount = (float) ($data['cod_support_amount'] ?? 0);

                        $order->update([
                            'discount' => (float) $finance['total_discount'],
                            'shipping_fee' => (float) ($data['shipping_fee'] ?? 0),
                            'deposit' => (float) ($data['deposit'] ?? 0),
                            'cod_support_amount' => $supportAmount,
                            'amout_support_fee' => $supportAmount,
                            'collect_amount' => (float) $finance['collect_amount'],
                            'total_amount' => (float) $finance['gross_total'],
                            'updated_by' => Auth::id(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Sửa số liệu đơn hàng thành công')
                            ->send();
                    }),
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
                                    ->minValue(0)
                                    ->suffix('g')
                                    ->helperText(__('accounting.reconciliation.weight_help'))
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 0]),
                                    ]),
                                TextInput::make('length')
                                    ->label(__('accounting.reconciliation.length'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->suffix('cm')
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 0]),
                                    ]),
                                TextInput::make('width')
                                    ->label(__('accounting.reconciliation.width'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->suffix('cm')
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 0]),
                                    ]),
                                TextInput::make('height')
                                    ->label(__('accounting.reconciliation.height'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->suffix('cm')
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 0]),
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
                                    ->native(false),
                            ])
                            ->columns(1),

                        Section::make(__('accounting.reconciliation.financial_info'))
                            ->schema([
                                TextInput::make('cod_amount')
                                    ->label(__('accounting.reconciliation.cod_amount'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->prefix('₫')
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 0]),
                                    ]),
                                TextInput::make('payment_type_id')
                                    ->label(__('accounting.reconciliation.payment_type'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->helperText(__('accounting.reconciliation.payment_type_help'))
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'numeric' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 1]),
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
                BulkAction::make('bulk_print')
                    ->label('In hàng loạt')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->action(function (Collection $records, $livewire) {
                        $printableRecords = $records
                            ->loadMissing(['order.customer', 'order.items.product'])
                            ->filter(fn($record) => (bool) $record->order)
                            ->values();

                        if ($printableRecords->isEmpty()) {
                            Notification::make()
                                ->danger()
                                ->title('Không có đơn hàng hợp lệ để in')
                                ->send();

                            return;
                        }

                        $html = self::buildBulkPrintHtml($printableRecords);

                        if (method_exists($livewire, 'js')) {
                            $livewire->js(<<<JS
                                const printWindow = window.open('', '_blank');
                                if (printWindow) {
                                    printWindow.document.open();
                                    printWindow.document.write({$html});
                                    printWindow.document.close();
                                    printWindow.focus();
                                    setTimeout(() => printWindow.print(), 250);
                                }
                            JS);
                        }

                        Notification::make()
                            ->success()
                            ->title('Đã mở popup in hàng loạt')
                            ->send();
                    }),
                BulkAction::make('bulk_confirm')
                    ->label(__('accounting.reconciliation.batch_confirm'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($livewire) => $livewire->activeTab === strtolower(ReconciliationStatus::PENDING->name) || $livewire->activeTab === 'all')
                    ->action(function (Collection $records, ReconciliationService $service) {
                        $count = 0;
                        $skipped = 0;

                        $finalStatuses = collect([
                            GhnOrderStatus::DELIVERED,
                            GhnOrderStatus::RETURNED,
                            GhnOrderStatus::CANCEL,
                            GhnOrderStatus::LOST,
                            GhnOrderStatus::DAMAGE,
                        ])
                            ->flatMap(fn(GhnOrderStatus $status) => [
                                mb_strtolower($status->value),
                                mb_strtolower($status->label()),
                            ])
                            ->push('cancelled')
                            ->unique()
                            ->values()
                            ->all();

                        foreach ($records as $record) {
                            $ghnStatuses = collect([
                                $record->order?->ghn_status,
                                $record->ghn_status_label,
                            ])
                                ->filter()
                                ->map(fn($status) => mb_strtolower(trim((string) $status)));

                            $isFinalStatus = $ghnStatuses->contains(
                                fn(string $status) => in_array($status, $finalStatuses, true)
                            );

                            if ($isFinalStatus && $record->status === ReconciliationStatus::PENDING->value) {
                                $result = $service->confirmReconciliation($record->id);
                                if (!$result->isError()) {
                                    $count++;
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
            ->actionsColumnLabel(__('accounting.reconciliation.actions_column_label'));
    }

    private static function currentOrganizationId(): ?int
    {
        return Auth::user()?->organization_id;
    }

    private static function userOptions(?int $role = null, ?int $position = null): array
    {
        $query = User::query()
            ->where('organization_id', self::currentOrganizationId())
            ->where('disable', false)
            ->orderBy('name');

        if ($role !== null) {
            $query->where('role', $role);
        }

        if ($position !== null) {
            $query->where('position', $position);
        }

        return $query->pluck('name', 'id')->all();
    }

    private static function teamOptions(int $type): array
    {
        return Team::query()
            ->where('organization_id', self::currentOrganizationId())
            ->where('type', $type)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function productOptions(): array
    {
        return Product::query()
            ->where('organization_id', self::currentOrganizationId())
            ->whereIn('id', function ($query): void {
                $query->from('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('reconciliations', 'reconciliations.order_id', '=', 'orders.id')
                    ->where('reconciliations.organization_id', self::currentOrganizationId())
                    ->select('order_items.product_id');
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function applyMarketingStaffFilter(Builder $query, int $staffId): Builder
    {
        return $query->whereHas('order.customer.assignedStaff', function (Builder $staffQuery) use ($staffId): void {
            $staffQuery
                ->where('users.id', $staffId)
                ->where('users.role', UserRole::MARKETING->value);
        });
    }

    private static function applyMarketingTeamFilter(Builder $query, int $teamId): Builder
    {
        return $query->whereHas('order.customer.assignedStaff', function (Builder $staffQuery) use ($teamId): void {
            $staffQuery
                ->where('users.role', UserRole::MARKETING->value)
                ->where('users.team_id', $teamId);
        });
    }

    private static function updateOrderFinancialPreview(callable $set, callable $get): void
    {
        $productTotal = (float) ($get('product_total') ?? 0);
        $discount = (float) ($get('discount') ?? 0);
        $ck1 = (float) ($get('ck1') ?? 0);
        $ck2 = (float) ($get('ck2') ?? 0);
        $shippingFee = (float) ($get('shipping_fee') ?? 0);
        $codFee = (float) ($get('cod_fee') ?? 0);
        $deposit = (float) ($get('deposit') ?? 0);
        $codSupportAmount = (float) ($get('cod_support_amount') ?? 0);

        $productDiscount = $productTotal * (($ck1 + $ck2) / 100);
        $totalDiscount = $productDiscount + $discount;
        $grossTotal = max(0, $productTotal - $totalDiscount + $shippingFee + $codFee);
        $collectAmount = max(0, $grossTotal - $deposit - $codSupportAmount);

        $set('total_amount', round($grossTotal, 2));
        $set('collect_amount', round($collectAmount, 2));
    }

    private static function buildBulkPrintHtml(Collection $records): string
    {
        $cards = $records->map(function ($record): string {
            $order = $record->order;
            $customerName = e($order?->customer?->username ?? $record->ghn_to_name ?? '-');
            $customerPhone = e($order?->customer?->phone ?? $record->ghn_to_phone ?? '-');
            $address = e($order?->shipping_address ?? $record->ghn_to_address ?? '-');
            $orderCode = e($order?->code ?? $record->ghn_order_code ?? '-');
            $ghnCode = e($record->ghn_order_code ?? '-');
            $note = e($order?->note ?? '');
            $amount = number_format((float) ($order?->total_amount ?? $record->cod_amount ?? 0), 0, ',', '.');

            $items = $order?->items?->map(function ($item): string {
                $productName = e($item->product?->name ?? 'Sản phẩm');
                $quantity = number_format((float) $item->quantity, 0, ',', '.');

                return "<li>{$productName} x {$quantity}</li>";
            })->implode('') ?? '<li>Không có dữ liệu sản phẩm</li>';

            return <<<HTML
                <article class="print-card">
                    <header class="print-card__header">
                        <div>
                            <h2>Mã đơn: {$orderCode}</h2>
                            <p>Mã GHN: {$ghnCode}</p>
                        </div>
                        <div class="print-card__amount">{$amount} đ</div>
                    </header>
                    <section class="print-card__body">
                        <p><strong>Khách hàng:</strong> {$customerName}</p>
                        <p><strong>Số điện thoại:</strong> {$customerPhone}</p>
                        <p><strong>Địa chỉ:</strong> {$address}</p>
                        <p><strong>Ghi chú:</strong> {$note}</p>
                        <div class="print-card__items">
                            <strong>Sản phẩm</strong>
                            <ul>{$items}</ul>
                        </div>
                    </section>
                </article>
            HTML;
        })->implode('');

        return json_encode(<<<HTML
            <!DOCTYPE html>
            <html lang="vi">
            <head>
                <meta charset="utf-8">
                <title>In hàng loạt đơn đối soát</title>
                <style>
                    * { box-sizing: border-box; }
                    body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
                    .print-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
                    .print-card { border: 1px solid #d1d5db; border-radius: 12px; padding: 16px; break-inside: avoid; }
                    .print-card__header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 12px; }
                    .print-card__header h2 { margin: 0 0 4px; font-size: 18px; }
                    .print-card__header p { margin: 0; color: #4b5563; font-size: 13px; }
                    .print-card__amount { font-size: 18px; font-weight: 700; color: #1d4ed8; white-space: nowrap; }
                    .print-card__body p { margin: 0 0 8px; line-height: 1.4; }
                    .print-card__items ul { margin: 8px 0 0; padding-left: 18px; }
                    @media print {
                        body { margin: 12px; }
                        .print-grid { gap: 12px; }
                        .print-card { page-break-inside: avoid; }
                    }
                </style>
            </head>
            <body>
                <div class="print-grid">
                    {$cards}
                </div>
            </body>
            </html>
        HTML);
    }
}
