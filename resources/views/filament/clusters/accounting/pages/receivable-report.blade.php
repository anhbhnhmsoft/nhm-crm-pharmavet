<x-filament-panels::page>
    <div class="space-y-6" x-data="{
        reportData: @entangle('receivableData')
    }">
        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-filament::section>
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">{{ __('accounting.report.receivable_logistics') }}</p>
                <p class="text-2xl font-black text-blue-600"
                    x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.summary.total_logistics || 0)">
                </p>
            </x-filament::section>

            <x-filament::section>
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">{{ __('accounting.report.receivable_customers') }}</p>
                <p class="text-2xl font-black text-orange-600"
                    x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.summary.total_customers || 0)">
                </p>
            </x-filament::section>

            <div
                class="p-6 bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-xl shadow-lg border-b-4 border-indigo-900">
                <p class="text-[10px] font-black text-indigo-100 uppercase mb-1">{{ __('accounting.report.receivable_grand_total') }}</p>
                <p class="text-3xl font-black text-white"
                    x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.summary.grand_total || 0)">
                </p>
            </div>
        </div>

        {{-- Tables Section --}}
        <div class="grid grid-cols-1 gap-6">
            {{-- Logistics Debts --}}
            <x-filament::section collapsible>
                <x-slot name="heading">{{ __('accounting.report.receivable_logistics_detail') }}</x-slot>
                <div class="overflow-hidden rounded-lg border border-gray-100 dark:border-gray-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 font-bold uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">{{ __('accounting.report.order_code') }}</th>
                                <th class="px-4 py-3">{{ __('accounting.report.customer') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('accounting.report.amount') }}</th>
                                <th class="px-4 py-3">{{ __('accounting.report.sale_staff') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('accounting.report.debt_age') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="debt in reportData.logistics" :key="debt.order_id">
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-900/10">
                                    <td class="px-4 py-3 font-mono font-bold text-blue-600" x-text="debt.order_code">
                                    </td>
                                    <td class="px-4 py-3" x-text="debt.customer_name"></td>
                                    <td class="px-4 py-3 text-right font-bold"
                                        x-text="new Intl.NumberFormat('vi-VN').format(debt.amount)"></td>
                                    <td class="px-4 py-3 text-gray-500 italic" x-text="debt.sale_name"></td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 rounded-full text-[10px] font-black"
                                            :class="debt.debt_age > 7 ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'"
                                            x-text="debt.debt_age"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            {{-- Customer Debts --}}
            <x-filament::section collapsible>
                <x-slot name="heading">{{ __('accounting.report.receivable_customers_detail') }}</x-slot>
                <div class="overflow-hidden rounded-lg border border-gray-100 dark:border-gray-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 font-bold uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">{{ __('accounting.report.order_code') }}</th>
                                <th class="px-4 py-3">{{ __('accounting.report.customer') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('accounting.report.amount') }}</th>
                                <th class="px-4 py-3">{{ __('accounting.report.sale_staff') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('accounting.report.debt_age') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="debt in reportData.customers" :key="debt.order_id">
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-900/10">
                                    <td class="px-4 py-3 font-mono font-bold text-orange-600" x-text="debt.order_code">
                                    </td>
                                    <td class="px-4 py-3" x-text="debt.customer_name"></td>
                                    <td class="px-4 py-3 text-right font-bold"
                                        x-text="new Intl.NumberFormat('vi-VN').format(debt.amount)"></td>
                                    <td class="px-4 py-3 text-gray-500 italic" x-text="debt.sale_name"></td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 rounded-full text-[10px] font-black"
                                            :class="debt.debt_age > 7 ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700'"
                                            x-text="debt.debt_age"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>