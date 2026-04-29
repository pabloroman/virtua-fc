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
$matchdayVariance = $actualMatchday - $projectedMatchday;
$hasActualMatchday = $actualMatchday > 0;

$projectedSeasonTicket = (int) ($finances?->projected_season_ticket_revenue ?? 0);

$seasonTickets = $summary['season_tickets'];
$canEditTickets = $seasonTickets['can_edit'];
$ticketAreas = $seasonTickets['areas'];
$baselineAreas = $seasonTickets['baseline_areas'];
$overallFill = $seasonTickets['overall_fill_rate'];
$pricing = $seasonTickets['pricing'];
$isDefault = $pricing?->is_default ?? true;

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
                        <p class="text-xs text-text-muted mt-4 leading-relaxed">{{ __('club.stadium.capacity_help') }}</p>
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
                                totalCapacity: {{ $capacity }},
                                csrf: @js(csrf_token()),
                             })">

                            <p class="text-xs text-text-muted leading-relaxed mb-4">
                                {{ __('club.stadium.season_tickets.subtitle') }}
                                @if($isDefault)
                                    <span class="ml-1 text-accent-blue font-medium">{{ __('club.stadium.season_tickets.default_applied') }}</span>
                                @endif
                            </p>

                            <x-season-ticket-schematic :areas="$ticketAreas" alpine="$data" />

                            {{-- Per-area pricing rows --}}
                            <div class="mt-6 space-y-2">
                                @foreach($ticketAreas as $i => $area)
                                    @php
                                        $minPrice = (int) round(($baselineAreas[$i]['baseline_price_cents'] ?? $area['baseline_price_cents']) * $minMultiplier);
                                        $maxPrice = (int) round(($baselineAreas[$i]['baseline_price_cents'] ?? $area['baseline_price_cents']) * $maxMultiplier);
                                    @endphp
                                    <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_auto] items-center gap-3 px-3 py-2 bg-surface-700/50 border border-border-default rounded-lg">
                                        <div>
                                            <div class="text-xs font-semibold text-text-primary uppercase tracking-wide">{{ __('club.stadium.season_tickets.area.' . $area['slug']) }}</div>
                                            <div class="text-[11px] text-text-muted">
                                                {{ __('club.stadium.season_tickets.capacity') }}: {{ number_format($area['capacity']) }}
                                                <span class="ml-2">{{ __('club.stadium.season_tickets.baseline_price') }}: € {{ number_format(($baselineAreas[$i]['baseline_price_cents'] ?? $area['baseline_price_cents']) / 100, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] text-text-muted">€</span>
                                            <input type="number"
                                                   min="{{ (int) round($minPrice / 100) }}"
                                                   max="{{ (int) round($maxPrice / 100) }}"
                                                   step="5"
                                                   class="w-24 bg-surface-800 border border-border-strong rounded px-2 py-1 text-sm text-text-primary focus:border-accent-blue focus:ring-0"
                                                   :value="Math.round(prices[{{ $i }}] / 100)"
                                                   @input.debounce.300ms="updatePrice({{ $i }}, $event.target.value)">
                                        </div>
                                        <div class="text-right text-[11px] min-w-[120px]">
                                            <div class="text-text-secondary">
                                                <span x-text="(areas[{{ $i }}]?.sold ?? 0).toLocaleString('es-ES')"></span> /
                                                {{ number_format($area['capacity']) }}
                                            </div>
                                            <div class="text-text-muted">
                                                <span x-text="Math.round((areas[{{ $i }}]?.fill_rate ?? 0) * 100)"></span>% {{ __('club.stadium.season_tickets.predicted_fill') }}
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Aggregates --}}
                            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3">
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.season_tickets.predicted_fill') }}</div>
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
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.season_tickets.total_revenue') }}</div>
                                    <div class="font-heading text-2xl font-bold text-accent-green">
                                        <span x-text="formatRevenue(totalRevenue)"></span>
                                    </div>
                                    <div class="text-[11px] text-text-muted mt-1">{{ __('club.stadium.season_tickets.revenue_help') }}</div>
                                </div>
                            </div>

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

                            <x-season-ticket-schematic :areas="$ticketAreas" />

                            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3">
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.season_tickets.tickets_sold') }}</div>
                                    <div class="font-heading text-2xl font-bold text-text-primary">{{ number_format((int) ($pricing->total_sold ?? 0)) }}</div>
                                    <div class="text-[11px] text-text-muted mt-1">{{ $overallFill }}% {{ __('club.stadium.season_tickets.predicted_fill') }}</div>
                                </div>
                                <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3">
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.season_tickets.total_revenue') }}</div>
                                    <div class="font-heading text-2xl font-bold text-accent-green">{{ Money::format((int) ($pricing->total_revenue ?? 0)) }}</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </x-section-card>

                {{-- Last home-match attendance --}}
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

            {{-- RIGHT column (1/3): Matchday revenue + season ticket revenue --}}
            <div class="space-y-6">
                <x-section-card :title="__('club.stadium.season_tickets.revenue_card_title')">
                    <div class="px-5 py-4">
                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('finances.projected_revenue') }}</div>
                        <div class="font-heading text-xl font-bold text-accent-green">{{ Money::format($projectedSeasonTicket) }}</div>
                        <p class="text-xs text-text-muted mt-3 leading-relaxed">{{ __('club.stadium.season_tickets.revenue_card_help') }}</p>
                    </div>
                </x-section-card>

                <x-section-card :title="__('club.stadium.matchday_revenue')">
                    <div class="px-5 py-4">
                        @if($finances)
                            <div class="space-y-3">
                                <div>
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('finances.projected_revenue') }}</div>
                                    <div class="font-heading text-lg font-bold text-text-body">{{ $finances->formatted_projected_matchday_revenue }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('finances.actual_revenue') }}</div>
                                    <div class="font-heading text-lg font-bold {{ $hasActualMatchday ? 'text-text-primary' : 'text-text-muted' }}">
                                        {{ $hasActualMatchday ? $finances->formatted_actual_matchday_revenue : '—' }}
                                    </div>
                                </div>
                                @if($hasActualMatchday)
                                    <div class="pt-3 border-t border-border-default">
                                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('finances.variance') }}</div>
                                        <div class="font-heading text-lg font-bold {{ $matchdayVariance >= 0 ? 'text-accent-green' : 'text-accent-red' }}">
                                            {{ \App\Support\Money::formatSigned($matchdayVariance) }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <p class="text-xs text-text-muted mt-4 leading-relaxed">{{ __('club.stadium.matchday_revenue_help') }}</p>
                        @else
                            <p class="text-sm text-text-muted">{{ __('club.stadium.no_finances_yet') }}</p>
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
                        const baseline = this.baselines[index] ?? 0;
                        let cents = Math.round(parseFloat(raw || '0') * 100);
                        const min = Math.round(baseline * this.minMultiplier);
                        const max = Math.round(baseline * this.maxMultiplier);
                        cents = Math.max(min, Math.min(max, cents));
                        this.prices = { ...this.prices, [index]: cents };
                        this.refresh();
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
                            } catch (e) { /* network blip — keep last preview */ }
                        }, 250);
                    },
                };
            };
        </script>
    @endif
</x-app-layout>
