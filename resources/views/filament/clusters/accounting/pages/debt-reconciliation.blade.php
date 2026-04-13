<x-filament-panels::page>
    @vite(['resources/css/app.css'])
    <div class="space-y-6">
        <form wire:submit="generateReport" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end gap-3">
                <x-filament::button type="submit" size="lg" icon="heroicon-o-magnifying-glass">
                    {{ __('accounting.debt_reconciliation.generate') }}
                </x-filament::button>
            </div>
        </form>

        @if(count($summaryData) > 0)
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <span>{{ __('accounting.debt_reconciliation.summary_title') }}</span>
                        <span class="px-2 py-0.5 text-[10px] bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300 rounded-full font-bold uppercase">
                            {{ __('accounting.debt_reconciliation.partners_count', ['count' => count($summaryData)]) }}
                        </span>
                    </div>
                </x-slot>
                
                <div class="overflow-x-auto overflow-y-auto max-h-[600px] rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm custom-scrollbar">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="p-4 font-bold text-gray-500 uppercase text-[10px]">{{ __('accounting.debt_reconciliation.partner_type') }}</th>
                                <th class="p-4 font-bold text-gray-500 uppercase text-[10px] text-right">{{ __('accounting.debt_reconciliation.opening_balance') }}</th>
                                <th class="p-4 font-bold text-gray-500 uppercase text-[10px] text-right text-red-600">{{ __('accounting.debt_reconciliation.debit') }}</th>
                                <th class="p-4 font-bold text-gray-500 uppercase text-[10px] text-right text-green-600">{{ __('accounting.debt_reconciliation.credit') }}</th>
                                <th class="p-4 font-bold text-gray-500 uppercase text-[10px] text-right">{{ __('accounting.debt_reconciliation.closing_balance') }}</th>
                                <th class="p-4 font-bold text-gray-500 uppercase text-[10px] text-center">{{ __('accounting.debt_reconciliation.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                            @foreach($summaryData as $item)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
                                    <td class="p-4">
                                        <div class="font-bold text-gray-900 dark:text-white">{{ $item['partner_name'] }}</div>
                                        <div class="text-[10px] text-gray-400 font-medium italic">
                                            {{ $item['partner_type'] === \App\Common\Constants\Accounting\DebtPartnerType::LOGISTICS->value ? __('accounting.debt_reconciliation.partner_logistics') : __('accounting.debt_reconciliation.partner_customer') }}
                                        </div>
                                    </td>
                                    <td class="p-4 text-right font-medium text-gray-600 dark:text-gray-400">
                                        {{ number_format($item['opening_balance'], 0, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-right font-bold text-red-500">
                                        {{ $item['total_debit'] > 0 ? number_format($item['total_debit'], 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="p-4 text-right font-bold text-green-500">
                                        {{ $item['total_credit'] > 0 ? number_format($item['total_credit'], 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="p-4 text-right font-black text-gray-900 dark:text-white bg-gray-50/30 dark:bg-gray-800/10">
                                        {{ number_format($item['closing_balance'], 0, ',', '.') }}
                                    </td>
                                    <td class="p-4">
                                        <div class="flex justify-center gap-2">
                                            <x-filament::button 
                                                wire:click="viewDetail({{ $item['partner_type'] }}, {{ $item['partner_id'] }})"
                                                size="xs" 
                                                color="primary" 
                                                outlined
                                                icon="heroicon-o-eye"
                                            >
                                                {{ __('accounting.debt_reconciliation.view') }}
                                            </x-filament::button>
                                            
                                            <x-filament::button 
                                                wire:click="exportPartner({{ $item['partner_type'] }}, {{ $item['partner_id'] }})"
                                                size="xs" 
                                                color="gray" 
                                                outlined
                                                icon="heroicon-o-printer"
                                            >
                                                {{ __('accounting.debt_reconciliation.export') }}
                                            </x-filament::button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 text-[10px] text-gray-400 italic">
                    {{ __('accounting.debt_reconciliation.scroll_note') }}
                </div>
            </x-filament::section>
        @endif

        {{-- Detail Report Modal --}}
        <x-filament::modal id="detail-report-modal" width="6xl" :display-header="false">
            @if($reportData)
                <div class="bg-white dark:bg-gray-950 p-6 sm:p-10 max-w-full mx-auto relative overflow-hidden">
                    {{-- Decorative backgrounds --}}
                    <div class="absolute top-0 right-0 -mt-20 -mr-20 w-64 h-64 bg-primary-50 dark:bg-primary-900/10 rounded-full blur-3xl opacity-50"></div>
                    <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-64 h-64 bg-gray-50 dark:bg-gray-800/10 rounded-full blur-3xl opacity-50"></div>

                    <div class="relative z-10">
                        {{-- Header Section --}}
                        <div class="flex flex-col sm:flex-row justify-between items-center sm:items-start gap-6 border-b border-gray-100 dark:border-gray-800 pb-10 mb-10">
                            <div class="text-center sm:text-left space-y-3">
                                <div class="inline-flex items-center px-3 py-1 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full text-[10px] font-bold uppercase tracking-wider mb-1">
                                    {{ __('accounting.debt_reconciliation.title') }}
                                </div>
                                <h1 class="text-3xl font-black text-gray-900 dark:text-white uppercase tracking-tight leading-tight">
                                    {{ __('accounting.debt_reconciliation.confirm_heading') }}
                                </h1>
                                <p class="text-sm font-medium text-gray-400 flex items-center gap-2 justify-center sm:justify-start">
                                    <x-heroicon-o-calendar class="w-4 h-4" />
                                    <span>{{ __('accounting.debt_reconciliation.from_date') }}: <span class="text-gray-900 dark:text-gray-200">{{ \Carbon\Carbon::parse($reportData['period']['from'])->format('d/m/Y') }}</span></span>
                                    <span class="text-gray-300 dark:text-gray-700 mx-1">|</span>
                                    <span>{{ __('accounting.debt_reconciliation.to_date') }}: <span class="text-gray-900 dark:text-gray-200">{{ \Carbon\Carbon::parse($reportData['period']['to'])->format('d/m/Y') }}</span></span>
                                </p>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <x-filament::button 
                                    color="gray" 
                                    icon="heroicon-o-printer" 
                                    wire:click="export" 
                                    outlined 
                                    size="sm"
                                    class="rounded-xl shadow-sm font-bold"
                                >
                                    {{ __('accounting.debt_reconciliation.export_pdf') }}
                                </x-filament::button>
                                <x-filament::button 
                                    color="danger" 
                                    icon="heroicon-o-x-mark" 
                                    x-on:click="close" 
                                    outlined 
                                    size="sm"
                                    class="rounded-xl shadow-sm font-bold"
                                >
                                    {{ __('accounting.debt_reconciliation.close_modal') }}
                                </x-filament::button>
                            </div>
                        </div>

                        {{-- Partner Info Card --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                            <div class="md:col-span-2 bg-gray-50 dark:bg-white/5 p-6 rounded-2xl border border-gray-100 dark:border-white/10 space-y-4">
                                <div class="flex items-center gap-3 text-gray-500 text-[10px] font-black uppercase tracking-widest">
                                    <div class="p-1 px-2 bg-gray-200 dark:bg-gray-700 rounded text-gray-700 dark:text-gray-300">
                                        {{ (int)$data['partner_type'] === \App\Common\Constants\Accounting\DebtPartnerType::LOGISTICS->value ? __('accounting.debt_reconciliation.partner_logistics') : __('accounting.debt_reconciliation.partner_customer') }}
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white leading-none">
                                        {{ $reportData['partner']['name'] ?? __('Không rõ tên') }}
                                    </p>
                                    <div class="flex flex-wrap gap-4 pt-1">
                                        @if(isset($reportData['partner']['phone']) && $reportData['partner']['phone'])
                                            <span class="flex items-center gap-2 text-xs text-gray-500 font-bold">
                                                <x-heroicon-m-phone class="w-4 h-4 text-primary-500" />
                                                {{ $reportData['partner']['phone'] }}
                                            </span>
                                        @endif
                                        @if(isset($reportData['partner']['address']) && $reportData['partner']['address'])
                                            <span class="flex items-center gap-2 text-xs text-gray-500 font-bold">
                                                <x-heroicon-m-map-pin class="w-4 h-4 text-primary-500" />
                                                {{ $reportData['partner']['address'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 dark:bg-white/5 p-6 rounded-2xl border border-gray-100 dark:border-white/10 flex flex-col justify-center relative overflow-hidden group shadow-sm">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 relative z-10">{{ __('accounting.debt_reconciliation.opening_balance') }}</p>
                                <div class="flex items-baseline gap-1 relative z-10">
                                    <span class="text-xl font-bold text-gray-900 dark:text-white leading-none">
                                        {{ number_format($reportData['opening_balance'] ?? 0, 0, ',', '.') }}
                                    </span>
                                    <span class="text-gray-400 font-bold text-sm">đ</span>
                                </div>
                                <div class="mt-2 text-[9px] font-semibold">
                                    @if(($reportData['opening_balance'] ?? 0) == 0)
                                        <span class="text-gray-400 uppercase tracking-tighter">{{ __('Không có dư nợ') }}</span>
                                    @else
                                        <span class="{{ $reportData['opening_balance'] > 0 ? 'text-red-500' : 'text-green-500' }} uppercase tracking-tighter">
                                            {{ $reportData['opening_balance'] > 0 ? __('Dư Nợ') : __('Dư Có') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Transactions Table --}}
                        <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm mb-10 bg-white dark:bg-gray-900">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead>
                                    <tr class="bg-gray-50/80 dark:bg-gray-800/80 backdrop-blur-md border-b border-gray-200 dark:border-gray-800">
                                        <th class="p-4 font-black text-gray-500 uppercase text-[10px]">{{ __('accounting.debt_reconciliation.date') }}</th>
                                        <th class="p-4 font-black text-gray-500 uppercase text-[10px]">{{ __('accounting.debt_reconciliation.code') }}</th>
                                        <th class="p-4 font-black text-gray-500 uppercase text-[10px]">{{ __('accounting.debt_reconciliation.description') }}</th>
                                        <th class="p-4 font-black text-gray-500 uppercase text-[10px] text-right text-red-600 dark:text-red-400">{{ __('accounting.debt_reconciliation.debit') }}</th>
                                        <th class="p-4 font-black text-gray-500 uppercase text-[10px] text-right text-green-600 dark:text-green-400">{{ __('accounting.debt_reconciliation.credit') }}</th>
                                        <th class="p-4 font-black text-gray-500 uppercase text-[10px] text-right">{{ __('accounting.debt_reconciliation.remaining') }}</th>
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
                                        <tr class="hover:bg-primary-50/30 dark:hover:bg-primary-900/5 transition-all duration-200 group">
                                            <td class="p-4 text-xs font-medium text-gray-500 dark:text-gray-400 group-hover:text-primary-600">
                                                {{ \Carbon\Carbon::parse($trans['date'])->format('d/m/Y') }}
                                            </td>
                                            <td class="p-4 font-bold text-gray-900 dark:text-white">#{{ $trans['code'] }}</td>
                                            <td class="p-4 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ $trans['description'] }}</td>
                                            <td class="p-4 font-bold text-red-600 dark:text-red-400 text-right tabular-nums">
                                                {{ $debit > 0 ? number_format($debit, 0, ',', '.') : '-' }}
                                            </td>
                                            <td class="p-4 font-bold text-green-600 dark:text-green-400 text-right tabular-nums">
                                                {{ $credit > 0 ? number_format($credit, 0, ',', '.') : '-' }}
                                            </td>
                                            <td class="p-4 font-black text-gray-900 dark:text-white text-right tabular-nums bg-gray-50/30 dark:bg-gray-800/20">
                                                {{ number_format($runningBalance, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-50 dark:bg-gray-800/80 border-t border-gray-200 dark:border-gray-700 font-bold">
                                        <td colspan="3" class="p-5 text-right uppercase text-[10px] tracking-widest text-gray-500 font-black">
                                            {{ __('accounting.debt_reconciliation.total') }}
                                        </td>
                                        <td class="p-5 text-right text-red-600 tabular-nums">
                                            {{ number_format(collect($reportData['transactions'])->sum('debit'), 0, ',', '.') }}
                                        </td>
                                        <td class="p-5 text-right text-green-600 tabular-nums">
                                            {{ number_format(collect($reportData['transactions'])->sum('credit'), 0, ',', '.') }}
                                        </td>
                                        <td class="p-5 text-right bg-gray-900 dark:bg-primary-600 text-white tabular-nums text-lg font-black rounded-br-2xl">
                                            {{ number_format($reportData['closing_balance'] ?? 0, 0, ',', '.') }} đ
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- Signatures Section --}}
                        <div class="grid grid-cols-2 gap-20 mt-20 text-center relative">
                            <div class="space-y-24">
                                <div class="space-y-1">
                                    <p class="font-black text-[11px] text-gray-900 dark:text-white uppercase tracking-[0.2em]">
                                        {{ __('accounting.debt_reconciliation.partner_representative') }}
                                    </p>
                                    <p class="text-[9px] text-gray-400 font-medium italic lowercase tracking-tight">
                                        ({{ __('accounting.debt_reconciliation.signature_note') }})
                                    </p>
                                </div>
                                <div class="w-16 h-px bg-gray-100 dark:bg-gray-800 mx-auto"></div>
                            </div>
                            <div class="space-y-24">
                                <div class="space-y-1">
                                    <p class="font-black text-[11px] text-gray-900 dark:text-white uppercase tracking-[0.2em]">
                                        {{ __('accounting.debt_reconciliation.chief_accountant') }}
                                    </p>
                                    <p class="text-[9px] text-gray-400 font-medium italic lowercase tracking-tight">
                                        ({{ __('accounting.debt_reconciliation.signature_note') }})
                                    </p>
                                </div>
                                <div class="w-16 h-px bg-gray-100 dark:bg-gray-800 mx-auto"></div>
                            </div>
                        </div>

                        <div class="mt-24 pt-8 border-t border-dashed border-gray-100 dark:border-gray-800 text-center text-[9px] text-gray-400 font-bold uppercase tracking-widest flex items-center justify-center gap-4">
                            <span class="flex items-center gap-1.5"><x-heroicon-m-clock class="w-3 h-3" /> {{ now()->format('d/m/Y H:i:s') }}</span>
                            <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                            <span>{{ __('accounting.debt_reconciliation.app_name') }}</span>
                        </div>
                    </div>
                </div>
            @endif
        </x-filament::modal>
    </div>
</x-filament-panels::page>
