<x-filament-panels::page>
        @vite(['resources/css/app.css'])
    <form wire:submit="generateReport" class="space-y-4">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('telesale.reports.generate') }}</x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('warehouse.reports.revenue_title') }}</x-slot>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="overflow-x-auto">
                <table class="w-full text-left divide-y divide-gray-200 dark:divide-white/5">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-6 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-left">{{ __('warehouse.form.name') }}</th>
                            <th class="px-6 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-right">{{ __('warehouse.reports.total_orders') }}</th>
                            <th class="px-6 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-right">{{ __('warehouse.reports.total_revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($rows as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition duration-75">
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 font-medium">{{ $row['warehouse_name'] }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 text-right">
                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/30">
                                        {{ number_format($row['total_orders']) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 text-right font-semibold text-primary-600 dark:text-primary-400">
                                    {{ number_format($row['total_revenue']) }} ₫
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
        <x-slot name="heading">{{ __('Biểu đồ doanh thu theo kho') }}</x-slot>
        <div wire:ignore class="flex justify-center w-full overflow-hidden">
            <div x-data="revenueChart($wire)" x-init="initChart" x-ref="chart" class="w-full max-w-4xl" style="min-height: 380px;"></div>
        </div>
    </x-filament::section>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('revenueChart', ($wire) => ({
                chart: null,
                initChart() {
                    const rows = $wire.rows || [];
                    const categories = rows.map(r => r.warehouse_name || 'N/A');
                    const revenueData = rows.map(r => r.total_revenue || 0);

                    let options = {
                        series: revenueData,
                        chart: {
                            type: 'donut',
                            height: 380,
                            width: '100%',
                            fontFamily: 'inherit',
                            redrawOnParentResize: true,
                            redrawOnWindowResize: true,
                        },
                        labels: categories,
                        colors: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#14B8A6', '#F97316'],
                        dataLabels: {
                            enabled: true,
                            formatter: function (val) {
                                return val.toFixed(1) + "%"
                            }
                        },
                        plotOptions: {
                            pie: {
                                donut: {
                                    size: '65%',
                                    labels: {
                                        show: true,
                                        name: {
                                            show: true,
                                            fontSize: '22px',
                                            fontFamily: 'inherit',
                                            fontWeight: 600,
                                        },
                                        value: {
                                            show: true,
                                            fontSize: '16px',
                                            fontFamily: 'inherit',
                                            formatter: function (val) {
                                                return Number(val).toLocaleString() + ' đ'
                                            }
                                        },
                                        total: {
                                            show: true,
                                            showAlways: true,
                                            label: 'Tổng Doanh Thu',
                                            fontSize: '16px',
                                            fontFamily: 'inherit',
                                            fontWeight: 600,
                                            formatter: function (w) {
                                                const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                                if (total >= 1000000) {
                                                    return (total / 1000000).toLocaleString() + ' Tr';
                                                }
                                                return total.toLocaleString() + ' đ';
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        tooltip: {
                            y: {
                                formatter: function (val) {
                                    return val.toLocaleString() + ' đ';
                                }
                            }
                        },
                        legend: {
                            position: 'bottom'
                        }
                    };

                    setTimeout(() => {
                        this.chart = new ApexCharts(this.$refs.chart, options);
                        this.chart.render();
                        
                        // Handle Sidebar toggle manually just in case
                        this.resizeObserver = new ResizeObserver(() => {
                            if (this.chart) {
                                this.chart.resize();
                            }
                        });
                        this.resizeObserver.observe(this.$refs.chart.parentElement);
                    }, 100);

                    this.$watch('$wire.rows', (newRows) => {
                        if (newRows) {
                            const newLabels = newRows.map(r => r.warehouse_name || 'N/A');
                            const newData = newRows.map(r => r.total_revenue || 0);
                            
                            this.chart.updateOptions({
                                labels: newLabels,
                                series: newData
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
