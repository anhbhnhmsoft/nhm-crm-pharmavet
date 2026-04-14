<x-filament-panels::page>
    @vite(['resources/css/app.css'])
    <x-filament::section>
        <x-slot name="heading">{{ __('warehouse.reports.matrix_title') }}</x-slot>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="overflow-x-auto">
                <table class="w-full text-left divide-y divide-gray-200 dark:divide-white/5">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-6 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-left">{{ __('warehouse.form.name') }}</th>
                            <th class="px-6 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-right">{{ __('warehouse.reports.quantity') }}</th>
                            <th class="px-6 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-right">{{ __('warehouse.reports.pending') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($rows as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition duration-75">
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $row['warehouse'] }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 text-right font-medium">
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-400 dark:ring-emerald-400/30">
                                        {{ number_format($row['quantity']) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 text-right font-medium">
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-400 dark:ring-amber-400/30">
                                        {{ number_format($row['pending']) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </x-filament::section>

    <!-- Chart Section -->
    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('Biểu đồ ma trận kho') }}</x-slot>
        <div wire:ignore class="w-full overflow-hidden">
            <div x-data="matrixChart($wire)" x-init="initChart" x-ref="chart" class="w-full" style="min-height: 350px;"></div>
        </div>
    </x-filament::section>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('matrixChart', ($wire) => ({
                chart: null,
                initChart() {
                    const rows = $wire.rows || [];
                    const categories = rows.map(r => r.warehouse);
                    const quantityData = rows.map(r => r.quantity || 0);
                    const pendingData = rows.map(r => r.pending || 0);

                    let options = {
                        series: [
                            {
                                name: '{{ __('warehouse.reports.quantity') }}',
                                data: quantityData
                            },
                            {
                                name: '{{ __('warehouse.reports.pending') }}',
                                data: pendingData
                            }
                        ],
                        chart: {
                            type: 'bar',
                            height: 350,
                            width: '100%',
                            toolbar: { show: false },
                            fontFamily: 'inherit',
                            redrawOnParentResize: true,
                            redrawOnWindowResize: true,
                        },
                        colors: ['#10B981', '#F59E0B'], // Emerald, Amber
                        plotOptions: {
                            bar: {
                                horizontal: false,
                                columnWidth: '45%',
                                borderRadius: 4
                            },
                        },
                        grid: {
                            padding: {
                                left: 20,
                                right: 20,
                                top: 0,
                                bottom: 0
                            }
                        },
                        dataLabels: {
                            enabled: false
                        },
                        stroke: {
                            show: true,
                            width: 2,
                            colors: ['transparent']
                        },
                        xaxis: {
                            categories: categories,
                        },
                        yaxis: {
                            title: {
                                text: 'Số lượng'
                            },
                            labels: {
                                formatter: function (val) {
                                    return val.toLocaleString();
                                }
                            }
                        },
                        fill: {
                            opacity: 1
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

                    this.$watch('$wire.rows', (newRows) => {
                        if (newRows) {
                            this.chart.updateSeries([
                                { name: '{{ __('warehouse.reports.quantity') }}', data: newRows.map(r => r.quantity || 0) },
                                { name: '{{ __('warehouse.reports.pending') }}', data: newRows.map(r => r.pending || 0) }
                            ]);
                            this.chart.updateOptions({
                                xaxis: { categories: newRows.map(r => r.warehouse) }
                            });
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
