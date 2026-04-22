<x-filament-panels::page>
    @vite(['resources/css/app.css'])

    <div class="space-y-6" wire:poll.15s="loadJobs">
        <form wire:submit="applyFilters" class="space-y-4">
            {{ $this->form }}

            <div class="flex justify-end gap-2">
                <x-filament::button type="button" color="gray" wire:click="loadJobs">
                    {{ __('telesale.reports.refresh_history') }}
                </x-filament::button>
                <x-filament::button type="submit" color="primary">
                    {{ __('telesale.reports.filter_history') }}
                </x-filament::button>
            </div>
        </form>

        <x-filament::section>
            <x-slot name="heading">{{ __('telesale.reports.export_history_title') }}</x-slot>

            @if (empty($jobs))
                <p class="text-sm text-gray-500">{{ __('telesale.reports.export_history_empty') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse border border-gray-200 dark:border-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="p-3 border border-gray-200 dark:border-gray-700">#</th>
                                <th class="p-3 border border-gray-200 dark:border-gray-700">{{ __('telesale.reports.export_history_report') }}</th>
                                <th class="p-3 border border-gray-200 dark:border-gray-700">{{ __('telesale.reports.export_history_status') }}</th>
                                <th class="p-3 border border-gray-200 dark:border-gray-700 text-center">{{ __('telesale.reports.export_history_rows') }}</th>
                                <th class="p-3 border border-gray-200 dark:border-gray-700">{{ __('telesale.reports.export_history_created_at') }}</th>
                                <th class="p-3 border border-gray-200 dark:border-gray-700">{{ __('telesale.reports.export_history_completed_at') }}</th>
                                <th class="p-3 border border-gray-200 dark:border-gray-700">{{ __('telesale.reports.export_history_file') }}</th>
                                <th class="p-3 border border-gray-200 dark:border-gray-700 text-center">{{ __('telesale.reports.export_history_action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($jobs as $job)
                                <tr wire:key="export-job-{{ $job['id'] }}">
                                    <td class="p-3 border border-gray-200 dark:border-gray-700 font-medium">{{ $job['id'] }}</td>
                                    <td class="p-3 border border-gray-200 dark:border-gray-700">{{ $job['report_label'] }}</td>
                                    <td class="p-3 border border-gray-200 dark:border-gray-700">
                                        <div class="space-y-2">
                                            <x-filament::badge :color="$job['status_color']">
                                                {{ $job['status_label'] }}
                                            </x-filament::badge>

                                            @if (filled($job['error_message']))
                                                <div class="text-xs text-danger-600 dark:text-danger-400">
                                                    {{ $job['error_message'] }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="p-3 border border-gray-200 dark:border-gray-700 text-center">{{ number_format($job['row_count']) }}</td>
                                    <td class="p-3 border border-gray-200 dark:border-gray-700">{{ $job['created_at'] ?? '-' }}</td>
                                    <td class="p-3 border border-gray-200 dark:border-gray-700">{{ $job['completed_at'] ?? '-' }}</td>
                                    <td class="p-3 border border-gray-200 dark:border-gray-700">
                                        <div class="text-sm">{{ $job['file_name'] ?? '-' }}</div>
                                    </td>
                                    <td class="p-3 border border-gray-200 dark:border-gray-700 text-center">
                                        @if ($job['can_download'])
                                            <x-filament::button
                                                size="sm"
                                                type="button"
                                                color="gray"
                                                wire:click="downloadExport({{ $job['id'] }})"
                                            >
                                                {{ __('common.action.download') }}
                                            </x-filament::button>
                                        @else
                                            <span class="text-sm text-gray-400">{{ __('telesale.reports.export_history_not_ready') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
