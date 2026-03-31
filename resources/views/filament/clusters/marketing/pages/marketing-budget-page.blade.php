<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit.prevent="save" class="space-y-4">
            {{ $this->form }}
            <div class="flex justify-end">
                <x-filament::button type="submit">{{ __('marketing.budget.form.save') }}</x-filament::button>
            </div>
        </form>

        <x-filament::section>
            <x-slot name="heading">{{ __('marketing.budget.title') }}</x-slot>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">{{ __('marketing.budget.table.date') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('marketing.budget.table.channel') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('marketing.budget.table.campaign') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.budget.table.budget') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.budget.table.spend') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.budget.table.fee') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.budget.table.valid_leads') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.budget.table.cost_per_lead') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.budget.table.new_revenue') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.budget.table.old_revenue') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.budget.table.close_rate') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.budget.table.cancel_rate') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.budget.table.aov') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.budget.table.roi') }}</th>
                            <th class="px-3 py-2 text-center">{{ __('marketing.budget.table.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($report['rows'] ?? []) as $row)
                            <tr class="odd:bg-white even:bg-slate-50">
                                <td class="px-3 py-2">{{ $row['date'] }}</td>
                                <td class="px-3 py-2">{{ $row['channel'] }}</td>
                                <td class="px-3 py-2">{{ $row['campaign'] }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['budget_amount']) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['actual_spend']) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['fee_amount']) }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['valid_leads'] }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['cost_per_lead']) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['new_revenue']) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['old_revenue']) }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['close_rate'] }}%</td>
                                <td class="px-3 py-2 text-right">{{ $row['cancel_rate'] }}%</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['aov']) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['roi'], 2) }}</td>
                                <td class="px-3 py-2 text-center">{{ __('marketing.budget.status.' . $row['status']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-8 text-center text-slate-500" colspan="15">{{ __('marketing.common.no_data') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
