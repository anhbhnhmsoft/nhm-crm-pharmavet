@php
$pagesCount = 0;
$lastSync = null;
if (isset($record) && is_object($record) && $record !== 'temp') {
try {
$pagesCount = $record
->entities()
->where('type', \App\Common\Constants\Marketing\IntegrationEntityType::PAGE_META->value)
->count();
} catch (\Throwable $e) {
$pagesCount = 0;
}
if ($record->last_sync_at) {
$lastSync = method_exists($record->last_sync_at, 'diffForHumans')
? $record->last_sync_at->diffForHumans()
: (string) $record->last_sync_at;
}
}
@endphp

@vite(['resources/css/app.css'])

<div x-data="{
    recordId: @js($record?->id ?? 'temp'),
    pagesCount: @js($pagesCount),
    lastSync: @js($lastSync),
    connecting: false,
    syncing: false,
    oauthWindow: null,
    checkInterval: null,

    init() {
        window.addEventListener('message', (event) => this.handleOAuthMessage(event));
    },

    openFacebookPopup() {
        this.connecting = true;
        const width = 600;
        const height = 700;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);
        const url = `/integration/facebook/${this.recordId}/redirect`;

        this.oauthWindow = window.open(
            url,
            'FacebookOAuth',
            `width=${width},height=${height},left=${left},top=${top},toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes`
        );

        if (!this.oauthWindow || this.oauthWindow.closed || typeof this.oauthWindow.closed === 'undefined') {
            this.connecting = false;
            this.notify('danger', '{{ __('filament.integration.notifications.popup_blocked.title') }}', '{{ __('filament.integration.notifications.popup_blocked.body') }}');
            return;
        }

        this.checkInterval = setInterval(() => {
            if (this.oauthWindow && this.oauthWindow.closed) {
                this.handlePopupClosed();
            }
        }, 1000);
    },

    handleOAuthMessage(event) {
        if (event.origin !== window.location.origin) return;
        const data = event.data;

        if (data.type === 'facebook-oauth-success') {
            this.handleSuccess(data);
        } else if (data.type === 'facebook-oauth-error') {
            this.handleError(data.message);
        }
    },

    handleSuccess(data) {
        this.connecting = false;
        this.cleanup();
        
        this.notify('success', '{{ __('filament.integration.notifications.connected.title') }}', '{{ __('filament.integration.notifications.connected.body') }}');

        if (data.pagesCount !== undefined) this.pagesCount = data.pagesCount;
        if (data.lastSync !== undefined) this.lastSync = data.lastSync;

        setTimeout(() => window.location.reload(), 1000);
    },

    handleError(message) {
        this.connecting = false;
        this.cleanup();

        this.notify('danger', '{{ __('filament.integration.notifications.error.title') }}', message || '{{ __('filament.integration.notifications.error.body') }}');

        if (this.recordId && this.recordId !== 'temp') {
            this.disconnect();
        }
    },

    handlePopupClosed() {
        if (this.connecting) {
            this.connecting = false;
            this.cleanup();
            this.notify('warning', '{{ __('filament.integration.notifications.cancelled.title') }}');
        }
    },

    cleanup() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
        this.oauthWindow = null;
    },

    async syncPages() {
        if (!this.recordId || this.recordId === 'temp') return;
        this.syncing = true;

        try {
            const response = await fetch(`/api/integrations/${this.recordId}/sync-pages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').content,
                },
            });
            const result = await response.json();

            if (result.success) {
                if (result.count !== undefined) this.pagesCount = result.count;
                this.notify('success', '{{ __('filament.integration.notifications.sync_success.title') }}', result.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            this.notify('danger', '{{ __('filament.integration.notifications.sync_error.title') }}', error.message);
        } finally {
            this.syncing = false;
        }
    },

    async disconnect() {
        if (!this.recordId || this.recordId === 'temp') return;

        try {
            const response = await fetch(`/api/integrations/${this.recordId}/disconnect`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').content,
                },
            });
            const result = await response.json();

            if (result.success) {
                this.notify('success', '{{ __('filament.integration.notifications.disconnected.title') }}');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            this.notify('danger', '{{ __('filament.integration.notifications.error.title') }}', error.message);
        }
    },

    notify(status, title, body = null) {
        window.filament?.notifications?.notify({
            title: title,
            body: body,
            status: status
        });
    }
}" class="space-y-4">

    @if ($isConnected)
    <div class="flex items-center justify-between gap-4 p-3 bg-gray-50 dark:bg-gray-900/20 rounded-md border border-gray-200 dark:border-gray-800">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
            </svg>
            <div class="text-sm">
                <div class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                    <span x-text="pagesCount + ' {{ __('filament.integration.sections.pages') }}'"></span>
                    <span class="mx-2">·</span>
                    <span x-text="lastSync ? '{{ __('filament.integration.sections.last_sync') }}: ' + lastSync : '{{ __('filament.integration.sections.never_synced') }}'"></span>
                </div>
            </div>
        </div>

        <div class="text-right text-xs">
            <button type="button" x-on:click="syncPages" :disabled="syncing"
                class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg shadow-sm focus:outline-none disabled:opacity-50">
                <svg class="w-4 h-4" :class="{ 'animate-spin': syncing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                    </path>
                </svg>
                <span>{{ __('filament.integration.actions.sync_pages') }}</span>
            </button>
        </div>
    </div>
    @endif

    @if(!$isConnected)
    <div class="flex gap-3">
        <button type="button" x-on:click="openFacebookPopup" :disabled="connecting"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all">

            <template x-if="!connecting">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                </svg>
            </template>

            <template x-if="connecting">
                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </template>

            <span x-text="connecting ? '{{ __('filament.integration.sections.connecting') }}' : '{{ __('filament.integration.actions.connect_facebook') }}'"></span>
        </button>
    </div>

    <div class="text-xs text-gray-500 dark:text-gray-400">
        {{ $connectHint ?? __('filament.integration.sections.facebook_popup_hint') }}
    </div>
    @else
    <div class="flex gap-3">
        <button type="button" x-on:click="syncPages" :disabled="syncing"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg shadow-sm focus:outline-none disabled:opacity-50">
            <svg class="w-5 h-5" :class="{ 'animate-spin': syncing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                </path>
            </svg>
            <span>{{ __('filament.integration.actions.sync_pages') }}</span>
        </button>

        <button type="button" x-on:click="disconnect"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-red-700 dark:text-red-400 bg-white dark:bg-gray-800 border border-red-300 dark:border-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <span>{{ __('filament.integration.actions.disconnect') }}</span>
        </button>
    </div>
    @endif
</div>