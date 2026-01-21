<x-filament-panels::page.simple>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <div class="flex gap-2 justify-center">
        <button wire:click="switchLanguage('vi')" type="button" @class([
            'flex items-center  text-gray-700 gap-2 px-3 py-2 rounded-lg transition-all duration-200 border',
            app()->getLocale() === 'vi'
                ? 'bg-primary-600 shadow-lg scale-105 border-blue-500'
                : 'bg-primary  border-gray-200 hover:bg-gray-50 hover:border-primary-300',
        ])
        title="{{ __('common.vietnam') }}">
            <img src="{{ asset('images/vietnam.png') }}" alt="Vietnam flag" class="w-6 h-4 rounded-sm object-cover" />
            <span class="font-medium text-sm">VI</span>
        </button>

        <button wire:click="switchLanguage('en')" type="button" @class([
            'flex items-center gap-2 text-gray-700  px-3 py-2 rounded-lg transition-all duration-200 border',
            app()->getLocale() === 'en'
                ? 'bg-primary-600 shadow-lg scale-105 border-blue-500'
                : 'bg-primary border-gray-200 hover:bg-gray-50 hover:border-primary-300',
        ]) title="{{ __('common.english') }}">
            <img src="{{ asset('images/english.png') }}" alt="English flag"
                 class="w-6 h-4 rounded-sm object-cover" />
            <span class="font-medium text-sm">EN</span>
        </button>
        <button wire:click="switchLanguage('la')" type="button" @class([
            'flex items-center gap-2 text-gray-700  px-3 py-2 rounded-lg transition-all duration-200 border',
            app()->getLocale() === 'la'
                ? 'bg-primary-600 shadow-lg scale-105 border-blue-500'
                : 'bg-primary border-gray-200 hover:bg-gray-50 hover:border-primary-300',
        ]) title="{{ __('common.english') }}">
            <img src="{{ asset('images/laos.png') }}" alt="Laos flag"
                 class="w-6 h-4 rounded-sm object-cover" />
            <span class="font-medium text-sm">LA</span>
        </button>
    </div>

    {{ \Filament\Support\Facades\FilamentView::renderHook(
          \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
          scopes: $this->getRenderHookScopes(),
      ) }}

    <form wire:submit.prevent="authenticate" class="space-y-6">
        {{ $this->form }}
        <x-filament::actions :actions="$this->getFormActions()" alignment="right" class="flex justify-center" />
    </form>

    {{ \Filament\Support\Facades\FilamentView::renderHook(
        \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
        scopes: $this->getRenderHookScopes(),
    ) }}
</x-filament-panels::page.simple>

