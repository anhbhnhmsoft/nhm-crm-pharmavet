<div class="space-y-3">
    @forelse($attachments as $item)
        <div class="rounded-md border p-3">
            <div class="text-sm font-medium">
                {{ __('accounting.fund_transaction.version') }} #{{ $item->version }}
            </div>
            <div class="text-xs text-gray-600">
                {{ __('accounting.fund_transaction.uploaded_by') }}: {{ $item->uploader?->name ?? '-' }}
                | {{ __('accounting.fund_transaction.uploaded_at') }}: {{ optional($item->uploaded_at)->format('d/m/Y H:i') }}
            </div>
            <div class="mt-2">
                @php
                    $extension = strtolower(pathinfo($item->file_path, PATHINFO_EXTENSION));
                    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                @endphp

                @if($isImage)
                    <div class="mb-2">
                        <a href="{{ asset('storage/' . $item->file_path) }}" target="_blank">
                            <img src="{{ asset('storage/' . $item->file_path) }}" 
                                 alt="{{ $item->original_name }}" 
                                 class="max-h-48 rounded-lg shadow-sm hover:opacity-90 transition-opacity border"
                            >
                        </a>
                    </div>
                @endif

                <a href="{{ asset('storage/' . $item->file_path) }}" target="_blank" class="text-primary-600 underline text-sm">
                    {{ $item->original_name ?: basename($item->file_path) }}
                </a>
            </div>
        </div>
    @empty
        <div class="text-sm text-gray-600">{{ __('common.error.data_not_found') }}</div>
    @endforelse
</div>
