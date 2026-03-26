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
            <div class="mt-1">
                <a href="{{ asset('storage/' . $item->file_path) }}" target="_blank" class="text-primary-600 underline">
                    {{ $item->original_name ?: basename($item->file_path) }}
                </a>
            </div>
        </div>
    @empty
        <div class="text-sm text-gray-600">{{ __('common.error.data_not_found') }}</div>
    @endforelse
</div>
