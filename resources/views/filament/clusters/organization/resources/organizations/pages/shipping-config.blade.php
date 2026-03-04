<x-filament-panels::page>
    @vite(['resources/css/app.css'])
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end gap-3">
            <x-filament::button type="submit" color="primary" wire:loading.attr="disabled">
                <x-filament::loading-indicator class="h-5 w-5 mr-2" wire:loading wire:target="save" />
                {{ __('filament.shipping.save') }}
            </x-filament::button>
        </div>
    </form>

    {{-- Connection Status Info --}}
    @if ($isConnected && !empty($shops))
        <div class="mt-6">
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('filament.shipping.connected_stores') }}
                </x-slot>

                <div class="space-y-2">
                    @foreach ($shops as $shop)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex-1">
                                <div class="font-medium text-sm">
                                    {{ $shop['_name'] ?? 'N/A' }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ __('filament.shipping.store_id') }}: {{ $shop['_id'] ?? 'N/A' }}
                                </div>
                                @if (isset($shop['phone']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('filament.shipping.phone') }}: {{ $shop['phone'] }}
                                    </div>
                                @endif
                                @if (isset($shop['address']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('filament.shipping.address') }}: {{ $shop['address'] }}
                                    </div>
                                @endif
                            </div>

                            @if ($data['default_store_id'] == $shop['_id'])
                                <x-filament::badge color="success">
                                    {{ __('filament.shipping.default') }}
                                </x-filament::badge>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
    @endif

    {{-- Help Section --}}
    <div class="mt-6">
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                {{ __('filament.shipping.help') }}
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <h4>{{ __('filament.shipping.how_to_get_token') }}</h4>
                <ol>
                    <li>{{ __('filament.shipping.help_step_1') }}</li>
                    <li>{{ __('filament.shipping.help_step_2') }}</li>
                    <li>{{ __('filament.shipping.help_step_3') }}</li>
                    <li>{{ __('filament.shipping.help_step_4') }}</li>
                </ol>

                <h4 class="mt-4">{{ __('filament.shipping.note') }}</h4>
                <ul>
                    <li>{{ __('filament.shipping.note_1') }}</li>
                    <li>{{ __('filament.shipping.note_2') }}</li>
                    <li>{{ __('filament.shipping.note_3') }}</li>
                </ul>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
