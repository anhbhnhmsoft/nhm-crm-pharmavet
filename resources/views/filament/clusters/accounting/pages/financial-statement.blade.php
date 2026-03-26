<x-filament-panels::page>
    @vite(['resources/css/app.css'])
    <div class="space-y-6">
        <form wire:submit="generateReport" class="space-y-4">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit" color="primary">
                    {{ __('accounting.report.generate_button') }}
                </x-filament::button>
            </div>
        </form>

        <div x-data="{ reportData: null }" x-on:financial-report-generated.window="reportData = $event.detail[0]">
            <template x-if="reportData">
                <div class="space-y-8">
                    <x-filament::section>
                        <x-slot name="heading">{{ __('accounting.report.financial_statement.operating_income') }}</x-slot>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="p-5 bg-blue-50/50 dark:bg-blue-900/5 rounded-2xl border border-blue-100 dark:border-blue-800 shadow-sm">
                                <p class="text-[10px] font-black text-blue-600 dark:text-blue-400 uppercase mb-2 tracking-widest">
                                    {{ __('accounting.report.financial_statement.net_revenue') }}
                                </p>
                                <p class="text-2xl font-black text-blue-700 dark:text-blue-300" 
                                   x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.income_statement.net_revenue || 0)">
                                </p>
                            </div>

                            <div class="p-5 bg-amber-50/50 dark:bg-amber-900/5 rounded-2xl border border-amber-100 dark:border-amber-800 shadow-sm">
                                <p class="text-[10px] font-black text-amber-600 dark:text-amber-400 uppercase mb-2 tracking-widest">
                                    {{ __('accounting.report.financial_statement.cogs') }} (Kho)
                                </p>
                                <p class="text-2xl font-black text-amber-700 dark:text-amber-300" 
                                   x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.income_statement.cogs || 0)">
                                </p>
                            </div>

                            <div class="p-5 bg-emerald-50/50 dark:bg-emerald-900/5 rounded-2xl border border-emerald-100 dark:border-emerald-800 shadow-sm">
                                <p class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase mb-2 tracking-widest">
                                    {{ __('accounting.report.financial_statement.gross_profit') }}
                                </p>
                                <p class="text-2xl font-black text-emerald-700 dark:text-emerald-300" 
                                   x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.income_statement.gross_profit || 0)">
                                </p>
                            </div>
                        </div>

                        <div class="mt-8 p-6 bg-gray-50/50 dark:bg-gray-900/5 rounded-3xl border border-gray-100 dark:border-gray-800">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-tighter">
                                    {{ __('accounting.report.financial_statement.operating_expenses') }}
                                </h3>
                                <span class="text-lg font-black text-red-600 dark:text-red-400"
                                      x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.income_statement.operating_expenses.total || 0)">
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" x-data="{ categories: @js($expenseCategories) }">
                                <template x-for="(amount, categoryId) in reportData.income_statement.operating_expenses.details" :key="categoryId">
                                    <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
                                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1" x-text="categories[categoryId] || categoryId"></p>
                                        <p class="text-sm font-black text-gray-900 dark:text-white"
                                           x-text="new Intl.NumberFormat('vi-VN').format(amount) + ' đ'">
                                        </p>
                                        <div class="mt-2 w-full h-1 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div class="bg-red-500 h-1 transition-all duration-1000" 
                                                 :style="'width: ' + ((reportData.income_statement.operating_expenses.total > 0) ? (amount / reportData.income_statement.operating_expenses.total * 100) : 0) + '%'">
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-8 pt-8 border-t border-gray-100 dark:border-gray-800 flex justify-end items-center gap-4">
                                <span class="text-sm font-bold text-gray-500 uppercase">{{ __('accounting.report.financial_statement.net_income') }}</span>
                                <span class="text-3xl font-black transition-colors duration-500"
                                      :class="reportData.income_statement.net_income >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                      x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.income_statement.net_income || 0)">
                                </span>
                            </div>
                        </div>
                    </x-filament::section>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <x-filament::section>
                            <x-slot name="heading">{{ __('accounting.report.financial_statement.balance_sheet') }}</x-slot>
                            <div class="space-y-6">
                                <div class="flex justify-between items-end">
                                    <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest">{{ __('accounting.report.financial_statement.assets') }}</h4>
                                    <span class="text-2xl font-black text-gray-900 dark:text-white border-b-2 border-gray-900" x-text="new Intl.NumberFormat('vi-VN').format(reportData.balance_sheet_frame.total_assets_estimate) + ' đ'"></span>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                                        <span class="text-xs font-bold text-gray-600 dark:text-gray-400">{{ __('accounting.report.financial_statement.cash_and_bank') }}</span>
                                        <span class="text-sm font-black text-gray-900 dark:text-white" x-text="new Intl.NumberFormat('vi-VN').format(reportData.balance_sheet_frame.current_assets.cash_and_bank) + ' đ'"></span>
                                    </div>
                                    <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                                        <span class="text-xs font-bold text-gray-600 dark:text-gray-400">{{ __('accounting.report.financial_statement.accounts_receivable') }}</span>
                                        <span class="text-sm font-black text-gray-900 dark:text-white" x-text="new Intl.NumberFormat('vi-VN').format(reportData.balance_sheet_frame.current_assets.accounts_receivable) + ' đ'"></span>
                                    </div>
                                    <div class="flex items-center justify-between p-3 border-t border-dashed border-gray-200 dark:border-gray-700 pt-4">
                                        <span class="text-xs italic text-gray-400">{{ __('accounting.report.financial_statement.inventory_valuation') }}</span>
                                        <span class="text-sm font-bold text-gray-400" x-text="new Intl.NumberFormat('vi-VN').format(reportData.income_statement.cogs) + ' đ'"></span>
                                    </div>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">{{ __('accounting.report.financial_statement.cash_flow') }}</x-slot>
                            <div class="space-y-6">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="p-4 bg-green-50 dark:bg-green-900/10 rounded-2xl border border-green-100 dark:border-green-800">
                                        <p class="text-[9px] font-black text-green-600 uppercase mb-1">{{ __('accounting.report.financial_statement.cash_flow_in') }}</p>
                                        <p class="text-lg font-black text-green-700 dark:text-green-400" x-text="new Intl.NumberFormat('vi-VN').format(reportData.cash_flow_frame.inflow) + ' đ'"></p>
                                    </div>
                                    <div class="p-4 bg-red-50 dark:bg-red-900/10 rounded-2xl border border-red-100 dark:border-red-800">
                                        <p class="text-[9px] font-black text-red-600 uppercase mb-1">{{ __('accounting.report.financial_statement.cash_flow_out') }}</p>
                                        <p class="text-lg font-black text-red-700 dark:text-red-400" x-text="new Intl.NumberFormat('vi-VN').format(reportData.cash_flow_frame.outflow) + ' đ'"></p>
                                    </div>
                                </div>
                                <div class="p-6 bg-gradient-to-r from-gray-800 to-gray-900 rounded-3xl shadow-xl shadow-gray-200 dark:shadow-none flex items-center justify-between overflow-hidden relative">
                                    <div class="absolute -right-4 -bottom-4 opacity-10">
                                        <x-heroicon-o-currency-dollar class="w-24 h-24 text-white" />
                                    </div>
                                    <div class="relative z-10">
                                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ __('accounting.report.financial_statement.net_cash_flow') }}</p>
                                        <p class="text-3xl font-black text-white" x-text="new Intl.NumberFormat('vi-VN').format(reportData.cash_flow_frame.net_cash_flow) + ' đ'"></p>
                                    </div>
                                    <div class="relative z-10 p-2 rounded-full" :class="reportData.cash_flow_frame.net_cash_flow >=0 ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
                                        <template x-if="reportData.cash_flow_frame.net_cash_flow >= 0">
                                            <x-heroicon-s-arrow-trending-up class="w-8 h-8" />
                                        </template>
                                        <template x-if="reportData.cash_flow_frame.net_cash_flow < 0">
                                            <x-heroicon-s-arrow-trending-down class="w-8 h-8" />
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </x-filament::section>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-filament-panels::page>
