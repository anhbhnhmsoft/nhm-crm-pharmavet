<x-filament-panels::page>
    @vite(['resources/css/app.css'])
    <form wire:submit="generateReport" class="space-y-4" novalidate>
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('accounting.report.generate_button') }}</x-filament::button>
        </div>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
        <x-filament::section class="border-l-4 border-success-500">
            <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">{{ __('accounting.fund_ledger.total_in') }}</p>
            <p class="text-2xl font-black text-success-600">
                {{ number_format((float) ($summary['total_in'] ?? 0), 2) }} <span class="text-xs font-normal text-gray-400">VND</span>
            </p>
        </x-filament::section>

        <x-filament::section class="border-l-4 border-danger-500">
            <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">{{ __('accounting.fund_ledger.total_out') }}</p>
            <p class="text-2xl font-black text-danger-600">
                {{ number_format((float) ($summary['total_out'] ?? 0), 2) }} <span class="text-xs font-normal text-gray-400">VND</span>
            </p>
        </x-filament::section>

        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 border-l-4 border-primary-600">
            <p class="text-[10px] font-black text-gray-400 uppercase mb-1">{{ __('accounting.fund_ledger.balance') }}</p>
            <p class="text-3xl font-black text-primary-600">
                {{ number_format((float) ($summary['balance'] ?? 0), 2) }} <span class="text-xs font-normal text-gray-400">VND</span>
            </p>
        </div>
    </div>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('accounting.fund_ledger.compare_previous') }}</x-slot>
        <div class="mb-2 text-xs text-gray-600">
            {{ __('accounting.fund_ledger.current_range') }}:
            {{ filled(data_get($data, 'from_date')) ? \Illuminate\Support\Carbon::parse(data_get($data, 'from_date'))->format('d/m/Y') : '-' }}
            -
            {{ filled(data_get($data, 'to_date')) ? \Illuminate\Support\Carbon::parse(data_get($data, 'to_date'))->format('d/m/Y') : '-' }}
        </div>
        <div class="mb-2 text-xs text-gray-600">
            {{ __('accounting.fund_ledger.previous_range') }}:
            {{ filled(data_get($compare, 'previous_range.0')) ? \Illuminate\Support\Carbon::parse(data_get($compare, 'previous_range.0'))->format('d/m/Y') : '-' }}
            -
            {{ filled(data_get($compare, 'previous_range.1')) ? \Illuminate\Support\Carbon::parse(data_get($compare, 'previous_range.1'))->format('d/m/Y') : '-' }}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            @php($varIn = (float) data_get($compare, 'variance.total_in', 0))
            @php($varOut = (float) data_get($compare, 'variance.total_out', 0))
            @php($varBalance = (float) data_get($compare, 'variance.balance', 0))
            <div class="{{ $varIn >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ __('accounting.fund_ledger.variance_in') }}:
                {{ $varIn >= 0 ? '▲' : '▼' }} {{ number_format($varIn, 2) }}%
            </div>
            <div class="{{ $varOut >= 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ __('accounting.fund_ledger.variance_out') }}:
                {{ $varOut >= 0 ? '▲' : '▼' }} {{ number_format($varOut, 2) }}%
            </div>
            <div class="{{ $varBalance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ __('accounting.fund_ledger.variance_balance') }}:
                {{ $varBalance >= 0 ? '▲' : '▼' }} {{ number_format($varBalance, 2) }}%
            </div>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('accounting.fund_ledger.entries') }}</x-slot>
        <div class="overflow-hidden rounded-lg border border-gray-100 dark:border-gray-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 font-bold uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('accounting.fund_transaction.transaction_date') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('accounting.fund_transaction.transaction_code') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('accounting.fund_transaction.counterparty_name') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('accounting.fund_ledger.in_amount') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('accounting.fund_ledger.out_amount') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('accounting.fund_transaction.currency') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('accounting.fund_transaction.balance_after') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('accounting.fund_transaction.description') }}</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($rows as $row)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-900/10 transition-colors uppercase">
                        <td class="px-4 py-3 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($row['transaction_date'])->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 font-mono font-bold text-primary-600 truncate max-w-[120px]">{{ $row['transaction_code'] }}</td>
                        <td class="px-4 py-3">{{ $row['counterparty_name'] ?? '-' }}</td>
                        <td class="px-4 py-3 text-right font-bold text-success-600">
                            {{ (int) ($row['type'] ?? 0) === \App\Common\Constants\Organization\FundTransactionType::DEPOSIT->value ? number_format((float) ($row['amount'] ?? 0), 2) : '-' }}
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-danger-600">
                            {{ (int) ($row['type'] ?? 0) === \App\Common\Constants\Organization\FundTransactionType::WITHDRAW->value ? number_format((float) ($row['amount'] ?? 0), 2) : '-' }}
                        </td>
                        <td class="px-4 py-3 text-gray-400">{{ $row['currency'] ?? 'VND' }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-800 dark:text-gray-200">{{ number_format((float) ($row['balance_after'] ?? 0), 2) }}</td>
                        <td class="px-4 py-3 text-gray-500 italic max-w-xs truncate">{{ $row['description'] ?? '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-8 text-center text-gray-400 bg-gray-50/50" colspan="8">{{ __('common.error.data_not_found') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
