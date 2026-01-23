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
            <template x-if="reportData">
                <div class="space-y-6">
                    <x-filament::section>
                        <x-slot name="heading">{{ __('accounting.report.revenue_section') }}</x-slot>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('accounting.report.revenue_from_orders') }}</p>
                                <p class="text-2xl font-bold" x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.revenue?.from_orders || 0)"></p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('accounting.report.revenue_other') }}</p>
                                <p class="text-2xl font-bold" x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.revenue?.other || 0)"></p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('accounting.report.revenue_total') }}</p>
                                <p class="text-2xl font-bold text-green-600 dark:text-green-400" x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.revenue?.total || 0)"></p>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <x-slot name="heading">{{ __('accounting.report.expense_section') }}</x-slot>
                        <div class="space-y-2">
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('accounting.report.expense_total') }}</p>
                            <p class="text-2xl font-bold text-red-600 dark:text-red-400" x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.expense?.total || 0)"></p>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <x-slot name="heading">{{ __('accounting.report.profit_section') }}</x-slot>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('accounting.report.profit_amount') }}</p>
                                <p class="text-2xl font-bold" :class="(reportData.profit?.amount || 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(reportData.profit?.amount || 0)"></p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('accounting.report.profit_rate') }}</p>
                                <p class="text-2xl font-bold" :class="(reportData.profit?.rate || 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" x-text="(reportData.profit?.rate || 0).toFixed(2) + '%'"></p>
                            </div>
                        </div>
                    </x-filament::section>
                </div>
            </template>
        </div>
    </div>
</x-filament-panels::page>

