@php
$authHeader = config('marketing.website_v2.auth_header', 'X-Website-Token');
$leadEndpointText = $leadEndpoint ?: __('filament.integration.fields.endpoint_unavailable');
$pingEndpointText = $pingEndpoint ?: __('filament.integration.fields.endpoint_unavailable');
$headerValueText = $secret !== '' ? $authHeader.': '.$secret : $authHeader.': ***';
@endphp

@vite(['resources/css/app.css','resources/js/app.js'])

<div
    wire:ignore
    x-data="{
        isSaved: @js($isSaved ?? false),
        leadEndpoint: @js($leadEndpoint),
        pingEndpoint: @js($pingEndpoint),
        secret: @js($secret),
        authHeader: @js($authHeader),
        pinging: false,
        pingResult: null,
        pingError: null,
        pingFieldErrors: {},

        async copyToClipboard(value, fallbackMessage) {
            if (!value) {
                this.notify('warning', fallbackMessage);
                return;
            }

            try {
                await navigator.clipboard.writeText(value);
            } catch (error) {
                this.notify('danger', '{{ __('filament.integration.notifications.error.title') }}', error?.message || '{{ __('filament.integration.notifications.error.body') }}');
            }
        },

        async ping() {
            if (!this.isSaved) {
                this.pingError = @js(__('filament.integration.notifications.ping_save_required'));
                this.pingResult = null;
                this.pingFieldErrors = {};
                this.notify('warning', '{{ __('filament.integration.notifications.ping_failed') }}', this.pingError);
                return;
            }

            if (!this.pingEndpoint || !this.secret) {
                this.pingError = @js(__('filament.integration.notifications.ping_missing_config'));
                this.pingResult = null;
                this.pingFieldErrors = {};
                this.notify('warning', '{{ __('filament.integration.notifications.ping_failed') }}', this.pingError);
                return;
            }

            this.pinging = true;
            this.pingError = null;
            this.pingResult = null;
            this.pingFieldErrors = {};

            const payload = {
                request_id: `ui_ping_${Date.now()}`,
                lead: {
                    name: 'Ping Test',
                    phone: '0900000000',
                    email: 'ping@example.com',
                    source_detail: 'website_ping_test',
                },
            };

            try {
                const response = await fetch(this.pingEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        [this.authHeader]: this.secret,
                    },
                    body: JSON.stringify(payload),
                });

                const contentType = response.headers.get('content-type') || '';
                const json = contentType.includes('application/json')
                    ? await response.json()
                    : { message: await response.text() };

                if (response.ok) {
                    this.pingResult = json.message || '{{ __('filament.integration.notifications.ping_success') }}';
                    this.notify('success', '{{ __('filament.integration.notifications.ping_success') }}', this.pingResult);
                    return;
                }

                this.pingError = json.message || '{{ __('filament.integration.notifications.ping_failed') }}';
                this.pingFieldErrors = json.errors || {};
                this.notify('danger', '{{ __('filament.integration.notifications.ping_failed') }}', this.pingError);
            } catch (error) {
                this.pingError = error?.message || '{{ __('filament.integration.notifications.ping_failed') }}';
                this.notify('danger', '{{ __('filament.integration.notifications.ping_failed') }}', this.pingError);
            } finally {
                this.pinging = false;
            }
        },

        notify(status, title, body = null) {
            window.filament?.notifications?.notify({
                title: title,
                body: body,
                status: status,
            });
        }
    }"
    class="space-y-4"
>
    <div class="rounded-md border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/20">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ __('filament.integration.fields.generated_lead_endpoint') }}
                </p>
                <p class="mt-1 break-all text-xs text-gray-500 dark:text-gray-400">
                    {{ $leadEndpointText }}
                </p>
            </div>

            <button
                type="button"
                x-on:click="copyToClipboard(leadEndpoint, '{{ __('filament.integration.fields.endpoint_unavailable') }}')"
                :disabled="!leadEndpoint"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                <span>{{ __('filament.integration.actions.copy_endpoint') }}</span>
            </button>
        </div>
    </div>

    <div class="rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/20">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ __('filament.integration.fields.generated_ping_endpoint') }}
                </p>
                <p class="mt-1 break-all text-xs text-gray-500 dark:text-gray-400">
                    {{ $pingEndpointText }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    x-on:click="copyToClipboard(pingEndpoint, '{{ __('filament.integration.fields.endpoint_unavailable') }}')"
                    :disabled="!pingEndpoint"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <span>{{ __('filament.integration.actions.copy_ping_endpoint') }}</span>
                </button>

                <button
                    type="button"
                    x-on:click="ping()"
                    :disabled="pinging || !pingEndpoint || !isSaved"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg class="h-4 w-4" :class="{ 'animate-spin': pinging }" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="pinging ? '{{ __('filament.integration.actions.testing_ping') }}' : '{{ __('filament.integration.actions.test_ping') }}'">
                        {{ __('filament.integration.actions.test_ping') }}
                    </span>
                </button>
            </div>
        </div>
    </div>

    <div class="rounded-md border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/20">
        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
            {{ __('filament.integration.fields.auth_header') }}
        </p>
        <p class="mt-1 break-all text-xs text-gray-500 dark:text-gray-400">
            {{ $headerValueText }}
        </p>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            {{ __('filament.integration.fields.auth_header_helper') }}
        </p>
    </div>

    @if (!($isSaved ?? false))
        <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700">
            <p class="font-semibold">{{ __('filament.integration.notifications.ping_save_required') }}</p>
        </div>
    @endif

    <template x-if="pingResult">
        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700">
            <p class="font-semibold" x-text="pingResult"></p>
        </div>
    </template>

    <template x-if="pingError">
        <div class="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
            <p class="font-semibold" x-text="pingError"></p>
        </div>
    </template>

    <template x-if="Object.keys(pingFieldErrors).length > 0">
        <div class="space-y-1 rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
            <template x-for="(messages, field) in pingFieldErrors" :key="field">
                <p>
                    <span class="font-semibold" x-text="field"></span>:
                    <span x-text="Array.isArray(messages) ? messages.join(', ') : messages"></span>
                </p>
            </template>
        </div>
    </template>
</div>
