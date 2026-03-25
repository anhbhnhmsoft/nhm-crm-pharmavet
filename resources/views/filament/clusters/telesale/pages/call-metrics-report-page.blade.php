<x-filament-panels::page>
    <form wire:submit="generateReport" class="space-y-4">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('telesale.reports.generate') }}</x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('telesale.reports.call_metrics_title') }}</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>{{ __('telesale.reports.total_calls') }}: {{ $metrics['total_calls'] ?? 0 }}</div>
            <div>{{ __('telesale.reports.connected_calls') }}: {{ $metrics['connected_calls'] ?? 0 }}</div>
            <div>{{ __('telesale.reports.total_duration') }}: {{ $metrics['total_duration'] ?? 0 }}</div>
            <div>{{ __('telesale.reports.avg_duration') }}: {{ $metrics['avg_duration'] ?? 0 }}</div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
