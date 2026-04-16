<x-filament-panels::page>
    {{-- Header stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center">
                <x-heroicon-o-tag class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('accounting.expense_category.total_categories') }}</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ count($categoryStats) }}</p>
            </div>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-emerald-100 dark:bg-emerald-900 flex items-center justify-center">
                <x-heroicon-o-document-text class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('accounting.expense_category.total_records') }}</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($this->getTotalExpenses()) }}</p>
            </div>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center">
                <x-heroicon-o-banknotes class="h-6 w-6 text-amber-600 dark:text-amber-400" />
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('accounting.expense_category.total_amount') }}</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($this->getTotalAmount(), 0, ',', '.') }} đ</p>
            </div>
        </div>
    </div>

    {{-- Notice: System managed --}}
    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 p-4 mb-6 flex items-start gap-3">
        <x-heroicon-o-information-circle class="h-5 w-5 text-blue-500 mt-0.5 flex-shrink-0" />
        <div class="text-sm text-blue-700 dark:text-blue-300">
            <p class="font-semibold mb-1">{{ __('accounting.expense_category.system_notice_title') }}</p>
            <p>{{ __('accounting.expense_category.system_notice_body') }}</p>
        </div>
    </div>

    {{-- Category cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ($categoryStats as $item)
            <div class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                {{-- Colored top bar --}}
                <div class="h-1.5" style="background-color: {{ $item['hex'] }};"></div>

                <div class="p-5">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-8 w-8 rounded-full items-center justify-center text-white text-sm font-bold"
                                  style="background-color: {{ $item['hex'] }};">
                                {{ $item['value'] }}
                            </span>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white text-sm">
                                    {{ $item['label'] }}
                                </h3>
                                @if ($item['is_system'])
                                    <span class="inline-flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                                        <x-heroicon-o-lock-closed class="h-3 w-3" />
                                        {{ __('accounting.expense_category.system') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                              style="background-color: {{ $item['hex'] }}20; color: {{ $item['hex'] }};">
                            #{{ $item['value'] }}
                        </span>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4 min-h-[32px]">
                        {{ $item['description'] }}
                    </p>

                    <div class="border-t border-gray-100 dark:border-gray-700 pt-3 grid grid-cols-2 gap-3">
                        <div class="text-center">
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('accounting.expense_category.expense_count') }}</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($item['count']) }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('accounting.expense_category.expense_total') }}</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ number_format($item['total'], 0, ',', '.') }} đ
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
