@php
    use App\Support\Telesale\CustomerInteractionTimelineBuilder;

    $customer = $getRecord();
    $timelineEntries = app(CustomerInteractionTimelineBuilder::class)->build($customer);
@endphp
@vite(['resources/css/app.css'])

<div class="space-y-6">
    @forelse($timelineEntries as $entry)
    <div class="flex gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="flex-shrink-0">
            <div class="w-10 h-10 rounded-full {{ $entry['icon']['bg'] }} flex items-center justify-center">
                <svg class="w-5 h-5 {{ $entry['icon']['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $entry['icon']['path'] }}" />
                </svg>
            </div>
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $entry['title'] }}
                        @if($entry['direction_label'])
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            ({{ $entry['direction_label'] }})
                        </span>
                        @endif
                    </p>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $entry['actor'] }}
                        </span>
                        <span class="text-xs text-gray-400">|</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $entry['occurred_at']->diffForHumans() }}
                        </span>
                    </div>
                </div>

                @if($entry['status_label'])
                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full {{ $entry['status_style'] }}">
                    {{ $entry['status_label'] }}
                </span>
                @endif
            </div>

            @if($entry['content'])
            <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                {!! nl2br(e($entry['content'])) !!}
            </div>
            @endif

            @if($entry['status_label'])
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                {{ __('telesale.form.result') }}:
                <span class="font-medium text-gray-700 dark:text-gray-200">{{ $entry['status_label'] }}</span>
            </p>
            @endif

            @if($entry['reason_label'])
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('common.table.note') }}:
                <span class="font-medium text-gray-700 dark:text-gray-200">{{ $entry['reason_label'] }}</span>
            </p>
            @endif

            @if($entry['duration'])
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('telesale.messages.duration') }}: {{ gmdate('i:s', $entry['duration']) }}
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
