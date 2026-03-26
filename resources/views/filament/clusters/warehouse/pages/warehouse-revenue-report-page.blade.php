<x-filament-panels::page>
    <form wire:submit="generateReport" class="space-y-4">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('telesale.reports.generate') }}</x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('warehouse.reports.revenue_title') }}</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="p-2 text-left">{{ __('warehouse.form.name') }}</th>
                        <th class="p-2 text-right">{{ __('warehouse.reports.total_orders') }}</th>
                        <th class="p-2 text-right">{{ __('warehouse.reports.total_revenue') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b">
                            <td class="p-2">{{ $row['warehouse_name'] }}</td>
                            <td class="p-2 text-right">{{ number_format($row['total_orders']) }}</td>
                            <td class="p-2 text-right">{{ number_format($row['total_revenue']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
