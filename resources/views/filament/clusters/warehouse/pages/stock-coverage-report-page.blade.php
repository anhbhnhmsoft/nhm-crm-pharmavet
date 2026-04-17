<x-filament-panels::page>
    @vite(['resources/css/app.css'])
    <form wire:submit="generateReport" class="space-y-4">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('telesale.reports.generate') }}</x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('warehouse.reports.coverage_title') }}</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('warehouse.reports.available_stock') }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ number_format($stats['available_stock'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('warehouse.reports.avg_daily_out') }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-warning-600 dark:text-warning-400">{{ number_format($stats['avg_daily_out'] ?? 0, 2) }}</p>
            </div>
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('warehouse.reports.days_of_stock') }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-success-600 dark:text-success-400">{{ number_format($stats['days_of_stock'] ?? 0, 2) }}</p>
            </div>
        </div>
    </x-filament::section>

    <!-- Chart Section -->
    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('Biểu đồ mức độ đáp ứng') }}</x-slot>
        <div wire:ignore class="w-full overflow-hidden">
            <div x-data="coverageChart($wire)" x-init="initChart" x-ref="chart" class="w-full" style="min-height: 300px;"></div>
        </div>
    </x-filament::section>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('coverageChart', ($wire) => ({
                chart: null,
                initChart() {
                    let options = {
                        series: [{
                            name: 'Chỉ số',
                            data: [
                                $wire.stats ? ($wire.stats.available_stock || 0) : 0,
                                $wire.stats ? ($wire.stats.avg_daily_out || 0) : 0,
                                $wire.stats ? ($wire.stats.days_of_stock || 0) : 0
                            ]
                        }],
                        chart: {
                            type: 'bar',
                            height: 300,
                            width: '100%',
                            toolbar: { show: false },
                            fontFamily: 'inherit',
                            redrawOnParentResize: true,
                            redrawOnWindowResize: true,
                        },
                        colors: ['#3B82F6', '#F59E0B', '#10B981'], // Blue, Amber, Emerald
                        grid: {
                            padding: {
                                left: 10,
                                right: 30, // Extra right padding for horizontal bar labels
                                top: 0,
                                bottom: 0
                            }
                        },
                        plotOptions: {
                            bar: {
                                distributed: true,
                                borderRadius: 4,
                                horizontal: true,
                            }
                        },
                        dataLabels: {
                            enabled: true,
                            formatter: function (val) {
                                return Number(val).toLocaleString(undefined, {maximumFractionDigits: 2});
                            }
                        },
                        legend: { show: false },
                        xaxis: {
                            categories: [
                                '{{ __('warehouse.reports.available_stock') }}',
                                '{{ __('warehouse.reports.avg_daily_out') }}',
                                '{{ __('warehouse.reports.days_of_stock') }}'
                            ],
                            labels: {
                                formatter: function (val) {
                                    return Number(val).toLocaleString(undefined, {maximumFractionDigits: 0});
                                }
                            }
                        },
                        tooltip: {
                            y: {
                                formatter: function (val) {
                                    return Number(val).toLocaleString(undefined, {maximumFractionDigits: 2});
                                }
                            }
                        }
                    };

                    setTimeout(() => {
                        this.chart = new ApexCharts(this.$refs.chart, options);
                        this.chart.render();

                        this.resizeObserver = new ResizeObserver(() => {
                            if (this.chart && typeof this.chart.windowResizeHandler === 'function') {
                                this.chart.windowResizeHandler();
                            }
                        });
                        this.resizeObserver.observe(this.$refs.chart.parentElement);
                    }, 350);

                    this.$watch('$wire.stats', (value) => {
                        if (value) {
                            this.chart.updateSeries([{
                                data: [
                                    value.available_stock || 0,
                                    value.avg_daily_out || 0,
                                    value.days_of_stock || 0
                                ]
                            }]);
                        }
                    });
                },
                destroy() {
                    if (this.resizeObserver) this.resizeObserver.disconnect();
                    if (this.chart) this.chart.destroy();
                }
            }));
        });
    </script>
</x-filament-panels::page>
