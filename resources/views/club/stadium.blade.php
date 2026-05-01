@php
/** @var App\Models\Game $game */
/** @var array $summary */

use App\Support\Money;

$capacity = $summary['capacity'];
$stadiumName = $summary['stadium_name'];
$lastHomeMatch = $summary['last_home_match'];
$finances = $summary['finances'];

$projectedMatchday = (int) ($finances?->projected_matchday_revenue ?? 0);
$actualMatchday = (int) ($finances?->actual_matchday_revenue ?? 0);
$hasActualMatchday = $actualMatchday > 0;

$seasonTickets = $summary['season_tickets'];
$canEditTickets = $seasonTickets['can_edit'];
$ticketAreas = $seasonTickets['areas'];
$baselineAreas = $seasonTickets['baseline_areas'];
$overallFill = $seasonTickets['overall_fill_rate'];
$pricing = $seasonTickets['pricing'];

// Initial Alpine seed: per-area current price + baseline. The component
// re-fetches predictions from the server so this seed only needs to cover
// the first render.
$alpinePrices = [];
$alpineBaselines = [];
$minMultiplier = $seasonTickets['min_price_multiplier'];
$maxMultiplier = $seasonTickets['max_price_multiplier'];
foreach ($ticketAreas as $i => $area) {
    $alpinePrices[$i] = (int) ($area['price_cents'] ?? $area['baseline_price_cents']);
    $alpineBaselines[$i] = (int) ($area['baseline_price_cents'] ?? $alpinePrices[$i]);
}
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">

        {{-- Club hub title + subnav --}}
        <div class="mt-6 mb-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('club.hub_title') }}</h2>
        </div>
        <x-club-section-nav :game="$game" active="stadium" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">

            {{-- LEFT column (2/3): Stadium identity + season tickets + last attendance --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Stadium identity card --}}
                <x-section-card :title="__('club.stadium.home_ground')">
                    <div class="px-5 py-4">
                        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                            <div>
                                <div class="text-[10px] text-text-muted uppercase tracking-widest mb-1">{{ __('club.stadium.stadium_name') }}</div>
                                <div class="font-heading text-2xl font-bold text-text-primary">{{ $stadiumName ?? '—' }}</div>
                            </div>
                            <div class="md:text-right">
                                <div class="text-[10px] text-text-muted uppercase tracking-widest mb-1">{{ __('club.stadium.capacity') }}</div>
                                <div class="font-heading text-2xl font-bold text-text-primary">{{ number_format($capacity) }}</div>
                            </div>
                        </div>
                    </div>
                </x-section-card>

                {{-- Season tickets — editable or locked --}}
                <x-section-card :title="__('club.stadium.season_tickets.title')">
                    @if($canEditTickets)
                        <div class="px-5 py-4"
                             x-data="seasonTicketEditor({
                                gameId: @js($game->id),
                                previewUrl: @js(route('game.club.stadium.season-tickets.preview', $game->id)),
                                prices: @js($alpinePrices),
                                baselines: @js($alpineBaselines),
                                minMultiplier: {{ $minMultiplier }},
                                maxMultiplier: {{ $maxMultiplier }},
                                initialAreas: @js($ticketAreas),
                                initialFill: {{ $overallFill }},
                                initialRevenue: @js((int) ($pricing->total_revenue ?? 0)),
                                initialSold: @js((int) ($pricing->total_sold ?? 0)),
                                initialMatchday: {{ $projectedMatchday }},
                                totalCapacity: {{ $capacity }},
                                csrf: @js(csrf_token()),
                             })">

                            <p class="text-xs text-text-muted leading-relaxed mb-4">
                                {{ __('club.stadium.season_tickets.subtitle') }}
                            </p>

                            {{-- Per-area pricing rows --}}
                            <div class="mt-6 space-y-2">
                                @foreach($ticketAreas as $i => $area)
                                    @php
                                        $baselineCents = (int) ($baselineAreas[$i]['baseline_price_cents'] ?? $area['baseline_price_cents']);
                                        $minPrice = (int) round($baselineCents * $minMultiplier);
                                        $maxPrice = (int) round($baselineCents * $maxMultiplier);
                                    @endphp
                                    <div class="px-3.5 py-2.5 bg-surface-700/50 border border-border-default rounded-lg space-y-2">
                                        <div class="flex items-baseline justify-between gap-4">
                                            <div class="min-w-0">
                                                <span class="text-xs font-semibold text-text-primary uppercase tracking-wide">{{ __('club.stadium.season_tickets.area.' . $area['slug']) }}</span>
                                                <span class="text-[11px] text-text-muted ml-1.5">{{ number_format($area['capacity']) }}</span>
                                            </div>
                                            <span class="font-heading text-2xl font-bold text-text-primary tabular-nums leading-none shrink-0">
                                                € <span x-text="Math.round(prices[{{ $i }}] / 100).toLocaleString('es-ES')"></span>
                                            </span>
                                        </div>

                                        <input type="range"
                                               min="{{ $minPrice }}"
                                               max="{{ $maxPrice }}"
                                               step="500"
                                               :value="prices[{{ $i }}]"
                                               :style="`--fill: ${sliderFill({{ $i }})}`"
                                               @input="setPriceCents({{ $i }}, $event.target.value)"
                                               class="season-ticket-slider w-full block">

                                        <div class="text-[11px] tabular-nums flex items-baseline gap-1.5">
                                            <span class="text-text-body font-semibold"><span x-text="Math.round((areas[{{ $i }}]?.fill_rate ?? 0) * 100)"></span>%</span>
                                            <span class="text-text-muted">{{ __('club.stadium.season_tickets.predicted_fill') }}</span>
                                            <span class="text-text-faint">·</span>
                                            <span class="text-text-muted"><span x-text="(areas[{{ $i }}]?.sold ?? 0).toLocaleString('es-ES')"></span> / {{ number_format($area['capacity']) }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Aggregates --}}
                            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3">
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest flex items-center gap-1.5">
                                        {{ __('club.stadium.season_tickets.predicted_fill') }}
                                        <svg x-data="" x-tooltip.raw="{{ __('club.stadium.season_tickets.predicted_fill_tooltip') }}" class="w-3.5 h-3.5 text-text-faint hover:text-text-secondary cursor-help shrink-0" fill="currentColor" viewBox="0 0 512 512"><path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg>
                                    </div>
                                    <div class="font-heading text-2xl font-bold text-text-primary">
                                        <span x-text="overallFill"></span>%
                                    </div>
                                    <div class="mt-2 h-1.5 bg-surface-900/40 rounded-full overflow-hidden">
                                        <div class="h-full bg-accent-blue rounded-full" :style="`width: ${overallFill}%`"></div>
                                    </div>
                                    <div class="text-[11px] text-text-muted mt-1">
                                        <span x-text="totalSold.toLocaleString('es-ES')"></span> / {{ number_format($capacity) }}
                                    </div>
                                </div>
                                <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3">
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.stadium_revenue.title') }}</div>
                                    <div class="font-heading text-2xl font-bold text-accent-green">
                                        <span x-text="formatRevenue(totalRevenue + projectedMatchday)"></span>
                                    </div>
                                    <div class="mt-2 space-y-1 text-[11px]">
                                        <div class="flex justify-between gap-2">
                                            <span class="text-text-muted">{{ __('club.stadium.stadium_revenue.season_tickets') }}</span>
                                            <span class="text-text-body font-semibold" x-text="formatRevenue(totalRevenue)"></span>
                                        </div>
                                        <div class="flex justify-between gap-2">
                                            <span class="text-text-muted">{{ __('club.stadium.stadium_revenue.matchday') }}</span>
                                            <span class="text-text-body font-semibold" x-text="formatRevenue(projectedMatchday)"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <p class="text-[11px] text-text-muted mt-3 leading-relaxed">{{ __('club.stadium.stadium_revenue.help') }}</p>

                            <p class="text-[11px] text-accent-gold mt-4">{{ __('club.stadium.season_tickets.deadline_notice') }}</p>

                            <form method="POST"
                                  action="{{ route('game.club.stadium.season-tickets.save', $game->id) }}"
                                  class="mt-4 flex flex-col sm:flex-row gap-3">
                                @csrf
                                @foreach($ticketAreas as $i => $area)
                                    <input type="hidden" :name="`prices[{{ $i }}]`" :value="prices[{{ $i }}]">
                                @endforeach
                                <button type="submit"
                                        class="px-4 py-2 bg-accent-blue text-white text-sm font-semibold rounded-lg hover:bg-accent-blue/90 transition-colors">
                                    {{ __('club.stadium.season_tickets.save_button') }}
                                </button>
                                <button type="button"
                                        @click="resetToDefaults()"
                                        class="px-4 py-2 bg-surface-700 text-text-body text-sm font-semibold rounded-lg hover:bg-surface-600 transition-colors">
                                    {{ __('club.stadium.season_tickets.reset_defaults') }}
                                </button>
                            </form>
                        </div>
                    @else
                        {{-- Locked: read-only schematic + numbers --}}
                        <div class="px-5 py-4">
                            <p class="text-xs text-accent-gold leading-relaxed mb-4">{{ __('club.stadium.season_tickets.locked_notice') }}</p>

                            @php
                                $lockedSeasonTicketRevenue = (int) ($pricing->total_revenue ?? 0);
                                $lockedTaquilla = $hasActualMatchday ? $actualMatchday : $projectedMatchday;
                                $lockedTotalRevenue = $lockedSeasonTicketRevenue + $lockedTaquilla;
                            @endphp
                            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3">
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.season_tickets.tickets_sold') }}</div>
                                    <div class="font-heading text-2xl font-bold text-text-primary">{{ number_format((int) ($pricing->total_sold ?? 0)) }}</div>
                                    <div class="text-[11px] text-text-muted mt-1">{{ $overallFill }}% {{ __('club.stadium.season_tickets.predicted_fill') }}</div>
                                </div>
                                <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3">
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.stadium_revenue.title') }}</div>
                                    <div class="font-heading text-2xl font-bold text-accent-green">{{ Money::format($lockedTotalRevenue) }}</div>
                                    <div class="mt-2 space-y-1 text-[11px]">
                                        <div class="flex justify-between gap-2">
                                            <span class="text-text-muted">{{ __('club.stadium.stadium_revenue.season_tickets') }}</span>
                                            <span class="text-text-body font-semibold">{{ Money::format($lockedSeasonTicketRevenue) }}</span>
                                        </div>
                                        <div class="flex justify-between gap-2">
                                            <span class="text-text-muted">{{ __('club.stadium.stadium_revenue.matchday') }}</span>
                                            <span class="text-text-body font-semibold">{{ Money::format($lockedTaquilla) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <p class="text-[11px] text-text-muted mt-3 leading-relaxed">{{ __('club.stadium.stadium_revenue.help') }}</p>
                        </div>
                    @endif
                </x-section-card>

            </div>

            {{-- RIGHT column (1/3): Last home-match attendance --}}
            <div class="space-y-6">
                <x-section-card :title="__('club.stadium.last_attendance')">
                    <div class="px-5 py-4">
                        @if($lastHomeMatch)
                            @php
                                /** @var App\Models\GameMatch $lastMatch */
                                $lastMatch = $lastHomeMatch['match'];
                                $fillRate = $lastHomeMatch['fill_rate'];
                                $fillColor = $fillRate >= 90 ? 'bg-accent-green' : ($fillRate >= 70 ? 'bg-accent-blue' : ($fillRate >= 50 ? 'bg-accent-gold' : 'bg-accent-red'));
                            @endphp
                            <div class="flex flex-col gap-4">
                                <div class="flex items-center gap-3">
                                    <x-team-crest :team="$game->team" class="w-10 h-10 shrink-0" />
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('game.vs') }}</div>
                                        <div class="flex items-center gap-2">
                                            <x-team-crest :team="$lastMatch->awayTeam" class="w-5 h-5" />
                                            <span class="text-sm font-semibold text-text-primary truncate">{{ $lastMatch->awayTeam->name }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __($lastMatch->competition->name ?? '') }}</div>
                                        <div class="text-xs text-text-body">{{ $lastMatch->scheduled_date->format('d M Y') }}</div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex items-baseline justify-between gap-3 mb-2">
                                        <span class="font-heading text-3xl font-bold text-text-primary">{{ number_format($lastHomeMatch['attendance']) }}</span>
                                        <span class="text-sm text-text-muted">/ {{ number_format($lastHomeMatch['capacity_at_match']) }}</span>
                                    </div>
                                    <div class="w-full h-2 bg-surface-600 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full {{ $fillColor }}" style="width: {{ min($fillRate, 100) }}%"></div>
                                    </div>
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.fill_rate') }}</span>
                                        <span class="text-xs font-semibold text-text-body">{{ $fillRate }}%</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <p class="text-sm text-text-muted">{{ __('club.stadium.no_home_match_yet') }}</p>
                            </div>
                        @endif
                    </div>
                </x-section-card>
            </div>
        </div>
    </div>

    @if($canEditTickets)
        <script>
            window.seasonTicketEditor = function (config) {
                return {
                    prices: { ...config.prices },
                    baselines: { ...config.baselines },
                    areas: config.initialAreas.map(a => ({ ...a })),
                    overallFill: config.initialFill,
                    totalRevenue: config.initialRevenue,
                    totalSold: config.initialSold,
                    projectedMatchday: config.initialMatchday,
                    totalCapacity: config.totalCapacity,
                    minMultiplier: config.minMultiplier,
                    maxMultiplier: config.maxMultiplier,
                    pendingFetch: null,

                    formatPrice(cents) {
                        return '€ ' + new Intl.NumberFormat('es-ES').format(Math.round(cents / 100));
                    },
                    formatRevenue(cents) {
                        const euros = cents / 100;
                        if (euros >= 1_000_000) return '€ ' + (euros / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
                        if (euros >= 1_000) return '€ ' + Math.round(euros / 1_000) + 'K';
                        return '€ ' + Math.round(euros);
                    },
                    updatePrice(index, raw) {
                        const cents = Math.round(parseFloat(raw || '0') * 100);
                        this.setPriceCents(index, cents);
                    },
                    setPriceCents(index, raw) {
                        const baseline = this.baselines[index] ?? 0;
                        let cents = parseInt(raw, 10);
                        if (!Number.isFinite(cents)) cents = 0;
                        const min = Math.round(baseline * this.minMultiplier);
                        const max = Math.round(baseline * this.maxMultiplier);
                        cents = Math.max(min, Math.min(max, cents));
                        this.prices = { ...this.prices, [index]: cents };
                        this.refresh();
                    },
                    sliderFill(index) {
                        const baseline = this.baselines[index] ?? 0;
                        if (!baseline) return '0%';
                        const min = Math.round(baseline * this.minMultiplier);
                        const max = Math.round(baseline * this.maxMultiplier);
                        if (max <= min) return '0%';
                        const cents = this.prices[index] ?? min;
                        const pct = Math.max(0, Math.min(100, ((cents - min) / (max - min)) * 100));
                        return pct.toFixed(2) + '%';
                    },
                    resetToDefaults() {
                        this.prices = { ...this.baselines };
                        this.refresh();
                    },
                    async refresh() {
                        if (this.pendingFetch) clearTimeout(this.pendingFetch);
                        this.pendingFetch = setTimeout(async () => {
                            try {
                                const res = await fetch(config.previewUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': config.csrf,
                                    },
                                    body: JSON.stringify({ prices: this.prices }),
                                });
                                if (!res.ok) return;
                                const data = await res.json();
                                this.areas = data.areas ?? this.areas;
                                this.overallFill = data.overall_fill_rate ?? this.overallFill;
                                this.totalRevenue = data.total_revenue ?? this.totalRevenue;
                                this.totalSold = data.total_sold ?? this.totalSold;
                                this.projectedMatchday = data.projected_matchday_revenue ?? this.projectedMatchday;
                            } catch (e) { /* network blip — keep last preview */ }
                        }, 250);
                    },
                };
            };
        </script>
    @endif
</x-app-layout>
