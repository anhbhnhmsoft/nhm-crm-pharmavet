<x-filament-panels::page>
    @vite(['resources/css/app.css'])
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <form wire:submit="generateReport" class="space-y-4">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('telesale.reports.generate') }}</x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('telesale.reports.call_metrics_title') }}</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700">
                <div class="text-sm font-medium text-gray-500">{{ __('telesale.reports.total_calls') }}</div>
                <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($metrics['total_calls'] ?? 0) }}</div>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700">
                <div class="text-sm font-medium text-gray-500">{{ __('telesale.reports.connected_calls') }}</div>
                <div class="mt-1 text-2xl font-bold text-success-600">{{ number_format($metrics['connected_calls'] ?? 0) }}</div>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700">
                <div class="text-sm font-medium text-gray-500">{{ __('telesale.reports.total_duration') }}</div>
                <div class="mt-1 text-2xl font-bold text-primary-600">{{ number_format($metrics['total_duration'] ?? 0) }}s</div>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700">
                <div class="text-sm font-medium text-gray-500">{{ __('telesale.reports.avg_duration') }}</div>
                <div class="mt-1 text-2xl font-bold text-amber-600">{{ $metrics['avg_duration'] ?? 0 }}s</div>
            </div>
        </div>
    </x-filament::section>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <!-- Call Connection Rate Donut -->
        <x-filament::section>
            <x-slot name="heading">{{ __('telesale.reports.call_metrics_chart_title') }}</x-slot>
            <div class="flex justify-center" wire:ignore>
                <div x-data="callMetricsDonut($wire)" x-init="initChart" x-ref="chart" class="w-full max-w-md"></div>
            </div>
        </x-filament::section>

        <!-- Call Volume Bar Chart -->
        <x-filament::section>
            <x-slot name="heading">{{ __('telesale.reports.call_volume_comparison') }}</x-slot>
            <div wire:ignore>
                <div x-data="callVolumeBar($wire)" x-init="initChart" x-ref="chart"></div>
            </div>
        </x-filament::section>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('callMetricsDonut', ($wire) => ({
                chart: null,
                initChart() {
                    let connected = $wire.metrics ? ($wire.metrics.connected_calls || 0) : 0;
                    let missed = $wire.metrics ? (($wire.metrics.total_calls || 0) - connected) : 0;
                    
                    if (missed < 0) missed = 0;

                    let options = {
                        series: [connected, missed],
                        chart: {
                            type: 'donut',
                            height: 380,
                            fontFamily: 'inherit'
                        },
                        labels: ['{{ __('telesale.reports.connected') }}', '{{ __('telesale.reports.missed') }}'],
                        colors: ['#10B981', '#F59E0B'], // Green (Success) vs Amber (Missed)
                        plotOptions: {
                            pie: {
                                donut: {
                                    size: '65%',
                                    labels: {
                                        show: true,
                                        total: {
                                            show: true,
                                            label: '{{ __('telesale.reports.total_calls') }}',
                                            formatter: function (w) {
                                                return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        dataLabels: { enabled: true },
                        legend: { position: 'bottom' },
                        tooltip: {
                            y: {
                                formatter: function (val) {
                                    return val + ' cuộc gọi';
                                }
                            }
                        }
                    };

                    this.chart = new ApexCharts(this.$refs.chart, options);
                    this.chart.render();

                    this.$watch('$wire.metrics', (value) => {
                        if (value) {
                            let newConnected = value.connected_calls || 0;
                            let newMissed = (value.total_calls || 0) - newConnected;
                            if (newMissed < 0) newMissed = 0;
                            this.chart.updateSeries([newConnected, newMissed]);
                        }
                    });
                }
            }));

            Alpine.data('callVolumeBar', ($wire) => ({
                chart: null,
                initChart() {
                    let options = {
                        series: [{
                            name: 'Số lượng',
                            data: [
                                $wire.metrics ? ($wire.metrics.total_calls || 0) : 0, 
                                $wire.metrics ? ($wire.metrics.connected_calls || 0) : 0
                            ]
                        }],
                        chart: {
                            type: 'bar',
                            height: 350,
                            toolbar: { show: false },
                            fontFamily: 'inherit'
                        },
                        colors: ['#3B82F6'], // Blue
                        plotOptions: {
                            bar: {
                                borderRadius: 4,
                                horizontal: false,
                                columnWidth: '50%',
                                distributed: true
                            }
                        },
                        xaxis: {
                            categories: ['{{ __('telesale.reports.total_calls') }}', '{{ __('telesale.reports.connected_calls') }}'],
                        },
                        legend: { show: false },
                        tooltip: {
                            y: {
                                formatter: function (val) {
                                    return val + ' cuộc gọi';
                                }
                            }
                        }
                    };

                    this.chart = new ApexCharts(this.$refs.chart, options);
                    this.chart.render();

                    this.$watch('$wire.metrics', (value) => {
                        if (value) {
                            this.chart.updateSeries([{
                                data: [value.total_calls || 0, value.connected_calls || 0]
                            }]);
                        }
                    });
                }
            }));
        });
    </script>
</x-filament-panels::page>
