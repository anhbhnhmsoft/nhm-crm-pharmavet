<x-filament-panels::page>
    <form wire:submit="generateReport" class="space-y-4">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('telesale.reports.generate') }}</x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('telesale.reports.data_quality_title') }}</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>{{ __('telesale.reports.total_contacts') }}: {{ $stats['total_contacts'] ?? 0 }}</div>
            <div>{{ __('telesale.reports.duplicate_contacts') }}: {{ $stats['duplicate_contacts'] ?? 0 }}</div>
            <div>{{ __('telesale.reports.unique_contacts') }}: {{ $stats['unique_contacts'] ?? 0 }}</div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
