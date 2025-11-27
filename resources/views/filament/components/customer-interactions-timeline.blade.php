@php
$customer = $getRecord();
$interactions = $customer?->interactions()->with('user')->latest('interacted_at')->get() ?? collect();
@endphp
@vite(['resources/css/app.css'])
<div class="space-y-4">
    @forelse($interactions as $interaction)
    <div class="flex gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="flex-shrink-0">
            @switch($interaction->type)
            @case('call')
            <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
            </div>
            @break
            @case('sms')
            <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
            </div>
            @break
            @case('email')
            <div class="w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center">
                <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            @break
            @case('note')
            <div class="w-10 h-10 rounded-full bg-yellow-100 dark:bg-yellow-900 flex items-center justify-center">
                <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </div>
            @break
            @default
            <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            @endswitch
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ ucfirst($interaction->type) }}
                        @if($interaction->direction)
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            ({{ $interaction->direction === 'inbound' ? __('telesale.direction.inbound') : __('telesale.direction.outbound') }})
                        </span>
                        @endif
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $interaction->user?->name ?? __('telesale.messages.system') }} • {{ $interaction->interacted_at->diffForHumans() }}
                    </p>
                </div>

                @if($interaction->status)
                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full
                            {{ $interaction->status == App\Interaction\InteractionStatus::COMPLETED->value ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                            {{ $interaction->status == App\Interaction\InteractionStatus::MISSED->value ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                            {{ $interaction->status == App\Interaction\InteractionStatus::FAILED->value ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}">
                    {{ __("telesale.interaction_status.{$interaction->status}") }}
                </span>
                @endif
            </div>

            @if($interaction->content)
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                {{ $interaction->content }}
            </p>
            @endif

            @if($interaction->duration)
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('telesale.messages.duration') }}: {{ gmdate('i:s', $interaction->duration) }}
            </p>
            @endif
        </div>
    </div>
    @empty
    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        <p class="mt-2">{{ __('telesale.messages.no_interactions') }}</p>
    </div>
    @endforelse
</div>