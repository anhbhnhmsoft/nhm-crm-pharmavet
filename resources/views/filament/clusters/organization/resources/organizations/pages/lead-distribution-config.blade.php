<x-filament-panels::page>
    @vite(['resources/css/app.css'])
    <div class="space-y-6">

        {{-- Form chính --}}
        <form wire:submit="save">
            {{ $this->form }}

            <div class="flex justify-end gap-3 mt-6">
                <x-filament::button type="button" color="gray" tag="a" :href="filament()->getUrl()">
                    {{ __('common.action.cancel') }}
                </x-filament::button>

                <x-filament::button type="submit" color="primary">
                    {{ __('common.action.save') }}
                </x-filament::button>
            </div>
        </form>
    </div>

    {{-- Loading overlay --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
