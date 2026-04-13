<x-filament-panels::page>
    @vite(['resources/css/app.css'])
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-400 uppercase tracking-wider">{{ __('order.status.pending') }}</div>
            <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['pending'] ?? 0) }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="text-sm font-medium text-blue-400 uppercase tracking-wider">{{ __('order.status.shipping') }}</div>
            <div class="mt-2 text-3xl font-bold text-blue-600">{{ number_format($stats['shipping'] ?? 0) }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="text-sm font-medium text-success-400 uppercase tracking-wider">{{ __('order.status.completed') }}</div>
            <div class="mt-2 text-3xl font-bold text-success-600">{{ number_format($stats['completed'] ?? 0) }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="text-sm font-medium text-danger-400 uppercase tracking-wider">{{ __('order.status.cancelled') }}</div>
            <div class="mt-2 text-3xl font-bold text-danger-600">{{ number_format($stats['cancelled'] ?? 0) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border-l-4 border-primary-500">
            <div class="flex items-center space-x-2">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('telesale.reports.gross_revenue') }}</div>
                <div title="{{ __('telesale.reports.gross_revenue_desc') }}">
                    <x-filament::icon
                        icon="heroicon-m-information-circle"
                        class="h-4 w-4 text-gray-400 cursor-help"
                    />
                </div>
            </div>
            <div class="mt-2 text-4xl font-extrabold text-primary-600 tracking-tight">
                {{ number_format($stats['gross_revenue'] ?? 0) }} <span class="text-lg font-normal text-gray-400">VND</span>
            </div>
            <div class="mt-1 text-xs text-gray-500 italic">{{ __('telesale.reports.gross_revenue_subtitle') }}</div>
        </div>
        
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border-l-4 border-success-500">
            <div class="flex items-center space-x-2">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('telesale.reports.net_revenue') }}</div>
                <div title="{{ __('telesale.reports.net_revenue_desc') }}">
                    <x-filament::icon
                        icon="heroicon-m-information-circle"
                        class="h-4 w-4 text-gray-400 cursor-help"
                    />
                </div>
            </div>
            <div class="mt-2 text-4xl font-extrabold text-success-600 tracking-tight">
                {{ number_format($stats['net_revenue'] ?? 0) }} <span class="text-lg font-normal text-gray-400">VND</span>
            </div>
            <div class="mt-1 text-xs text-secondary-500 italic font-medium">{{ __('telesale.reports.net_revenue_subtitle') }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
        <!-- Order Status Donut Chart -->
        <x-filament::section>
            <x-slot name="heading">{{ __('telesale.reports.ceo_order_status_chart') }}</x-slot>
            <div class="flex justify-center w-full" wire:ignore>
                <div x-data="orderStatusChart(@js($stats))" x-init="setTimeout(() => initChart(), 100)" x-ref="chart" class="w-full min-h-[350px]"></div>
            </div>
        </x-filament::section>

        <!-- Revenue Analysis Radial/Bar -->
        <x-filament::section>
            <x-slot name="heading">{{ __('telesale.reports.ceo_revenue_chart') }}</x-slot>
            <div class="flex justify-center w-full" wire:ignore>
                <div x-data="revenueAnalysisChart(@js($stats))" x-init="setTimeout(() => initChart(), 100)" x-ref="chart" class="w-full min-h-[350px]"></div>
            </div>
        </x-filament::section>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('orderStatusChart', (stats) => ({
                initChart() {
                    let options = {
                        series: [stats.pending || 0, stats.shipping || 0, stats.completed || 0, stats.cancelled || 0],
                        chart: {
                            type: 'donut',
                            height: 350,
                            fontFamily: 'inherit'
                        },
                        labels: [
                            '{{ __('order.status.pending') }}', 
                            '{{ __('order.status.shipping') }}', 
                            '{{ __('order.status.completed') }}', 
                            '{{ __('order.status.cancelled') }}'
                        ],
                        colors: ['#9CA3AF', '#3B82F6', '#10B981', '#EF4444'], // Gray, Blue, Green, Red
                        plotOptions: {
                            pie: {
                                donut: {
                                    labels: {
                                        show: true,
                                        total: {
                                            show: true,
                                            label: '{{ __('telesale.reports.total_orders') }}',
                                            formatter: function (w) {
                                                return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        dataLabels: { enabled: true },
                        legend: { position: 'bottom' }
                    };

                    new ApexCharts(this.$refs.chart, options).render();
                }
            }));

            Alpine.data('revenueAnalysisChart', (stats) => ({
                initChart() {
                    let gross = stats.gross_revenue || 0;
                    let net = stats.net_revenue || 0;

                    let options = {
                        series: [{
                            name: '{{ __('telesale.reports.gross_revenue') }}',
                            data: [gross]
                        }, {
                            name: '{{ __('telesale.reports.net_revenue') }}',
                            data: [net]
                        }],
                        chart: {
                            type: 'bar',
                            height: 350,
                            toolbar: { show: false },
                            fontFamily: 'inherit'
                        },
                        plotOptions: {
                            bar: {
                                horizontal: false,
                                columnWidth: '45%',
                                borderRadius: 8,
                                dataLabels: {
                                    position: 'top',
                                },
                            }
                        },
                        dataLabels: {
                            enabled: true,
                            formatter: function (val) {
                                return Intl.NumberFormat('vi-VN').format(val);
                            },
                            offsetY: -20,
                            style: {
                                fontSize: '12px',
                                colors: ["#64748b"]
                            }
                        },
                        stroke: {
                            show: true,
                            width: 2,
                            colors: ['transparent']
                        },
                        colors: ['#3B82F6', '#10B981'],
                        xaxis: {
                            categories: ['{{ __('telesale.reports.ceo_revenue_chart') }}'],
                            axisBorder: { show: false },
                            axisTicks: { show: false }
                        },
                        yaxis: {
                            labels: {
                                formatter: function (val) {
                                    return Intl.NumberFormat('vi-VN').format(val);
                                }
                            }
                        },
                        fill: {
                            opacity: 1
                        },
                        tooltip: {
                            y: {
                                formatter: function (val) {
                                    return Intl.NumberFormat('vi-VN').format(val) + " {{ __('telesale.form.currency_suffix') }}";
                                }
                            }
                        },
                        legend: {
                            position: 'bottom',
                            horizontalAlign: 'center',
                        }
                    };

                    new ApexCharts(this.$refs.chart, options).render();
                }
            }));
        });
    </script>
</x-filament-panels::page>
