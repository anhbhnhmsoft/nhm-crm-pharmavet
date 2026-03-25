<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('telesale.reports.ceo_dashboard_title') }}</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>{{ __('order.status.pending') }}: {{ $stats['pending'] ?? 0 }}</div>
            <div>{{ __('order.status.shipping') }}: {{ $stats['shipping'] ?? 0 }}</div>
            <div>{{ __('order.status.completed') }}: {{ $stats['completed'] ?? 0 }}</div>
            <div>{{ __('order.status.cancelled') }}: {{ $stats['cancelled'] ?? 0 }}</div>
            <div>{{ __('telesale.reports.gross_revenue') }}: {{ number_format($stats['gross_revenue'] ?? 0) }}</div>
            <div>{{ __('telesale.reports.net_revenue') }}: {{ number_format($stats['net_revenue'] ?? 0) }}</div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
