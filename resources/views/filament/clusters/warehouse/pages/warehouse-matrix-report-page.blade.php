<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('warehouse.reports.matrix_title') }}</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="p-2 text-left">{{ __('warehouse.form.name') }}</th>
                        <th class="p-2 text-right">{{ __('warehouse.reports.quantity') }}</th>
                        <th class="p-2 text-right">{{ __('warehouse.reports.pending') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b">
                            <td class="p-2">{{ $row['warehouse'] }}</td>
                            <td class="p-2 text-right">{{ number_format($row['quantity']) }}</td>
                            <td class="p-2 text-right">{{ number_format($row['pending']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
