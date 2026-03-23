<x-filament-panels::page>
    <form wire:submit="generateReport" class="space-y-4">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('telesale.reports.generate') }}</x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('telesale.reports.sale_kpi_title') }}</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>{{ __('telesale.reports.revenue') }}: {{ number_format($summary['revenue'] ?? 0) }}</div>
            <div>{{ __('telesale.reports.kpi_target') }}: {{ number_format($summary['target'] ?? 0) }}</div>
            <div>{{ __('telesale.reports.kpi_progress') }}: {{ $summary['kpi_progress'] ?? 0 }}%</div>
            <div>{{ __('telesale.reports.days_progress') }}: {{ $summary['days_progress'] ?? 0 }}%</div>
            <div>{{ __('telesale.reports.estimated_bonus') }}: {{ number_format($summary['estimated_bonus'] ?? 0) }}</div>
            <div>{{ __('telesale.reports.estimated_income') }}: {{ number_format($summary['estimated_income'] ?? 0) }}</div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
