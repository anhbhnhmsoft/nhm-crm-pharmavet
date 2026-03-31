<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">{{ __('marketing.cancel_return.title') }}</x-slot>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">{{ __('marketing.cancel_return.table.source') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('marketing.cancel_return.table.reason') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.cancel_return.table.orders') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.cancel_return.table.ratio') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="odd:bg-white even:bg-slate-50">
                                <td class="px-3 py-2">{{ $row['source'] }}</td>
                                <td class="px-3 py-2">{{ $row['reason'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['orders'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['ratio'] }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-8 text-center text-slate-500" colspan="4">{{ __('marketing.common.no_data') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
