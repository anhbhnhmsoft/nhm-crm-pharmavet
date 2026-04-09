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
        <x-slot name="heading">{{ __('telesale.reports.sale_kpi_title') }}</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>{{ __('telesale.reports.revenue') }}: {{ number_format($summary['revenue'] ?? 0) }}</div>
            <div>{{ __('telesale.reports.kpi_target') }}: {{ number_format($summary['target'] ?? 0) }}</div>
            <div>{{ __('telesale.reports.kpi_progress') }}: <span class="font-bold {{ ($summary['kpi_progress'] ?? 0) >= 100 ? 'text-success-600' : 'text-warning-600' }}">{{ $summary['kpi_progress'] ?? 0 }}%</span></div>
            <div>{{ __('telesale.reports.days_progress') }}: <span class="font-bold text-gray-600">{{ $summary['days_progress'] ?? 0 }}%</span></div>
            <div>{{ __('telesale.reports.estimated_bonus') }}: {{ number_format($summary['estimated_bonus'] ?? 0) }}</div>
            <div>{{ __('telesale.reports.estimated_income') }}: {{ number_format($summary['estimated_income'] ?? 0) }}</div>
        </div>
    </x-filament::section>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <!-- Revenue vs Target Chart -->
        <x-filament::section>
            <x-slot name="heading">{{ __('telesale.reports.revenue_vs_target') }}</x-slot>
            <div wire:ignore>
                <div x-data="revenueChart($wire)" x-init="initChart" x-ref="chart"></div>
            </div>
        </x-filament::section>

        <!-- Progress Chart -->
        <x-filament::section>
            <x-slot name="heading">{{ __('telesale.reports.progress_comparison') }}</x-slot>
            <div wire:ignore>
                <div x-data="progressChart($wire)" x-init="initChart" x-ref="chart"></div>
            </div>
        </x-filament::section>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('revenueChart', ($wire) => ({
                chart: null,
                initChart() {
                    let options = {
                        series: [
                            {
                                name: '{{ __('telesale.reports.revenue') }}',
                                data: [$wire.summary ? ($wire.summary.revenue || 0) : 0]
                            },
                            {
                                name: '{{ __('telesale.reports.kpi_target') }}',
                                data: [$wire.summary ? ($wire.summary.target || 0) : 0]
                            }
                        ],
                        chart: {
                            type: 'bar',
                            height: 350,
                            toolbar: { show: false },
                            fontFamily: 'inherit'
                        },
                        colors: ['#10B981', '#3B82F6'], // Emerald vs Blue
                        plotOptions: {
                            bar: {
                                horizontal: false,
                                columnWidth: '45%',
                                borderRadius: 4
                            },
                        },
                        dataLabels: { enabled: false },
                        stroke: {
                            show: true,
                            width: 2,
                            colors: ['transparent']
                        },
                        xaxis: {
                            categories: ['Giá trị (VND)'],
                        },
                        yaxis: {
                            labels: {
                                formatter: function (val) {
                                    if (val >= 1000000) {
                                        return (val / 1000000).toLocaleString() + ' Tr';
                                    }
                                    return val.toLocaleString();
                                }
                            }
                        },
                        tooltip: {
                            y: {
                                formatter: function (val) {
                                    return val.toLocaleString() + ' đ';
                                }
                            }
                        }
                    };

                    this.chart = new ApexCharts(this.$refs.chart, options);
                    this.chart.render();

                    // Watch for summary changes via Livewire
                    this.$watch('$wire.summary', (value) => {
                        if (value) {
                            this.chart.updateSeries([
                                { name: '{{ __('telesale.reports.revenue') }}', data: [value.revenue || 0] },
                                { name: '{{ __('telesale.reports.kpi_target') }}', data: [value.target || 0] }
                            ]);
                        }
                    });
                }
            }));

            Alpine.data('progressChart', ($wire) => ({
                chart: null,
                initChart() {
                    let options = {
                        series: [
                            {
                                name: 'Tỷ lệ %',
                                data: [
                                    $wire.summary ? ($wire.summary.kpi_progress || 0) : 0, 
                                    $wire.summary ? ($wire.summary.days_progress || 0) : 0
                                ]
                            }
                        ],
                        chart: {
                            type: 'bar',
                            height: 350,
                            toolbar: { show: false },
                            fontFamily: 'inherit'
                        },
                        colors: [function({ value, seriesIndex, dataPointIndex, w }) {
                            if (dataPointIndex === 0) { // KPI Progress
                                return value >= 100 ? '#10B981' : '#F59E0B'; // Emerald vs Amber
                            } else { // Days Progress
                                return '#6B7280'; // Gray
                            }
                        }],
                        plotOptions: {
                            bar: {
                                horizontal: true,
                                borderRadius: 4,
                                dataLabels: {
                                    position: 'top',
                                },
                            }
                        },
                        dataLabels: {
                            enabled: true,
                            offsetX: 40,
                            style: {
                                fontSize: '13px',
                                colors: ['var(--text-color, #374151)']
                            },
                            formatter: function (val) {
                                return val + '%';
                            }
                        },
                        xaxis: {
                            categories: ['{{ __('telesale.reports.kpi_progress') }}', '{{ __('telesale.reports.days_progress') }}'],
                            max: 100,
                            labels: {
                                formatter: function (val) {
                                    return val + '%';
                                }
                            }
                        },
                        tooltip: {
                            y: {
                                formatter: function (val) {
                                    return val + '%';
                                }
                            }
                        }
                    };

                    this.chart = new ApexCharts(this.$refs.chart, options);
                    this.chart.render();

                    // Watch
                    this.$watch('$wire.summary', (value) => {
                        if (value) {
                            // Cập nhật maxValue trục x để không bị cứng quá nếu vượt 100%
                            let maxVal = Math.max(100, value.kpi_progress || 0, value.days_progress || 0);
                            
                            this.chart.updateOptions({
                                xaxis: { max: maxVal > 100 ? maxVal + 10 : 100 }
                            });

                            this.chart.updateSeries([
                                { name: 'Tỷ lệ %', data: [value.kpi_progress || 0, value.days_progress || 0] }
                            ]);
                        }
                    });
                }
            }));
        });
    </script>
</x-filament-panels::page>
