    <x-filament-panels::page>
    @vite(['resources/css/app.css'])
    <div class="space-y-6">
        <form wire:submit="generateReport" class="space-y-4">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit" color="primary">
                    {{ __('marketing.report.generate_button') }}
                </x-filament::button>
            </div>
        </form>

        <div x-data="{ reportData: null, viewMode: 'full' }" x-on:report-generated.window="reportData = $event.detail[0]; viewMode = $event.detail[1] ?? 'full'">
            <template x-if="reportData && reportData.length > 0">
                <x-filament::section>
                    <x-slot name="heading">{{ __('marketing.report.chart_and_report') }}</x-slot>
                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/50 dark:bg-gray-800/50">
                                    <th
                                        class="p-3 border-b border-gray-200 dark:border-gray-700 font-bold text-gray-900 dark:text-white">
                                        {{ __('marketing.report.no') }}
                                    </th>
                                    <th
                                        class="p-3 border-b border-gray-200 dark:border-gray-700 font-bold text-gray-900 dark:text-white">
                                        {{ __('marketing.report.page_name') }}
                                    </th>
                                    <th
                                        class="p-3 border-b border-gray-200 dark:border-gray-700 font-bold text-gray-900 dark:text-white">
                                        {{ __('marketing.report.mkt_name') }}
                                    </th>
                                    <th
                                        class="p-3 border-b border-gray-200 dark:border-gray-700 font-bold text-center text-blue-600">
                                        {{ __('marketing.report.new_customers') }}
                                    </th>
                                    <th
                                        class="p-3 border-b border-gray-200 dark:border-gray-700 font-bold text-center text-indigo-600">
                                        {{ __('marketing.report.total_orders') }}
                                    </th>
                                    <th
                                        class="p-3 border-b border-gray-200 dark:border-gray-700 font-bold text-center text-gray-900 dark:text-gray-300">
                                        {{ __('marketing.report.total_leads') }}
                                    </th>
                                    <th
                                        class="p-3 border-b border-gray-200 dark:border-gray-700 font-bold text-center text-orange-600">
                                        {{ __('marketing.report.conversion_rate') }}
                                    </th>

                                    @php
                                        use App\Common\Constants\User\UserRole;
                                        use App\Common\Constants\User\UserPosition;

                                        $user = auth()->user();
                                        $canSeeRevenue = in_array($user->role, [UserRole::SUPER_ADMIN->value, UserRole::ADMIN->value])
                                            || $user->position === UserPosition::LEADER->value
                                            || $user->role === UserRole::MARKETING->value;
                                    @endphp
                                    @if($canSeeRevenue)
                                        <th
                                            x-show="viewMode !== 'care'"
                                            class="p-3 border-b border-gray-200 dark:border-gray-700 font-bold text-right text-green-600">
                                            {{ __('marketing.report.revenue') }}
                                        </th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template x-for="(item, index) in reportData" :key="index">
                                    <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/30 transition-colors">
                                        <td class="p-3 text-center text-gray-500" x-text="index + 1"></td>
                                        <td class="p-3 font-semibold text-gray-700 dark:text-gray-300"
                                            x-text="item.page_name"></td>
                                        <td class="p-3 text-gray-600 dark:text-gray-400" x-text="item.mkt_name"></td>
                                        <td class="p-3 text-center font-bold text-blue-600 dark:text-blue-400"
                                            x-text="item.new_customers"></td>
                                        <td class="p-3 text-center font-bold text-indigo-600 dark:text-indigo-400"
                                            x-text="item.total_orders"></td>
                                        <td class="p-3 text-center font-medium text-gray-600 dark:text-gray-400"
                                            x-text="item.total_leads"></td>

                                        <td class="p-3 text-center">
                                            <span
                                                class="px-2 py-1 rounded bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 border border-orange-200 dark:border-orange-800 font-bold text-xs"
                                                x-text="item.conversion_rate + '%'"></span>
                                        </td>

                                        @if($canSeeRevenue)
                                            <td class="p-3 text-right font-black text-green-700 dark:text-green-400"
                                                x-show="viewMode !== 'care'"
                                                x-text="new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(item.revenue)">
                                            </td>
                                        @endif
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            </template>
            <template x-if="reportData && reportData.length === 0">
                <div
                    class="p-8 text-center bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                    <p class="text-gray-500 dark:text-gray-400">{{ __('marketing.report.no_data') }}</p>
                </div>
            </template>
        </div>
    </div>
</x-filament-panels::page>
