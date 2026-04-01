<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs text-slate-500">{{ __('marketing.cancel_return.cards.total_orders') }}</p>
                <p class="mt-1 text-xl font-bold text-slate-800">{{ number_format((int) ($summary['total_orders'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs text-slate-500">{{ __('marketing.cancel_return.cards.cancel_orders') }}</p>
                <p class="mt-1 text-xl font-bold text-red-700">{{ number_format((int) ($summary['cancel_orders'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs text-slate-500">{{ __('marketing.cancel_return.cards.return_exchange_orders') }}</p>
                <p class="mt-1 text-xl font-bold text-amber-700">{{ number_format((int) ($summary['return_exchange_orders'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs text-slate-500">{{ __('marketing.cancel_return.cards.cancel_rate') }}</p>
                <p class="mt-1 text-xl font-bold text-slate-800">{{ number_format((float) ($summary['cancel_rate'] ?? 0), 2) }}%</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs text-slate-500">{{ __('marketing.cancel_return.cards.exception_rate') }}</p>
                <p class="mt-1 text-xl font-bold text-slate-800">{{ number_format((float) ($summary['exception_rate'] ?? 0), 2) }}%</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs text-slate-500">{{ __('marketing.cancel_return.cards.junk_lead_rate') }}</p>
                <p class="mt-1 text-xl font-bold text-slate-800">{{ number_format((float) ($summary['junk_lead_rate'] ?? 0), 2) }}%</p>
            </div>
        </div>

        <x-filament::section>
            <x-slot name="heading">{{ __('marketing.cancel_return.title') }}</x-slot>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">{{ __('marketing.cancel_return.table.source') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('marketing.cancel_return.table.source_detail') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('marketing.cancel_return.table.exception_type') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('marketing.cancel_return.table.reason') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.cancel_return.table.orders') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.cancel_return.table.ratio') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="odd:bg-white even:bg-slate-50">
                                <td class="px-3 py-2">{{ $row['source'] }}</td>
                                <td class="px-3 py-2">{{ $row['source_detail'] }}</td>
                                <td class="px-3 py-2">{{ __('marketing.cancel_return.exception_type.' . $row['exception_type']) }}</td>
                                <td class="px-3 py-2">{{ $row['reason'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['orders'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['ratio'] }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-8 text-center text-slate-500" colspan="6">{{ __('marketing.common.no_data') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('marketing.cancel_return.risky_campaigns.title') }}</x-slot>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">{{ __('marketing.cancel_return.risky_campaigns.campaign') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.cancel_return.risky_campaigns.total_orders') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.cancel_return.risky_campaigns.risk_orders') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.cancel_return.risky_campaigns.risk_rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($riskyCampaigns as $row)
                            <tr class="odd:bg-white even:bg-slate-50">
                                <td class="px-3 py-2">{{ $row['campaign'] }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['total_orders']) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['risk_orders']) }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-red-700">{{ number_format((float) $row['risk_rate'], 2) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-8 text-center text-slate-500" colspan="4">{{ __('marketing.common.no_data') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
