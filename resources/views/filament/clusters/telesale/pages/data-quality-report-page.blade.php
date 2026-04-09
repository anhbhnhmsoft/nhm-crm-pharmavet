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
        <x-slot name="heading">{{ __('telesale.reports.data_quality_title') }}</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>{{ __('telesale.reports.total_contacts') }}: <span class="font-bold">{{ $stats['total_contacts'] ?? 0 }}</span></div>
            <div>{{ __('telesale.reports.duplicate_contacts') }}: <span class="font-bold text-danger-600">{{ $stats['duplicate_contacts'] ?? 0 }}</span></div>
            <div>{{ __('telesale.reports.unique_contacts') }}: <span class="font-bold text-success-600">{{ $stats['unique_contacts'] ?? 0 }}</span></div>
        </div>
    </x-filament::section>

    <!-- Chart Section -->
    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('telesale.reports.data_quality_chart_title') }}</x-slot>
        <div class="flex justify-center" wire:ignore>
            <div x-data="dataQualityChart($wire)" x-init="initChart" x-ref="chart" class="w-full max-w-md"></div>
        </div>
    </x-filament::section>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('dataQualityChart', ($wire) => ({
                chart: null,
                initChart() {
                    let unique = $wire.stats ? ($wire.stats.unique_contacts || 0) : 0;
                    let duplicate = $wire.stats ? ($wire.stats.duplicate_contacts || 0) : 0;

                    let options = {
                        series: [unique, duplicate],
                        chart: {
                            type: 'donut',
                            height: 380,
                            fontFamily: 'inherit'
                        },
                        labels: ['{{ __('telesale.reports.unique_contacts') }}', '{{ __('telesale.reports.duplicate_contacts') }}'],
                        colors: ['#10B981', '#EF4444'],
                        plotOptions: {
                            pie: {
                                donut: {
                                    size: '65%',
                                    labels: {
                                        show: true,
                                        name: {
                                            show: true,
                                            fontSize: '14px',
                                        },
                                        value: {
                                            show: true,
                                            fontSize: '24px',
                                            fontWeight: 'bold',
                                            formatter: function (val) {
                                                return val;
                                            }
                                        },
                                        total: {
                                            show: true,
                                            showAlways: true,
                                            label: '{{ __('telesale.reports.total_contacts') }}',
                                            fontSize: '16px',
                                            fontWeight: 'bold',
                                            formatter: function (w) {
                                                return w.globals.seriesTotals.reduce((a, b) => {
                                                    return a + b
                                                }, 0);
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        dataLabels: {
                            enabled: true,
                            formatter: function (val) {
                                return val.toFixed(1) + "%";
                            }
                        },
                        legend: {
                            position: 'bottom',
                            markers: {
                                radius: 12,
                            }
                        },
                        stroke: {
                            show: true,
                            colors: 'transparent',
                            width: 2
                        },
                        tooltip: {
                            y: {
                                formatter: function (val) {
                                    return val + ' contact';
                                }
                            }
                        }
                    };

                    this.chart = new ApexCharts(this.$refs.chart, options);
                    this.chart.render();

                    // Watch for state changes via Livewire
                    this.$watch('$wire.stats', (value) => {
                        if (value) {
                            let newUnique = value.unique_contacts || 0;
                            let newDuplicate = value.duplicate_contacts || 0;
                            
                            this.chart.updateSeries([newUnique, newDuplicate]);
                        }
                    });
                }
            }));
        });
    </script>
</x-filament-panels::page>
