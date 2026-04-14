<x-filament-panels::page>
    <form wire:submit="generateReport" class="space-y-4">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('telesale.reports.generate') }}</x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('warehouse.reports.stock_title') }}</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>{{ __('warehouse.reports.opening') }}: {{ number_format($stats['opening'] ?? 0) }}</div>
            <div>{{ __('warehouse.reports.imports') }}: {{ number_format($stats['imports'] ?? 0) }}</div>
            <div>{{ __('warehouse.reports.exports') }}: {{ number_format($stats['exports'] ?? 0) }}</div>
            <div>{{ __('warehouse.reports.closing') }}: {{ number_format($stats['closing'] ?? 0) }}</div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
