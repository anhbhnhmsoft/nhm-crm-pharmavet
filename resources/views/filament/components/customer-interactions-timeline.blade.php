@php
    use App\Common\Constants\Interaction\InteractionType;
    use App\Common\Constants\Interaction\InteractionStatus;

    $customer = $getRecord();
    $interactions = $customer?->interactions()->with('user')->latest('interacted_at')->get() ?? collect();
@endphp
@vite(['resources/css/app.css'])

<div class="space-y-6">
    @forelse($interactions as $interaction)
    @php
        $type = InteractionType::tryFrom((int)$interaction->type);
        $icon = $type ? $type->getIcon() : InteractionType::NOTE->getIcon();
    @endphp
    <div class="flex gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="flex-shrink-0">
            <div class="w-10 h-10 rounded-full {{ $icon['bg'] }} flex items-center justify-center">
                <svg class="w-5 h-5 {{ $icon['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon['path'] }}" />
                </svg>
            </div>
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ InteractionType::getLabel((int)$interaction->type) }}
                        @if($interaction->direction)
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            ({{ $interaction->direction === 'inbound' ? __('telesale.direction.inbound') : __('telesale.direction.outbound') }})
                        </span>
                        @endif
                    </p>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $interaction->user ? $interaction->user->name : __('telesale.messages.system') }}
                        </span>
                        <span class="text-xs text-gray-400">•</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $interaction->interacted_at->diffForHumans() }}
                        </span>
                    </div>
                </div>

                @if($interaction->status)
                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full {{ InteractionStatus::getStyle((int)$interaction->status) }}">
                    {{ InteractionStatus::getLabel((int)$interaction->status) }}
                </span>
                @endif
            </div>

            @if($interaction->content)
            <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                {!! nl2br(e($interaction->content)) !!}
            </div>
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