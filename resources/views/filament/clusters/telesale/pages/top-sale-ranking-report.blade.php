<x-filament-panels::page>
    @vite(['resources/css/app.css'])
    <div class="space-y-6">
        <form wire:submit="generateReport" class="space-y-4">
            {{ $this->form }}

            <div class="flex justify-end gap-2">
                <x-filament::button type="button" color="gray" wire:click="exportReport">
                    {{ __('telesale.reports.export') }}
                </x-filament::button>
                <x-filament::button type="submit" color="primary">
                    {{ __('telesale.reports.generate') }}
                </x-filament::button>
            </div>
        </form>

        <x-filament::section>
            <x-slot name="heading">{{ __('telesale.reports.top_sale_title') }}</x-slot>

            @if (empty($rows))
                <p class="text-sm text-gray-500">{{ __('telesale.reports.no_data') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse border border-gray-200 dark:border-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="p-3 border border-gray-200 dark:border-gray-700">#</th>
                                <th class="p-3 border border-gray-200 dark:border-gray-700">{{ __('telesale.reports.staff') }}</th>
                                <th class="p-3 border border-gray-200 dark:border-gray-700 text-center">{{ __('telesale.reports.new_customer') }}</th>
                                <th class="p-3 border border-gray-200 dark:border-gray-700 text-center">{{ __('telesale.reports.old_customer') }}</th>
                                <th class="p-3 border border-gray-200 dark:border-gray-700 text-center">{{ __('telesale.reports.total_orders') }}</th>
                                <th class="p-3 border border-gray-200 dark:border-gray-700 text-right">{{ __('telesale.reports.total_revenue') }}</th>
                                <th class="p-3 border border-gray-200 dark:border-gray-700 text-right">{{ __('telesale.reports.adjusted_revenue') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="{{ $row['rank'] <= 3 ? 'bg-yellow-50 dark:bg-yellow-900/10' : '' }}">
                                    <td class="p-3 border border-gray-200 dark:border-gray-700 font-semibold">{{ $row['rank'] }}</td>
                                    <td class="p-3 border border-gray-200 dark:border-gray-700">{{ $row['staff_name'] }}</td>
                                    <td class="p-3 border border-gray-200 dark:border-gray-700 text-center">{{ $row['new_customers'] }}</td>
                                    <td class="p-3 border border-gray-200 dark:border-gray-700 text-center">{{ $row['old_customers'] }}</td>
                                    <td class="p-3 border border-gray-200 dark:border-gray-700 text-center">{{ $row['total_orders'] }}</td>
                                    <td class="p-3 border border-gray-200 dark:border-gray-700 text-right">{{ number_format($row['total_revenue']) }}</td>
                                    <td class="p-3 border border-gray-200 dark:border-gray-700 text-right">{{ number_format($row['adjusted_revenue']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
