<?php

namespace App\Filament\Clusters\Accounting\Resources\ExchangeRates\Pages;

use App\Filament\Clusters\Accounting\Resources\ExchangeRates\ExchangeRateResource;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListExchangeRates extends ListRecords
{
    protected static string $resource = ExchangeRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('accounting.exchange_rate.create'))
                ->using(function (array $data): ExchangeRate {
                    return ExchangeRate::query()->updateOrCreate(
                        [
                            'organization_id' => Auth::user()->organization_id,
                            'rate_date' => $data['rate_date'],
                            'to_currency' => $data['to_currency'],
                        ],
                        [
                            'from_currency' => $data['from_currency'],
                            'rate' => $data['rate'],
                            'source' => $data['source'] ?? 'manual',
                            'note' => $data['note'] ?? null,
                            'created_by' => Auth::id(),
                        ],
                    );
                }),

            Action::make('sync_from_api')
                ->label(__('accounting.exchange_rate.sync_action'))
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->modalHeading(__('accounting.exchange_rate.sync_modal_heading'))
                ->modalDescription(__('accounting.exchange_rate.sync_modal_desc_single_pair'))
                ->modalSubmitAction(false)
                ->form([
                    DatePicker::make('rate_date')
                        ->label(__('accounting.exchange_rate.rate_date'))
                        ->helperText(__('accounting.exchange_rate.sync_date_help'))
                        ->default(now())
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->live(),
                    Placeholder::make('manual_conflict_hint')
                        ->label(__('accounting.exchange_rate.conflict_warning'))
                        ->content(function (callable $get): string {
                            $rateDate = $get('rate_date');

                            if (blank($rateDate)) {
                                return __('accounting.exchange_rate.manual_conflict_check_prompt');
                            }

                            $manualRate = ExchangeRate::query()
                                ->where('organization_id', Auth::user()->organization_id)
                                ->whereDate('rate_date', $rateDate)
                                ->where('from_currency', 'USD')
                                ->where('to_currency', 'VND')
                                ->where('source', 'manual')
                                ->first();

                            if (! $manualRate) {
                                return __('accounting.exchange_rate.manual_conflict_absent');
                            }

                            $rate = rtrim(rtrim(number_format((float) $manualRate->rate, 6, '.', ','), '0'), '.');

                            return __('accounting.exchange_rate.manual_conflict_present', ['rate' => $rate]);
                        }),
                ])
                ->extraModalFooterActions(fn (Action $action): array => [
                    $action->makeModalSubmitAction('overwrite_manual_rate', arguments: ['overwrite_manual' => true])
                        ->label(__('accounting.exchange_rate.overwrite_manual'))
                        ->color('danger'),
                ])
                ->action(function (array $data, array $arguments, ExchangeRateService $service): void {
                    $result = $service->syncUsdVndRate(
                        Auth::user()->organization_id,
                        (string) $data['rate_date'],
                        (bool) ($arguments['overwrite_manual'] ?? false),
                    );

                    if (($result['status'] ?? 'failed') === 'failed') {
                        Notification::make()
                            ->danger()
                            ->title(__('accounting.exchange_rate.sync_failed'))
                            ->body(__('accounting.exchange_rate.api_fetch_failed'))
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title(__('accounting.exchange_rate.sync_success'))
                        ->body(match ($result['status'] ?? null) {
                            'created' => __('accounting.exchange_rate.synced_created'),
                            'updated' => __('accounting.exchange_rate.synced_updated'),
                            'skipped_manual' => __('accounting.exchange_rate.synced_skipped_manual'),
                            'overwritten_manual' => __('accounting.exchange_rate.synced_overwritten_manual'),
                            'unchanged_api' => __('accounting.exchange_rate.synced_unchanged_api'),
                            default => __('accounting.exchange_rate.synced', ['count' => 1]),
                        })
                        ->send();
                }),
        ];
    }
}
