<x-filament-panels::page>
    @vite(['resources/css/app.css'])
    <div class="space-y-6">
        <form wire:submit="generateReport" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end gap-3">
                <x-filament::button type="submit" size="lg">
                    {{ __('accounting.debt_reconciliation.generate') }}
                </x-filament::button>
                
                @if($reportData)
                    <x-filament::button color="gray" icon="heroicon-o-printer" wire:click="export" size="lg">
                        {{ __('accounting.debt_reconciliation.export_pdf') }}
                    </x-filament::button>
                @endif
            </div>
        </form>

        @if($reportData)
            <div class="bg-white dark:bg-gray-900 shadow-xl rounded-2xl border border-gray-200 dark:border-gray-800 p-8 max-w-5xl mx-auto overflow-hidden">
                {{-- Header --}}
                <div class="text-center mb-10 space-y-2">
                    <h1 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-widest">
                        {{ __('accounting.debt_reconciliation.confirm_heading') }}
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">
                        {{ __('accounting.debt_reconciliation.from_date') }}: <span class="font-bold text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($reportData['period']['from'])->format('d/m/Y') }}</span>
                        {{ __('accounting.debt_reconciliation.to_date') }}: <span class="font-bold text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($reportData['period']['to'])->format('d/m/Y') }}</span>
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-10 mb-10">
                    <div class="space-y-1">
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-tighter">{{ __('accounting.debt_reconciliation.partner_type') }}</p>
                        <p class="text-lg font-black text-primary-600 dark:text-primary-400">{{ $reportData['partner']['name'] }}</p>
                        @if(isset($reportData['partner']['phone']))
                            <p class="text-sm text-gray-500">{{ $reportData['partner']['phone'] }}</p>
                        @endif
                        @if(isset($reportData['partner']['address']))
                            <p class="text-sm text-gray-500">{{ $reportData['partner']['address'] }}</p>
                        @endif
                    </div>
                    <div class="flex flex-col items-end justify-center">
                        <div class="text-right p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-800 min-w-[200px]">
                            <p class="text-[10px] font-black text-gray-400 uppercase mb-1">{{ __('accounting.debt_reconciliation.opening_balance') }}</p>
                            <p class="text-2xl font-black text-gray-900 dark:text-white">
                                {{ number_format($reportData['opening_balance'], 0, ',', '.') }} đ
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Table --}}
                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 mb-8 shadow-sm">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800/50">
                                <th class="p-4 text-xs font-black text-gray-500 uppercase">{{ __('accounting.debt_reconciliation.date') }}</th>
                                <th class="p-4 text-xs font-black text-gray-500 uppercase">{{ __('accounting.debt_reconciliation.code') }}</th>
                                <th class="p-4 text-xs font-black text-gray-500 uppercase">{{ __('accounting.debt_reconciliation.description') }}</th>
                                <th class="p-4 text-xs font-black text-gray-500 uppercase text-right">{{ __('accounting.debt_reconciliation.debit') }}</th>
                                <th class="p-4 text-xs font-black text-gray-500 uppercase text-right">{{ __('accounting.debt_reconciliation.credit') }}</th>
                                <th class="p-4 text-xs font-black text-gray-500 uppercase text-right">{{ __('accounting.debt_reconciliation.remaining') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @php $runningBalance = $reportData['opening_balance']; @endphp
                            @foreach($reportData['transactions'] as $trans)
                                @php 
                                    $debit = $trans['debit'] ?? 0;
                                    $credit = $trans['credit'] ?? 0;
                                    $current = $debit - $credit;
                                    $runningBalance += $current;
                                @endphp
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
                                    <td class="p-4 text-sm text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::parse($trans['date'])->format('d/m/Y') }}</td>
                                    <td class="p-4 text-sm font-bold text-gray-900 dark:text-white">#{{ $trans['code'] }}</td>
                                    <td class="p-4 text-sm text-gray-600 dark:text-gray-400">{{ $trans['description'] }}</td>
                                    <td class="p-4 text-sm font-bold text-red-600 dark:text-red-400 text-right">
                                        {{ $debit > 0 ? number_format($debit, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="p-4 text-sm font-bold text-green-600 dark:text-green-400 text-right">
                                        {{ $credit > 0 ? number_format($credit, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="p-4 text-sm font-black text-gray-900 dark:text-white text-right">
                                        {{ number_format($runningBalance, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-100/50 dark:bg-gray-800 font-black">
                                <td colspan="3" class="p-4 text-right uppercase text-xs">{{ __('accounting.debt_reconciliation.total') }}</td>
                                <td class="p-4 text-right text-red-600">
                                    {{ number_format(collect($reportData['transactions'])->sum('debit'), 0, ',', '.') }}
                                </td>
                                <td class="p-4 text-right text-green-600">
                                    {{ number_format(collect($reportData['transactions'])->sum('credit'), 0, ',', '.') }}
                                </td>
                                <td class="p-4 text-right bg-primary-500 rounded-br-xl">
                                    {{ number_format($reportData['closing_balance'], 0, ',', '.') }} đ
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="grid grid-cols-2 gap-20 mt-20 text-center">
                    <div class="space-y-20">
                        <p class="font-black text-gray-900 dark:text-white uppercase tracking-widest">{{ __('accounting.debt_reconciliation.partner_representative') }}</p>
                        <p class="text-gray-400 italic">{{ __('accounting.debt_reconciliation.signature_note') }}</p>
                    </div>
                    <div class="space-y-20">
                        <p class="font-black text-gray-900 dark:text-white uppercase tracking-widest">{{ __('accounting.debt_reconciliation.chief_accountant') }}</p>
                        <p class="text-gray-400 italic">{{ __('accounting.debt_reconciliation.signature_note') }}</p>
                    </div>
                </div>

                <div class="mt-20 pt-8 border-t border-dashed border-gray-200 dark:border-gray-800 text-center text-[10px] text-gray-400 font-medium">
                    {{ __('accounting.debt_reconciliation.print_at') }}: {{ now()->format('d/m/Y H:i:s') }} - {{ __('accounting.debt_reconciliation.app_name') }}
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
