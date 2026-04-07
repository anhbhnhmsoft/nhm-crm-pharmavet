@vite(['resources/css/app.css'])

<div x-data="{ loading: true }" x-init="setTimeout(() => loading = false, 600)" class="relative min-h-[400px]">
    
    <!-- SKELETON LOADING AGENT -->
    <div x-show="loading" 
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="absolute inset-0 z-50 space-y-6 pl-10 pr-2 py-4"
    >
        @for($i = 0; $i < 3; $i++)
            <div class="animate-pulse bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4 shadow-sm">
                <div class="flex justify-between mb-4">
                    <div class="h-5 w-24 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                    <div class="h-4 w-32 bg-gray-100 dark:bg-gray-700 rounded"></div>
                </div>
                <div class="flex gap-6">
                    <div class="w-40 aspect-square bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                    <div class="flex-1 space-y-4">
                        <div class="h-10 bg-gray-100 dark:bg-gray-700 rounded-lg w-full"></div>
                        <div class="flex gap-2">
                            <div class="h-8 w-20 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                            <div class="h-8 w-28 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endfor
    </div>

    <!-- MAIN CONTENT -->
    <div x-show="!loading" 
         x-transition:enter="transition ease-out duration-500"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="relative space-y-6 pl-10 pr-2 py-4"
    >
        <!-- Timeline Line -->
        <div class="absolute inset-y-0 left-4 block w-0.5 bg-gray-200 dark:bg-gray-700"></div>

        @forelse($attachments as $item)
            <div class="group relative bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden">
                <!-- Timeline Dot -->
                <div class="absolute -left-[3.1rem] top-6 h-4 w-4 rounded-full border-4 border-white dark:border-gray-900 bg-blue-500 shadow-sm z-10 transition-transform group-hover:scale-125"></div>

                <div class="p-4">
                    <!-- Header Info -->
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 uppercase tracking-wider shadow-sm">
                                {{ __('accounting.fund_transaction.version') }} #{{ $item->version }}
                            </span>
                            
                            @if($loop->first)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400 uppercase tracking-wider animate-bounce-subtle">
                                    {{ __('common.status.latest') }}
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center gap-4 text-[11px] text-gray-400 dark:text-gray-500 italic">
                            <div class="flex items-center gap-1 group/user hover:text-blue-500 transition-colors">
                                <x-heroicon-m-user class="w-3.5 h-3.5" />
                                <span>{{ $item->uploader?->name ?? 'Admin' }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <x-heroicon-m-clock class="w-3.5 h-3.5" />
                                <span>{{ optional($item->uploaded_at)->format('d/m/Y H:i') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Content Area -->
                    <div class="flex flex-col md:flex-row gap-6 items-start">
                        @php
                            $extension = strtolower(pathinfo($item->file_path, PATHINFO_EXTENSION));
                            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                        @endphp

                        @if($isImage)
                            <div class="relative w-full md:w-40 aspect-square rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 group/img shadow-inner">
                                <a href="{{ asset('storage/' . $item->file_path) }}" target="_blank" class="block w-full h-full">
                                    <img src="{{ asset('storage/' . $item->file_path) }}" 
                                         alt="{{ $item->original_name }}" 
                                         class="w-full h-full object-cover transition-transform duration-700 group-hover/img:scale-110"
                                    >
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover/img:opacity-100 transition-opacity flex items-center justify-center">
                                        <x-heroicon-o-magnifying-glass-plus class="w-8 h-8 text-white" />
                                    </div>
                                </a>
                            </div>
                        @else
                            <div class="w-full md:w-40 aspect-square flex items-center justify-center bg-gray-50 dark:bg-gray-900 rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-700 shadow-inner">
                                <x-heroicon-o-document-text class="w-12 h-12 text-gray-300" />
                            </div>
                        @endif

                        <div class="flex-1 w-full flex flex-col justify-between h-full min-h-[140px]">
                            <div class="bg-gray-50/50 dark:bg-gray-900/30 p-4 rounded-xl border border-gray-100 dark:border-gray-800 backdrop-blur-sm">
                                <p class="text-sm font-semibold text-gray-600 dark:text-gray-300 break-all leading-relaxed">
                                    {{ $item->original_name ?: basename($item->file_path) }}
                                </p>
                                <div class="flex items-center gap-2 mt-2">
                                    <span class="text-[9px] px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-gray-500 uppercase font-bold tracking-tighter">
                                        {{ $extension }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 uppercase">
                                        FILE DOCUMENT
                                    </span>
                                </div>
                            </div>

                            <div class="flex gap-3 mt-4">
                                 <a href="{{ asset('storage/' . $item->file_path) }}" 
                                   target="_blank" 
                                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 text-xs font-bold rounded-xl shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-blue-300 transition-all active:scale-95"
                                >
                                    <x-heroicon-m-eye class="w-3.5 h-3.5 text-blue-500" />
                                    {{ __('common.action.view') }}
                                </a>
                                
                                <a href="{{ asset('storage/' . $item->file_path) }}" 
                                   download="{{ $item->original_name }}"
                                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-xs font-bold rounded-xl shadow-md hover:bg-blue-700 hover:shadow-lg transition-all active:scale-95 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                >
                                    <x-heroicon-m-arrow-down-tray class="w-3.5 h-3.5" />
                                    {{ __('common.action.download') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                <div class="relative mb-4">
                    <x-heroicon-o-document-magnifying-glass class="w-20 h-20 opacity-10" />
                    <div class="absolute inset-0 animate-ping bg-blue-400/10 rounded-full"></div>
                </div>
                <p class="text-sm italic font-medium">{{ __('common.error.data_not_found') }}</p>
            </div>
        @endforelse
    </div>
</div>

<style>
    @keyframes bounce-subtle {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-2px); }
    }
    .animate-bounce-subtle {
        animation: bounce-subtle 2s infinite ease-in-out;
    }
</style>