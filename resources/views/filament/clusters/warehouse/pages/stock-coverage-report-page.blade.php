<x-filament-panels::page>
    <form wire:submit="generateReport" class="space-y-4">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('telesale.reports.generate') }}</x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('warehouse.reports.coverage_title') }}</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>{{ __('warehouse.reports.available_stock') }}: {{ number_format($stats['available_stock'] ?? 0) }}</div>
            <div>{{ __('warehouse.reports.avg_daily_out') }}: {{ number_format($stats['avg_daily_out'] ?? 0, 2) }}</div>
            <div>{{ __('warehouse.reports.days_of_stock') }}: {{ number_format($stats['days_of_stock'] ?? 0, 2) }}</div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
