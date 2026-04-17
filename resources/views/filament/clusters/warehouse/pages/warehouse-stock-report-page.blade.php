<x-filament-panels::page>
    @vite(['resources/css/app.css'])

    <form wire:submit="generateReport" class="space-y-4">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('telesale.reports.generate') }}</x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('warehouse.reports.stock_title') }}</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('warehouse.reports.opening') }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ number_format($stats['opening'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('warehouse.reports.imports') }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-primary-600 dark:text-primary-400">{{ number_format($stats['imports'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('warehouse.reports.exports') }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-danger-600 dark:text-danger-400">{{ number_format($stats['exports'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('warehouse.reports.closing') }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-success-600 dark:text-success-400">{{ number_format($stats['closing'] ?? 0) }}</p>
            </div>
        </div>
    </x-filament::section>

    <!-- Chart Section -->
    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('Biểu đồ nhập xuất tồn') }}</x-slot>
        <div wire:ignore class="w-full overflow-hidden">
            <div x-data="stockChart($wire)" x-init="initChart" x-ref="chart" class="w-full" style="min-height: 350px;"></div>
        </div>
    </x-filament::section>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('stockChart', ($wire) => ({
                chart: null,
                initChart() {
                    let options = {
                        series: [{
                            name: 'Số lượng',
                            data: [
                                $wire.stats ? ($wire.stats.opening || 0) : 0,
                                $wire.stats ? ($wire.stats.imports || 0) : 0,
                                $wire.stats ? ($wire.stats.exports || 0) : 0,
                                $wire.stats ? ($wire.stats.closing || 0) : 0
                            ]
                        }],
                        chart: {
                            type: 'bar',
                            height: 350,
                            width: '100%',
                            toolbar: { show: false },
                            fontFamily: 'inherit',
                            redrawOnParentResize: true,
                            redrawOnWindowResize: true,
                        },
                        colors: ['#3B82F6', '#10B981', '#F43F5E', '#8B5CF6'], // Blue, Emerald, Rose, Purple
                        plotOptions: {
                            bar: {
                                distributed: true,
                                borderRadius: 4,
                                horizontal: false,
                                columnWidth: '40%'
                            }
                        },
                        grid: {
                            padding: {
                                left: 10,
                                right: 10,
                                top: 0,
                                bottom: 0
                            }
                        },
                        dataLabels: {
                            enabled: true,
                            formatter: function (val) {
                                return val.toLocaleString();
                            }
                        },
                        legend: { show: false },
                        xaxis: {
                            categories: [
                                '{{ __('warehouse.reports.opening') }}',
                                '{{ __('warehouse.reports.imports') }}',
                                '{{ __('warehouse.reports.exports') }}',
                                '{{ __('warehouse.reports.closing') }}'
                            ],
                            labels: {
                                style: {
                                    fontSize: '12px'
                                }
                            }
                        },
                        yaxis: {
                            labels: {
                                formatter: function (val) {
                                    return val.toLocaleString();
                                }
                            }
                        },
                        tooltip: {
                            y: {
                                formatter: function (val) {
                                    return val.toLocaleString();
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
                                    value.opening || 0,
                                    value.imports || 0,
                                    value.exports || 0,
                                    value.closing || 0
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
