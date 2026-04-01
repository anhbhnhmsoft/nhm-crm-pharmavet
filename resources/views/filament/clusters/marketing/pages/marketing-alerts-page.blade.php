<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">{{ __('marketing.alert_center.title') }}</x-slot>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">ID</th>
                            <th class="px-3 py-2 text-left">{{ __('marketing.alert_center.table.alert_type') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('marketing.alert_center.table.severity') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('marketing.alert_center.table.channel') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('marketing.alert_center.table.campaign') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('marketing.alert_center.table.triggered_at') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('marketing.alert_center.table.resolved_at') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('marketing.alert_center.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($alerts as $alert)
                            <tr class="odd:bg-white even:bg-slate-50">
                                <td class="px-3 py-2">#{{ $alert['id'] }}</td>
                                <td class="px-3 py-2">{{ __('marketing.alert_center.alert_type.' . $alert['alert_type']) }}</td>
                                <td class="px-3 py-2">{{ __('marketing.alert_center.severity.' . $alert['severity']) }}</td>
                                <td class="px-3 py-2">{{ $alert['channel'] }}</td>
                                <td class="px-3 py-2">{{ $alert['campaign'] }}</td>
                                <td class="px-3 py-2">{{ $alert['triggered_at'] }}</td>
                                <td class="px-3 py-2">{{ $alert['resolved_at'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if (empty($alert['resolved_at']))
                                        <x-filament::button size="xs" color="success" wire:click="markResolved({{ $alert['id'] }})">
                                            {{ __('marketing.alert_center.actions.resolve') }}
                                        </x-filament::button>
                                    @else
                                        <x-filament::button size="xs" color="gray" wire:click="reopen({{ $alert['id'] }})">
                                            {{ __('marketing.alert_center.actions.reopen') }}
                                        </x-filament::button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-8 text-center text-slate-500" colspan="8">{{ __('marketing.common.no_data') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
