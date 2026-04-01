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

        <div x-data="{ reportData: null }" x-on:report-generated.window="reportData = $event.detail[0]">
            <template x-if="reportData && reportData.business">
                <div class="space-y-6">
                    <x-filament::section>
                        <x-slot name="heading">{{ __('accounting.report.revenue_section') }}</x-slot>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            {{-- Gross Revenue --}}
                            <div
                                class="p-4 bg-gray-50/50 dark:bg-gray-900/5 rounded-lg border border-gray-100 dark:border-gray-900/10">
                                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">
                                    {{ __('accounting.report.revenue_gross') }}
                                </p>
                                <p class="text-xl font-bold text-gray-700 dark:text-gray-300"
                                    x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.business.revenue?.gross || 0)">
                                </p>
                            </div>

                            {{-- Deductions (Returns & Discounts) --}}
                            <div
                                class="p-4 bg-red-50/50 dark:bg-red-900/5 rounded-lg border border-red-100 dark:border-red-900/10">
                                <p class="text-[10px] font-black text-red-400 uppercase mb-1">
                                    {{ __('accounting.report.revenue_discounts') }} &
                                    {{ __('accounting.report.revenue_returns') }}
                                </p>
                                <p class="text-xl font-bold text-red-600 dark:text-red-400"
                                    x-text="'- ' + new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format((reportData.business.revenue?.discounts || 0) + (reportData.business.revenue?.returns || 0))">
                                </p>
                                <div class="mt-1 flex gap-2 text-[9px] font-semibold text-gray-400">
                                    <span
                                        x-text="'{{ __('accounting.report.discount_short') }}: ' + new Intl.NumberFormat('vi-VN').format(reportData.business.revenue?.discounts || 0)"></span>
                                    <span>|</span>
                                    <span
                                        x-text="'{{ __('accounting.report.return_short') }}: ' + new Intl.NumberFormat('vi-VN').format(reportData.business.revenue?.returns || 0)"></span>
                                </div>
                            </div>

                            {{-- Net Revenue --}}
                            <div
                                class="p-4 bg-blue-50/50 dark:bg-blue-900/5 rounded-lg border border-blue-100 dark:border-blue-900/10 shadow-sm shadow-blue-50/50">
                                <p
                                    class="text-[10px] font-black text-blue-600 dark:text-blue-400 uppercase mb-1 flex items-center gap-1">
                                    <x-heroicon-s-star class="w-2.5 h-2.5" />
                                    {{ __('accounting.report.revenue_net') }}
                                </p>
                                <p class="text-2xl font-black text-blue-700 dark:text-blue-300"
                                    x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.business.revenue?.net || 0)">
                                </p>
                            </div>

                            {{-- Total Combined --}}
                            <div
                                class="p-6 bg-gradient-to-br from-green-600 to-green-700 rounded-xl shadow-lg shadow-green-200 dark:shadow-none flex flex-col justify-center border-b-4 border-green-800">
                                <p class="text-[10px] font-black text-green-100 uppercase mb-1">
                                    {{ __('accounting.report.revenue_total') }}
                                </p>
                                <p class="text-3xl font-black text-white"
                                    x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.business.revenue?.total || 0)">
                                </p>
                                <div class="mt-1 text-[10px] text-green-100/70 font-bold uppercase italic"
                                    x-text="'{{ __('accounting.report.net_plus_other') }} (' + new Intl.NumberFormat('vi-VN').format(reportData.business.revenue?.other || 0) + ')'">
                                </div>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <x-slot name="heading">
                            <div class="flex items-center gap-2">
                                <span>{{ __('accounting.report.reconciliation_cash_flow') }}</span>
                                <span class="px-2 py-0.5 text-[10px] bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 rounded-full font-bold uppercase tracking-wider animate-pulse">{{ __('accounting.report.new_feature') }}</span>
                            </div>
                        </x-slot>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            {{-- Current Receivable --}}
                            <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl">
                                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">
                                    {{ __('accounting.report.current_cash_flow') }}
                                </p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white"
                                    x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.business.reconciliation?.current_receivable || 0)">
                                </p>
                                <div class="mt-2 flex items-center gap-2">
                                    <template x-if="reportData.business.reconciliation?.growth_rate >= 0">
                                        <div class="flex items-center text-green-600 font-bold text-sm">
                                            <x-heroicon-m-arrow-trending-up class="w-4 h-4 mr-1" />
                                            <span x-text="'+' + reportData.business.reconciliation.growth_rate + '%'"></span>
                                        </div>
                                    </template>
                                    <template x-if="reportData.business.reconciliation?.growth_rate < 0">
                                        <div class="flex items-center text-red-600 font-bold text-sm">
                                            <x-heroicon-m-arrow-trending-down class="w-4 h-4 mr-1" />
                                            <span x-text="reportData.business.reconciliation.growth_rate + '%'"></span>
                                        </div>
                                    </template>
                                    <span class="text-[10px] text-gray-400 font-medium italic">{{ __('accounting.report.vs_previous_period') }}</span>
                                </div>
                            </div>

                            <div class="p-6 bg-gray-50/50 dark:bg-gray-800/20 border border-dashed border-gray-200 dark:border-gray-800 rounded-xl">
                                <p class="text-[10px] font-bold text-gray-500 uppercase mb-1">
                                    {{ __('accounting.report.previous_cash_flow') }}
                                </p>
                                <p class="text-xl font-bold text-gray-600 dark:text-gray-400"
                                    x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.business.reconciliation?.prev_receivable || 0)">
                                </p>
                                <p class="mt-1 text-[10px] text-gray-400"
                                    x-text="'(' + reportData.business.reconciliation?.prev_period?.from + ' - ' + reportData.business.reconciliation?.prev_period?.to + ')'">
                                </p>
                            </div>

                            {{-- Reconciliation Ratio --}}
                            <div class="p-6 bg-indigo-50/30 dark:bg-indigo-900/10 border border-indigo-100 dark:border-indigo-900/20 rounded-xl">
                                <p class="text-[10px] font-bold text-indigo-500 uppercase mb-1">
                                    {{ __('accounting.report.cash_flow_revenue_ratio') }}
                                </p>
                                <p class="text-3xl font-black text-indigo-700 dark:text-indigo-400"
                                    x-text="((reportData.business.revenue?.net > 0) ? (reportData.business.reconciliation?.current_receivable / reportData.business.revenue.net * 100).toFixed(1) : 0) + '%'">
                                </p>
                                <div class="mt-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-indigo-500 h-1.5 rounded-full transition-all duration-1000"
                                        :style="'width: ' + ((reportData.business.revenue?.net > 0) ? Math.min((reportData.business.reconciliation?.current_receivable / reportData.business.revenue.net * 100), 100) : 0) + '%'">
                                        </div>
                                </div>
                            </div>
                        </div>
                    </x-filament::section>


                    <!-- New Sale Revenue Report Section -->
                    <template x-if="reportData.sales">
                        <x-filament::section>
                            <x-slot name="heading">{{ __('accounting.report.sales_section') }}</x-slot>
                            <div class="overflow-x-auto">
                                <table
                                    class="w-full text-left border-collapse border border-gray-200 dark:border-gray-700">
                                    <thead>
                                        <tr class="bg-gray-50 dark:bg-gray-800">
                                            <th class="p-3 border border-gray-200 dark:border-gray-700 font-bold">
                                                {{ __('accounting.report.staff_name') }}
                                            </th>
                                            <th
                                                class="p-3 border border-gray-200 dark:border-gray-700 font-bold text-center">
                                                {{ __('accounting.report.total_orders') }}
                                            </th>
                                            <th
                                                class="p-3 border border-gray-200 dark:border-gray-700 font-bold text-center text-green-600">
                                                {{ __('accounting.report.success') }}
                                            </th>
                                            <th
                                                class="p-3 border border-gray-200 dark:border-gray-700 font-bold text-center text-red-600">
                                                {{ __('accounting.report.returned') }}
                                            </th>
                                            <th
                                                class="p-3 border border-gray-200 dark:border-gray-700 font-bold text-center text-blue-600">
                                                {{ __('accounting.report.delivering') }}
                                            </th>
                                            <th
                                                class="p-3 border border-gray-200 dark:border-gray-700 font-bold text-center text-gray-500">
                                                {{ __('accounting.report.other') }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="item in reportData.sales.breakdown" :key="item.staff_name">
                                            <tr>
                                                <td class="p-3 border border-gray-200 dark:border-gray-700"
                                                    x-text="item.staff_name"></td>
                                                <td class="p-3 border border-gray-200 dark:border-gray-700 text-center font-bold"
                                                    x-text="item.total_count"></td>
                                                <td class="p-3 border border-gray-200 dark:border-gray-700">
                                                    <div class="text-center text-green-600 font-bold"
                                                        x-text="item.success.count"></div>
                                                    <div class="text-xs text-center text-gray-500"
                                                        x-text="new Intl.NumberFormat('vi-VN').format(item.success.cod) + ' {{ __('accounting.report.cod') }}'">
                                                    </div>
                                                    <div class="text-xs text-center font-semibold text-green-500"
                                                        x-text="item.success.rate + '%'"></div>
                                                </td>
                                                <td class="p-3 border border-gray-200 dark:border-gray-700">
                                                    <div class="text-center text-red-600 font-bold"
                                                        x-text="item.returned.count"></div>
                                                    <div class="text-xs text-center text-gray-500"
                                                        x-text="new Intl.NumberFormat('vi-VN').format(item.returned.cod) + ' {{ __('accounting.report.cod') }}'">
                                                    </div>
                                                    <div class="text-xs text-center font-semibold text-red-500"
                                                        x-text="item.returned.rate + '%'"></div>
                                                </td>
                                                <td class="p-3 border border-gray-200 dark:border-gray-700">
                                                    <div class="text-center text-blue-600 font-bold"
                                                        x-text="item.delivering.count"></div>
                                                    <div class="text-xs text-center text-gray-500"
                                                        x-text="new Intl.NumberFormat('vi-VN').format(item.delivering.cod) + ' {{ __('accounting.report.cod') }}'">
                                                    </div>
                                                    <div class="text-xs text-center font-semibold text-blue-500"
                                                        x-text="item.delivering.rate + '%'"></div>
                                                </td>
                                                <td class="p-3 border border-gray-200 dark:border-gray-700 text-center font-medium text-gray-500"
                                                    x-text="item.other_count"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-gray-100 dark:bg-gray-900 font-bold">
                                            <td class="p-3 border border-gray-200 dark:border-gray-700 text-right">
                                                {{ __('accounting.report.summary') }}
                                            </td>
                                            <td class="p-3 border border-gray-200 dark:border-gray-700 text-center"
                                                x-text="reportData.sales.summary.total_orders"></td>
                                            <td class="p-3 border border-gray-200 dark:border-gray-700">
                                                <div class="text-center text-green-600"
                                                    x-text="reportData.sales.summary.success.count"></div>
                                                <div class="text-xs text-center text-gray-500"
                                                    x-text="new Intl.NumberFormat('vi-VN').format(reportData.sales.summary.success.cod) + ' {{ __('accounting.report.cod') }}'">
                                                </div>
                                                <div class="text-xs text-center"
                                                    x-text="reportData.sales.summary.success.rate + '%'"></div>
                                            </td>
                                            <td class="p-3 border border-gray-200 dark:border-gray-700">
                                                <div class="text-center text-red-600"
                                                    x-text="reportData.sales.summary.returned.count"></div>
                                                <div class="text-xs text-center text-gray-500"
                                                    x-text="new Intl.NumberFormat('vi-VN').format(reportData.sales.summary.returned.cod) + ' {{ __('accounting.report.cod') }}'">
                                                </div>
                                                <div class="text-xs text-center"
                                                    x-text="reportData.sales.summary.returned.rate + '%'"></div>
                                            </td>
                                            <td class="p-3 border border-gray-200 dark:border-gray-700">
                                                <div class="text-center text-blue-600"
                                                    x-text="reportData.sales.summary.delivering.count"></div>
                                                <div class="text-xs text-center text-gray-500"
                                                    x-text="new Intl.NumberFormat('vi-VN').format(reportData.sales.summary.delivering.cod) + ' {{ __('accounting.report.cod') }}'">
                                                </div>
                                                <div class="text-xs text-center"
                                                    x-text="reportData.sales.summary.delivering.rate + '%'"></div>
                                            </td>
                                            <td class="p-3 border border-gray-200 dark:border-gray-700"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </x-filament::section>
                    </template>

                    <template x-if="reportData.marketing">
                        <x-filament::section>
                            <x-slot name="heading">{{ __('accounting.report.marketing_section') }}</x-slot>
                            <div
                                class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50/50 dark:bg-gray-800/50">
                                            <th
                                                class="p-3 border-b border-gray-200 dark:border-gray-700 font-bold text-gray-900 dark:text-white">
                                                {{ __('accounting.report.source_name') }}
                                            </th>
                                            <th
                                                class="p-3 border-b border-gray-200 dark:border-gray-700 font-bold text-center text-gray-900 dark:text-white">
                                                {{ __('accounting.report.total_orders') }}
                                            </th>
                                            <th
                                                class="p-3 border-b border-gray-200 dark:border-gray-700 font-bold text-center text-green-600">
                                                {{ __('accounting.report.success') }}
                                            </th>
                                            <th
                                                class="p-3 border-b border-gray-200 dark:border-gray-700 font-bold text-center text-red-600">
                                                {{ __('accounting.report.returned') }}
                                            </th>
                                            <th
                                                class="p-3 border-b border-gray-200 dark:border-gray-700 font-bold text-center text-blue-600">
                                                {{ __('accounting.report.delivering') }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <template x-for="item in reportData.marketing" :key="item.source">
                                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/30 transition-colors">
                                                <td class="p-3 font-semibold text-gray-700 dark:text-gray-300"
                                                    x-text="item.source"></td>
                                                <td class="p-3 text-center font-black text-gray-900 dark:text-white"
                                                    x-text="item.total_count"></td>
                                                <td class="p-3">
                                                    <div class="text-center text-green-700 dark:text-green-400 font-bold text-lg"
                                                        x-text="item.success.count"></div>
                                                    <div class="text-[10px] text-center text-gray-400 font-bold uppercase tracking-tighter"
                                                        x-text="new Intl.NumberFormat('vi-VN').format(item.success.cod) + ' {{ __('accounting.report.cod') }}'">
                                                    </div>
                                                    <div class="flex justify-center mt-1">
                                                        <span
                                                            class="px-2 py-0.5 rounded-full text-[10px] font-black bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 border border-green-200 dark:border-green-800"
                                                            x-text="item.success.rate + '%'"></span>
                                                    </div>
                                                </td>
                                                <td class="p-3">
                                                    <div class="text-center text-red-700 dark:text-red-400 font-bold text-lg"
                                                        x-text="item.returned.count"></div>
                                                    <div class="text-[10px] text-center text-gray-400 font-bold uppercase tracking-tighter"
                                                        x-text="new Intl.NumberFormat('vi-VN').format(item.returned.cod) + ' {{ __('accounting.report.cod') }}'">
                                                    </div>
                                                    <div class="flex justify-center mt-1">
                                                        <span
                                                            class="px-2 py-0.5 rounded-full text-[10px] font-black bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 border border-red-200 dark:border-red-800"
                                                            x-text="item.returned.rate + '%'"></span>
                                                    </div>
                                                </td>
                                                <td class="p-3">
                                                    <div class="text-center text-blue-700 dark:text-blue-400 font-bold text-lg"
                                                        x-text="item.delivering.count"></div>
                                                    <div class="text-[10px] text-center text-gray-400 font-bold uppercase tracking-tighter"
                                                        x-text="new Intl.NumberFormat('vi-VN').format(item.delivering.cod) + ' {{ __('accounting.report.cod') }}'">
                                                    </div>
                                                    <div class="flex justify-center mt-1">
                                                        <span
                                                            class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border border-blue-200 dark:border-blue-800"
                                                            x-text="item.delivering.rate + '%'"></span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </x-filament::section>
                    </template>

                    <template x-if="reportData.customers">
                        <x-filament::section>
                            <x-slot name="heading">{{ __('accounting.report.customer_section') }}</x-slot>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                                <template x-for="type in reportData.customers" :key="type.type_id">
                                    <div class="relative group p-6 rounded-2xl border transition-all duration-300 hover:shadow-xl"
                                        :class="{
                                            'bg-blue-50/30 border-blue-100': type.type_id == 1,
                                            'bg-amber-50/30 border-amber-100': type.type_id == 2,
                                            'bg-purple-50/30 border-purple-100': type.type_id == 3
                                        }">
                                        <div class="flex flex-col items-center gap-2">
                                            <span class="text-xs font-bold uppercase tracking-widest text-gray-500"
                                                x-text="type.type_id == 1 ? '{{ __('accounting.report.customer_type_new') }}' : (type.type_id == 2 ? '{{ __('accounting.report.customer_type_duplicate') }}' : '{{ __('accounting.report.customer_type_old') }}')">
                                            </span>
                                            <div class="text-3xl font-black text-gray-900 dark:text-white"
                                                x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(type.revenue)">
                                            </div>
                                            <div
                                                class="px-3 py-1 bg-white/80 dark:bg-gray-900/50 rounded-full text-xs font-bold shadow-sm border border-gray-100 dark:border-gray-800">
                                                <span x-text="type.count"></span>
                                                {{ __('accounting.report.total_orders') }}
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </x-filament::section>
                    </template>

                    <x-filament::section>
                        <x-slot name="heading">{{ __('accounting.report.expense_section') }}</x-slot>
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8"
                            x-data="{ categories: @js($expenseCategories) }">
                            <!-- Summary -->
                            <div
                                class="lg:col-span-4 flex flex-col justify-center items-center p-6 bg-red-50 dark:bg-red-900/10 rounded-xl border border-red-100 dark:border-red-900/20">
                                <p
                                    class="text-sm font-medium text-red-600 dark:text-red-400 mb-2 uppercase tracking-tight">
                                    {{ __('accounting.report.expense_total') }}
                                </p>
                                <p class="text-4xl font-extrabold text-red-600 dark:text-red-400"
                                    x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.business.expense?.total || 0)">
                                </p>
                            </div>

                            <!-- Breakdown -->
                            <div class="lg:col-span-8 space-y-4">
                                <p class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                                    {{ __('accounting.report.expense_breakdown') }}
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-4">
                                    <template x-for="(amount, categoryId) in reportData.business.expense?.by_category"
                                        :key="categoryId">
                                        <div class="group">
                                            <div class="flex justify-between items-center mb-1.5">
                                                <span
                                                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 transition-colors group-hover:text-red-500"
                                                    x-text="categories[categoryId] || '{{ __('accounting.report.unknown') }}'"></span>
                                                <span class="text-sm font-bold text-gray-900 dark:text-white"
                                                    x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)"></span>
                                            </div>
                                            <div
                                                class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2 overflow-hidden shadow-inner">
                                                <div class="bg-gradient-to-r from-red-400 to-red-600 h-2 rounded-full transition-all duration-700 ease-out"
                                                    :style="'width: ' + ((reportData.business.expense?.total > 0) ? (amount / reportData.business.expense.total * 100) : 0) + '%'">
                                                </div>
                                            </div>
                                            <div class="flex justify-end mt-1">
                                                <span class="text-[10px] font-bold text-gray-400 uppercase"
                                                    x-text="((reportData.business.expense?.total > 0) ? (amount / reportData.business.expense.total * 100).toFixed(1) : 0) + '%'"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <x-slot name="heading">{{ __('accounting.report.profit_section') }}</x-slot>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="p-8 rounded-2xl flex flex-col items-center justify-center border-2 transition-all duration-500"
                                :class="(reportData.business.profit?.amount || 0) >= 0 ? 'bg-green-50 border-green-200 dark:bg-green-900/10 dark:border-green-900/20' : 'bg-red-50 border-red-200 dark:bg-red-900/10 dark:border-red-900/20'">
                                <p class="text-sm font-bold uppercase tracking-widest mb-3"
                                    :class="(reportData.business.profit?.amount || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ __('accounting.report.profit_amount') }}
                                </p>
                                <p class="text-5xl font-black tracking-tighter"
                                    :class="(reportData.business.profit?.amount || 0) >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'"
                                    x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.business.profit?.amount || 0)">
                                </p>
                            </div>
                            <div class="p-8 rounded-2xl flex flex-col items-center justify-center border-2 transition-all duration-500"
                                :class="(reportData.business.profit?.rate || 0) >= 0 ? 'bg-green-50 border-green-200 dark:bg-green-900/10 dark:border-green-900/20' : 'bg-red-50 border-red-200 dark:bg-red-900/10 dark:border-red-900/20'">
                                <p class="text-sm font-bold uppercase tracking-widest mb-3"
                                    :class="(reportData.business.profit?.rate || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ __('accounting.report.profit_rate') }}
                                </p>
                                <div class="relative flex items-center justify-center">
                                    <p class="text-5xl font-black tracking-tighter z-10"
                                        :class="(reportData.business.profit?.rate || 0) >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'"
                                        x-text="(reportData.business.profit?.rate || 0).toFixed(2) + '%'"></p>
                                </div>
                            </div>
                        </div>
                    </x-filament::section>
                </div>
            </template>
        </div>
    </div>
</x-filament-panels::page>