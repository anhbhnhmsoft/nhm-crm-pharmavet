@php
use App\Common\Constants\Marketing\IntegrationStatus;

$pagesCount = 0;
$pendingPagesCount = $pendingPagesCount ?? 0;
$lastSync = null;
$recordId = is_object($record ?? null) ? $record->id : 'temp';
$statusValue = $status ?? null;
$statusBadgeClass = 'bg-gray-100 text-gray-700';
$statusLabel = __('filament.integration.status.pending');

if (isset($record) && is_object($record) && $record !== 'temp') {
    try {
        $pagesCount = $record->approvedFacebookPages()->count();
        $pendingPagesCount = $record->pendingFacebookPages()->count();
    } catch (\Throwable $e) {
        $pagesCount = 0;
        $pendingPagesCount = 0;
    }

    if ($record->last_sync_at) {
        $lastSync = method_exists($record->last_sync_at, 'diffForHumans')
            ? $record->last_sync_at->diffForHumans()
            : (string) $record->last_sync_at;
    }
}

if ((int) $statusValue === IntegrationStatus::CONNECTED->value) {
    $statusBadgeClass = 'bg-emerald-100 text-emerald-700';
    $statusLabel = __('filament.integration.status.connected');
} elseif ((int) $statusValue === IntegrationStatus::ERROR->value) {
    $statusBadgeClass = 'bg-rose-100 text-rose-700';
    $statusLabel = __('filament.integration.status.error');
} elseif ((int) $statusValue === IntegrationStatus::PENDING->value) {
    $statusBadgeClass = 'bg-amber-100 text-amber-700';
}
@endphp

@vite(['resources/css/app.css','resources/js/app.js'])

