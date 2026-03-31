<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-5">
            @foreach (($dashboard['cards'] ?? []) as $key => $card)
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase text-slate-500">{{ __('marketing.kpi.cards.' . $key) }}</p>
                    <p class="mt-2 text-xl font-bold text-slate-800">{{ number_format((float) $card['value'], 2) }}</p>
                    <p class="mt-1 text-xs {{ $card['trend'] === 'up' ? 'text-emerald-600' : ($card['trend'] === 'down' ? 'text-red-600' : 'text-slate-500') }}">
                        {{ $card['variance'] }}% ({{ __('marketing.kpi.variance.' . $card['trend']) }})
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
