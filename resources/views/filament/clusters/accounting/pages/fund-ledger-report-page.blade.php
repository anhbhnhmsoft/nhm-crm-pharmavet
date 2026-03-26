<x-filament-panels::page>
    <form wire:submit="generateReport" class="space-y-4">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('telesale.reports.generate') }}</x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('accounting.fund_ledger.summary') }}</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div>{{ __('accounting.fund_ledger.total_in') }}: <b>{{ number_format((float) ($summary['total_in'] ?? 0), 2) }}</b></div>
            <div>{{ __('accounting.fund_ledger.total_out') }}: <b>{{ number_format((float) ($summary['total_out'] ?? 0), 2) }}</b></div>
            <div>{{ __('accounting.fund_ledger.balance') }}: <b>{{ number_format((float) ($summary['balance'] ?? 0), 2) }}</b></div>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('accounting.fund_ledger.compare_previous') }}</x-slot>
        <div class="mb-2 text-xs text-gray-600">
            {{ __('accounting.fund_ledger.previous_range') }}:
            {{ data_get($compare, 'previous_range.0') }} - {{ data_get($compare, 'previous_range.1') }}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            @php($varIn = (float) data_get($compare, 'variance.total_in', 0))
            @php($varOut = (float) data_get($compare, 'variance.total_out', 0))
            @php($varBalance = (float) data_get($compare, 'variance.balance', 0))
            <div class="{{ $varIn >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ __('accounting.fund_ledger.variance_in') }}:
                {{ $varIn >= 0 ? '▲' : '▼' }} {{ number_format($varIn, 2) }}%
            </div>
            <div class="{{ $varOut >= 0 ? 'text-green-600' : 'text-red-600' }}">
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
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                <tr class="border-b">
                    <th class="text-left p-2">{{ __('accounting.fund_transaction.transaction_date') }}</th>
                    <th class="text-left p-2">{{ __('accounting.fund_transaction.transaction_code') }}</th>
                    <th class="text-left p-2">{{ __('accounting.fund_transaction.counterparty_name') }}</th>
                    <th class="text-right p-2">{{ __('accounting.fund_ledger.in_amount') }}</th>
                    <th class="text-right p-2">{{ __('accounting.fund_ledger.out_amount') }}</th>
                    <th class="text-left p-2">{{ __('accounting.fund_transaction.currency') }}</th>
                    <th class="text-right p-2">{{ __('accounting.fund_transaction.balance_after') }}</th>
                    <th class="text-left p-2">{{ __('accounting.fund_transaction.description') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr class="border-b">
                        <td class="p-2">{{ \Illuminate\Support\Carbon::parse($row['transaction_date'])->format('d/m/Y') }}</td>
                        <td class="p-2">{{ $row['transaction_code'] }}</td>
                        <td class="p-2">{{ $row['counterparty_name'] ?? '-' }}</td>
                        <td class="p-2 text-right text-green-600">
                            {{ (int) ($row['type'] ?? 0) === \App\Common\Constants\Organization\FundTransactionType::DEPOSIT->value ? number_format((float) ($row['amount'] ?? 0), 2) : '-' }}
                        </td>
                        <td class="p-2 text-right text-red-600">
                            {{ (int) ($row['type'] ?? 0) === \App\Common\Constants\Organization\FundTransactionType::WITHDRAW->value ? number_format((float) ($row['amount'] ?? 0), 2) : '-' }}
                        </td>
                        <td class="p-2">{{ $row['currency'] ?? 'VND' }}</td>
                        <td class="p-2 text-right">{{ number_format((float) ($row['balance_after'] ?? 0), 2) }}</td>
                        <td class="p-2">{{ $row['description'] ?? '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-2" colspan="8">{{ __('common.error.data_not_found') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