<div
    wire:ignore
    x-data="{
    recordId: @js($recordId),
    pagesCount: @js($pagesCount),
    pendingPagesCount: @js($pendingPagesCount),
    lastSync: @js($lastSync),
    status: @js($status ?? null),
    statusMessage: @js($statusMessage ?? null),
    connecting: false,
    syncing: false,
    apiToken: @js($apiToken ?? null),
    facebookAppId: @js($facebookAppId ?? null),
    oauthWindow: null,
    checkInterval: null,

    init() {
        this.initFacebookSdk();
        window.addEventListener('message', (event) => this.handleOAuthMessage(event));
    },

    initFacebookSdk() {
        if (!this.facebookAppId || window.FB) {
            return;
        }

        window.fbAsyncInit = () => {
            window.FB.init({
                appId: this.facebookAppId,
                cookie: true,
                xfbml: false,
                version: 'v25.0',
            });
        };

        const script = document.createElement('script');
        script.async = true;
        script.defer = true;
        script.crossOrigin = 'anonymous';
        script.src = 'https://connect.facebook.net/vi_VN/sdk.js';
        document.head.appendChild(script);
    },

    connectFacebook() {
        if (!this.apiToken || !window.FB || !this.facebookAppId) {
            this.openFacebookPopup();
            return;
        }

        this.connecting = true;

        window.FB.login(async (response) => {
            const accessToken = response?.authResponse?.accessToken;

            if (!accessToken) {
                this.connecting = false;
                this.notify('warning', '{{ __('filament.integration.notifications.cancelled.title') }}');
                return;
            }

            try {
                const apiResponse = await fetch('/api/v1/facebook/connect', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${this.apiToken}`,
                    },
                    body: JSON.stringify({
                        userAccessToken: accessToken,
                    }),
                });

                const result = await apiResponse.json();

                if (!apiResponse.ok) {
                    throw new Error(result.message || '{{ __('filament.integration.notifications.error.body') }}');
                }

                this.pendingPagesCount = result.data?.count ?? 0;
                this.pagesCount = 0;
                this.status = @js(IntegrationStatus::PENDING->value);
                this.statusMessage = result.message;
                this.notify('success', '{{ __('filament.integration.notifications.pending.title') }}', result.message);
                setTimeout(() => window.location.reload(), 1000);
            } catch (error) {
                this.status = @js(IntegrationStatus::ERROR->value);
                this.statusMessage = error.message;
                this.notify('danger', '{{ __('filament.integration.notifications.error.title') }}', error.message);
            } finally {
                this.connecting = false;
            }
        }, {
            scope: 'pages_show_list,pages_read_engagement,pages_manage_metadata,leads_retrieval',
            return_scopes: true,
        });
    },

    openFacebookPopup() {
        this.connecting = true;
        const width = 600;
        const height = 700;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);
        const url = `/integration/facebook/${this.recordId}/redirect?popup=1`;

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

        this.notify('success', '{{ __('filament.integration.notifications.pending.title') }}', '{{ __('filament.integration.notifications.pending.body') }}');

        if (data.pagesCount !== undefined) this.pendingPagesCount = data.pagesCount;
        if (data.lastSync !== undefined) this.lastSync = data.lastSync;
        this.pagesCount = 0;
        this.status = @js(IntegrationStatus::PENDING->value);
        this.statusMessage = '{{ __('messages.meta_business.pending_approval') }}';

        setTimeout(() => window.location.reload(), 1000);
    },

    handleError(message) {
        this.connecting = false;
        this.cleanup();

        this.notify('danger', '{{ __('filament.integration.notifications.error.title') }}', message || '{{ __('filament.integration.notifications.error.body') }}');
        this.status = @js(IntegrationStatus::ERROR->value);
        this.statusMessage = message || '{{ __('filament.integration.notifications.error.body') }}';
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
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\\'csrf-token\\']').content,
                },
            });

            const result = await response.json();

            if (result.success) {
                this.pendingPagesCount = result.count ?? 0;
                this.pagesCount = 0;
                this.status = @js(IntegrationStatus::PENDING->value);
                this.statusMessage = result.message;
                this.notify('success', '{{ __('filament.integration.notifications.pending.title') }}', result.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            this.status = @js(IntegrationStatus::ERROR->value);
            this.statusMessage = error.message;
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
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\\'csrf-token\\']').content,
                },
            });

            const result = await response.json();

            if (result.success) {
                this.status = @js(IntegrationStatus::PENDING->value);
                this.statusMessage = null;
                this.pagesCount = 0;
                this.pendingPagesCount = 0;
                this.notify('success', '{{ __('filament.integration.notifications.disconnected.title') }}');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            this.status = @js(IntegrationStatus::ERROR->value);
            this.statusMessage = error.message;
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
}"
    class="space-y-4"
>
    <div class="rounded-md border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/20">
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-600 dark:text-gray-300">{{ __('filament.integration.fields.connection_status') }}</span>
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadgeClass }}">
                {{ $statusLabel }}
            </span>
        </div>
        @if (filled($statusMessage))
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                {{ $statusMessage }}
            </p>
        @endif
    </div>

    <div class="flex items-center justify-between gap-4 rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/20">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
            </svg>
            <div class="text-sm text-gray-700 dark:text-gray-200">
                <div class="text-xs text-gray-600 dark:text-gray-400">
                    <span>{{ $pagesCount }} {{ __('filament.integration.sections.approved_pages') }}</span>
                    <span class="mx-2">·</span>
                    <span>{{ $pendingPagesCount }} {{ __('filament.integration.sections.pending_pages') }}</span>
                    <span class="mx-2">·</span>
                    <span>{{ $lastSync ? __('filament.integration.sections.last_sync') . ': ' . $lastSync : __('filament.integration.sections.never_synced') }}</span>
                </div>
            </div>
        </div>

        <div class="text-right text-xs">
            <button type="button" x-on:click="syncPages" :disabled="syncing"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <svg class="h-4 w-4" :class="{ 'animate-spin': syncing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                    </path>
                </svg>
                <span>{{ __('filament.integration.actions.sync_pages') }}</span>
            </button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <button
            type="button"
            x-on:click="connectFacebook"
            :disabled="connecting"
            class="inline-flex min-w-[220px] items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
            </svg>
            <span class="whitespace-nowrap">{{ __('filament.integration.actions.connect_facebook') }}</span>
        </button>

        <div
            class="inline-flex items-center gap-2 text-xs font-medium text-blue-700"
            x-show="connecting"
            x-cloak
        >
            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ __('filament.integration.sections.connecting') }}</span>
        </div>
    </div>

    <div class="text-xs text-gray-500 dark:text-gray-400">
        {{ $connectHint ?? __('filament.integration.sections.facebook_pending_hint') }}
    </div>
</div>
