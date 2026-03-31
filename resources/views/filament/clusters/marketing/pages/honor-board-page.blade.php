<x-filament-panels::page>
    @vite(['resources/css/app.css'])

    <style>
        .honor-board-bg {
            background: radial-gradient(circle at top left, rgba(245, 158, 11, 0.18), transparent 42%),
                radial-gradient(circle at top right, rgba(30, 64, 175, 0.16), transparent 46%),
                linear-gradient(160deg, #fff7ed 0%, #f8fafc 100%);
        }

        .honor-watermark::before {
            content: "{{ __('marketing.honor_board.watermark') }}";
            position: absolute;
            right: 1.25rem;
            top: 1rem;
            font-size: clamp(1.3rem, 2vw, 2.2rem);
            font-weight: 800;
            letter-spacing: .08em;
            color: rgba(148, 163, 184, 0.18);
            pointer-events: none;
            text-transform: uppercase;
        }

        .honor-scroll {
            max-height: 440px;
            overflow: auto;
        }

        .honor-sticky th {
            position: sticky;
            top: 0;
            z-index: 2;
        }
    </style>

    <div x-data="{ showHelp: false }" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 p-4 honor-board-bg honor-watermark relative">
            {{ $this->form }}

            @if (!empty($suggestions) && !empty($data['q']))
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600">{{ __('marketing.honor_board.suggestions') }}:</span>
                    @foreach ($suggestions as $suggestion)
                        <button
                            type="button"
                            wire:click='applySuggestion(@js($suggestion))'
                            class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:border-orange-400 hover:text-orange-600">
                            {{ $suggestion }}
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                <button type="button" @click="showHelp = true"
                    class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:border-orange-400 hover:text-orange-600">
                    ? {{ __('marketing.honor_board.help.title') }}
                </button>

                @if (!empty($data['q']))
                    <x-filament::button size="sm" color="gray" wire:click="clearSearch">
                        {{ __('marketing.honor_board.actions.clear_search') }}
                    </x-filament::button>
                @endif
            </div>
        </div>

        <div wire:loading class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            @for ($i = 0; $i < 3; $i++)
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="h-6 w-1/2 animate-pulse rounded bg-slate-200"></div>
                    <div class="mt-4 h-28 animate-pulse rounded-xl bg-slate-100"></div>
                    <div class="mt-3 space-y-2">
                        <div class="h-10 animate-pulse rounded bg-slate-100"></div>
                        <div class="h-10 animate-pulse rounded bg-slate-100"></div>
                        <div class="h-10 animate-pulse rounded bg-slate-100"></div>
                    </div>
                </div>
            @endfor
        </div>

        <div wire:loading.remove class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            @php
                $columns = [
                    [
                        'key' => 'sale',
                        'title' => __('marketing.honor_board.columns.sale.title'),
                        'subtitle' => __('marketing.honor_board.columns.sale.subtitle'),
                        'accent' => 'border-rose-200 bg-rose-50 text-rose-700',
                    ],
                    [
                        'key' => 'telesale',
                        'title' => __('marketing.honor_board.columns.telesale.title'),
                        'subtitle' => __('marketing.honor_board.columns.telesale.subtitle'),
                        'accent' => 'border-amber-200 bg-amber-50 text-amber-700',
                    ],
                    [
                        'key' => 'marketing',
                        'title' => __('marketing.honor_board.columns.marketing.title'),
                        'subtitle' => __('marketing.honor_board.columns.marketing.subtitle'),
                        'accent' => 'border-blue-200 bg-blue-50 text-blue-700',
                    ],
                ];
            @endphp

            @foreach ($columns as $column)
                @php
                    $top3 = $board[$column['key']]['top3'] ?? [];
                    $list = $board[$column['key']]['list'] ?? [];
                @endphp

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-4 flex items-start justify-between gap-2">
                        <div>
                            <h3 class="text-lg font-black text-slate-800">{{ $column['title'] }}</h3>
                            <p class="text-xs font-medium text-slate-500">{{ $column['subtitle'] }}</p>
                        </div>
                        <span class="rounded-full border px-3 py-1 text-[11px] font-bold {{ $column['accent'] }}">
                            {{ __('marketing.honor_board.top_3_badge') }}
                        </span>
                    </div>

                    @if (empty($top3) && empty($list))
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center">
                            <div class="text-3xl">📉</div>
                            <p class="mt-3 text-sm font-semibold text-slate-600">{{ __('marketing.honor_board.empty.title') }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ __('marketing.honor_board.empty.description') }}</p>
                        </div>
                    @else
                        <div class="grid grid-cols-3 items-end gap-2">
                            @php
                                $podium = [
                                    $top3[1] ?? null,
                                    $top3[0] ?? null,
                                    $top3[2] ?? null,
                                ];
                            @endphp

                            @foreach ($podium as $index => $row)
                                <div class="rounded-xl border p-2 text-center {{ $index === 1 ? 'min-h-[152px] border-amber-300 bg-amber-50' : 'min-h-[132px] border-slate-200 bg-slate-50' }}">
                                    @if ($row)
                                        <div class="mx-auto mb-1 flex h-8 w-8 items-center justify-center rounded-full bg-white text-xs font-black text-slate-700 shadow">
                                            #{{ $row['rank'] }}
                                        </div>
                                        <p class="line-clamp-2 text-xs font-bold text-slate-700">{{ $row['name'] }}</p>
                                        <p class="mt-2 text-xs font-semibold text-slate-500">{{ number_format($row['adjusted_revenue']) }}</p>
                                        <p class="text-[11px] text-slate-500">{{ $row['conversion_rate'] }}%</p>
                                        @if (isset($row['score']))
                                            <p class="text-[11px] font-semibold text-slate-600">{{ __('marketing.honor_board.table.score') }}: {{ number_format($row['score'], 2) }}</p>
                                        @endif
                                    @else
                                        <div class="text-xs text-slate-400">-</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 rounded-xl border border-slate-200 honor-scroll">
                            <table class="w-full text-sm honor-sticky">
                                <thead>
                                    <tr class="bg-slate-100 text-xs uppercase text-slate-600">
                                        <th class="px-3 py-2 text-left">#</th>
                                        <th class="px-3 py-2 text-left">{{ __('marketing.honor_board.table.name') }}</th>
                                        <th class="px-3 py-2 text-center">{{ __('marketing.honor_board.table.contacts') }}</th>
                                        <th class="px-3 py-2 text-center">{{ __('marketing.honor_board.table.orders') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('marketing.honor_board.table.revenue') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('marketing.honor_board.table.conversion_rate') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('marketing.honor_board.table.score') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($list as $row)
                                        <tr class="odd:bg-white even:bg-slate-50 hover:bg-amber-50 transition-colors">
                                            <td class="px-3 py-2 font-semibold text-slate-700">{{ $row['rank'] }}</td>
                                            <td class="px-3 py-2 font-semibold text-slate-700">{{ $row['name'] }}</td>
                                            <td class="px-3 py-2 text-center text-slate-600">{{ $row['contacts'] }}</td>
                                            <td class="px-3 py-2 text-center text-slate-600">{{ $row['orders'] }}</td>
                                            <td class="px-3 py-2 text-right font-semibold text-slate-700">{{ number_format($row['adjusted_revenue']) }}</td>
                                            <td class="px-3 py-2 text-right text-slate-600">{{ $row['conversion_rate'] }}%</td>
                                            <td class="px-3 py-2 text-right text-slate-600">{{ number_format((float) ($row['score'] ?? 0), 2) }}</td>
                                        </tr>
                                    @endforeach

                                    @if (!empty($board[$column['key']]['total']))
                                        @php $total = $board[$column['key']]['total']; @endphp
                                        <tr class="sticky bottom-0 bg-slate-200/80 font-semibold text-slate-800 backdrop-blur">
                                            <td class="px-3 py-2">—</td>
                                            <td class="px-3 py-2">{{ $total['name'] }}</td>
                                            <td class="px-3 py-2 text-center">{{ $total['contacts'] }}</td>
                                            <td class="px-3 py-2 text-center">{{ $total['orders'] }}</td>
                                            <td class="px-3 py-2 text-right">{{ number_format($total['adjusted_revenue']) }}</td>
                                            <td class="px-3 py-2 text-right">{{ $total['conversion_rate'] }}%</td>
                                            <td class="px-3 py-2 text-right">{{ number_format((float) ($total['score'] ?? 0), 2) }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        <div x-cloak x-show="showHelp" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" @keydown.escape.window="showHelp = false">
            <div class="w-full max-w-xl rounded-2xl bg-white p-5 shadow-xl">
                <div class="flex items-center justify-between">
                    <h4 class="text-base font-black text-slate-800">{{ __('marketing.honor_board.help.title') }}</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" @click="showHelp = false">✕</button>
                </div>
                <div class="mt-4 space-y-2 text-sm text-slate-600">
                    <p>• {{ __('marketing.honor_board.help.conversion_formula') }}</p>
                    <p>• {{ __('marketing.honor_board.help.revenue_formula') }}</p>
                    <p>• {{ __('marketing.honor_board.help.pushsale_formula') }}</p>
                    <p>• {{ __('marketing.honor_board.help.telesale_attribution') }}</p>
                </div>
                <div class="mt-4 flex justify-end">
                    <x-filament::button color="gray" @click="showHelp = false">{{ __('marketing.honor_board.actions.close') }}</x-filament::button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
