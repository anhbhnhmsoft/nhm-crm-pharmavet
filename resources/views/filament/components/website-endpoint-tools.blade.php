<div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4" x-data="{
    leadEndpoint: @js($leadEndpoint),
    pingEndpoint: @js($pingEndpoint),
    secret: @js($secret),
    pinging: false,
    pingResult: null,
    pingError: null,
    pingFieldErrors: {},
    async ping() {
        if (!this.pingEndpoint || !this.secret) {
            this.pingError = @js(__('filament.integration.notifications.ping_missing_config'));
            this.pingResult = null;
            this.pingFieldErrors = {};
            return;
        }

        this.pinging = true;
        this.pingError = null;
        this.pingResult = null;
        this.pingFieldErrors = {};

        const payload = {
            request_id: 'ui_ping_' + Date.now(),
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
                    [@js(config('marketing.website_v2.auth_header', 'X-Website-Token'))]: this.secret,
                },
                body: JSON.stringify(payload),
            });

            const json = await response.json();
            if (response.ok) {
                this.pingResult = json.message ?? 'OK';
            } else {
                this.pingError = json.message ?? 'Ping failed';
                this.pingFieldErrors = json.errors ?? {};
            }
        } catch (error) {
            this.pingError = error?.message ?? 'Ping failed';
        } finally {
            this.pinging = false;
        }
    },
}">
    <div class="grid gap-3 md:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('filament.integration.fields.generated_lead_endpoint') }}</p>
            <p class="mt-1 break-all text-sm text-slate-700">{{ $leadEndpoint ?: __('filament.integration.fields.endpoint_unavailable') }}</p>
        </div>

        <div class="flex items-start justify-end gap-2">
            <button
                type="button"
                class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:border-slate-400"
                x-on:click="if (leadEndpoint) navigator.clipboard.writeText(leadEndpoint)"
                x-bind:disabled="!leadEndpoint"
            >
                {{ __('filament.integration.actions.copy_endpoint') }}
            </button>

            <button
                type="button"
                class="inline-flex items-center rounded-md border border-indigo-300 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:border-indigo-400"
                x-on:click="ping()"
                x-bind:disabled="pinging || !pingEndpoint"
            >
                <span x-show="!pinging">{{ __('filament.integration.actions.test_ping') }}</span>
                <span x-show="pinging">{{ __('filament.integration.actions.testing_ping') }}</span>
            </button>
        </div>
    </div>

    <template x-if="pingResult">
        <p class="text-xs font-semibold text-emerald-600" x-text="pingResult"></p>
    </template>

    <template x-if="pingError">
        <p class="text-xs font-semibold text-red-600" x-text="pingError"></p>
    </template>

    <template x-if="Object.keys(pingFieldErrors).length > 0">
        <div class="space-y-1 rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
            <template x-for="(messages, field) in pingFieldErrors" :key="field">
                <p><span class="font-semibold" x-text="field"></span>: <span x-text="Array.isArray(messages) ? messages.join(', ') : messages"></span></p>
            </template>
        </div>
    </template>
</div>
