@props([
    'game',
])

@php
    $freeablesUrl = route('game.wage-budget.freeables', $game->id);
    $squadUrl = route('game.squad', $game->id);
    $flashShortfall = session('wage_cap_shortfall');
@endphp

<div
    x-data="wageCapModal({{ \Illuminate\Support\Js::from($freeablesUrl) }}, {{ \Illuminate\Support\Js::from($squadUrl) }})"
    @wage-cap-blocked.window="open($event.detail)"
    x-init="@js($flashShortfall !== null) && open({ shortfall_cents: @js((int) $flashShortfall) })"
    x-show="visible"
    x-cloak
    class="fixed inset-0 z-[60] flex items-end md:items-center justify-center bg-black/60"
    @click.self="close()"
>
    <div
        class="w-full md:max-w-lg max-h-[90vh] md:rounded-xl rounded-t-xl bg-surface-800 border border-border-default shadow-2xl flex flex-col"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
    >
        <div class="flex items-start justify-between p-4 border-b border-border-default">
            <div>
                <h3 class="font-heading text-base font-bold text-text-primary uppercase tracking-wide">
                    {{ __('finances.wage_budget_free_space_title') }}
                </h3>
                <p class="text-xs text-text-muted mt-1" x-show="shortfallFormatted">
                    <span x-text="shortfallMessage()"></span>
                </p>
                <p class="text-xs text-text-muted mt-1" x-show="!shortfallFormatted">
                    {{ __('finances.wage_budget_free_space_subtitle') }}
                </p>
            </div>
            <button type="button" @click="close()" class="text-text-muted hover:text-text-primary p-1 -mr-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-3 space-y-2">
            <template x-if="loading">
                <div class="text-xs text-text-muted py-6 text-center">
                    <svg class="animate-spin h-5 w-5 inline-block" fill="none" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                    </svg>
                </div>
            </template>

            <template x-if="!loading && players.length === 0">
                <div class="text-xs text-text-muted py-6 text-center">
                    {{ __('finances.wage_budget_free_space_no_candidates') }}
                </div>
            </template>

            <template x-for="player in players" :key="player.id">
                <div class="flex items-center justify-between gap-3 p-3 bg-surface-700/60 border border-border-default rounded-lg">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-text-primary truncate" x-text="player.name"></div>
                        <div class="text-[11px] text-text-muted flex items-center gap-2">
                            <span x-text="player.position"></span>
                            <span class="text-text-faint">•</span>
                            <span class="tabular-nums" x-text="player.annual_wage_formatted + '/yr'"></span>
                        </div>
                    </div>
                    <a :href="player.list_url"
                       class="shrink-0 text-[11px] px-3 py-1.5 rounded-md bg-accent-blue/10 hover:bg-accent-blue/20 text-accent-blue font-semibold transition-colors">
                        {{ __('finances.wage_budget_free_space_action_squad') }}
                    </a>
                </div>
            </template>
        </div>

        <div class="border-t border-border-default p-3 text-right">
            <button type="button" @click="close()"
                class="px-4 py-2 text-xs font-semibold rounded-md bg-surface-700 hover:bg-surface-600 text-text-body transition-colors">
                {{ __('app.close') }}
            </button>
        </div>
    </div>
</div>

<script>
window.wageCapModal = function (url, squadUrl) {
    return {
        visible: false,
        loading: false,
        players: [],
        shortfallCents: 0,
        shortfallFormatted: '',
        squadUrl,

        async open(detail) {
            this.visible = true;
            this.loading = true;
            this.shortfallCents = detail?.shortfall_cents ?? 0;
            this.shortfallFormatted = this.formatMoney(this.shortfallCents);

            const params = new URLSearchParams();
            if (this.shortfallCents > 0) {
                params.set('shortfall', String(this.shortfallCents));
            }

            try {
                const resp = await fetch(url + (params.toString() ? '?' + params.toString() : ''), {
                    headers: { 'Accept': 'application/json' },
                });
                if (!resp.ok) {
                    this.players = [];
                    return;
                }
                const data = await resp.json();
                this.players = data.players || [];
            } catch {
                this.players = [];
            } finally {
                this.loading = false;
            }
        },

        close() {
            this.visible = false;
            this.players = [];
        },

        shortfallMessage() {
            return @js(__('finances.wage_budget_free_space_shortfall', ['amount' => ':AMOUNT:'])).replace(':AMOUNT:', this.shortfallFormatted);
        },

        formatMoney(cents) {
            const euros = Math.abs(cents) / 100;
            const sign = cents < 0 ? '-' : '';
            if (euros >= 1_000_000) {
                const v = Math.round(euros / 100_000) / 10;
                return `${sign}€ ${v % 1 === 0 ? v.toFixed(0) : v.toFixed(1)}M`;
            }
            if (euros >= 1_000) {
                return `${sign}€ ${Math.round(euros / 1_000)}K`;
            }
            return `${sign}€ ${Math.round(euros)}`;
        },
    };
};
</script>
