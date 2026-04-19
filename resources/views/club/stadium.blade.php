@php
/** @var App\Models\Game $game */
/** @var array $summary */

$capacity = $summary['capacity'];
$stadiumName = $summary['stadium_name'];
$loyaltyPoints = $summary['loyalty_points'];
$baseLoyalty = $summary['base_loyalty'];
$loyaltyDirection = $summary['loyalty_direction'];
$lastHomeMatch = $summary['last_home_match'];
$finances = $summary['finances'];

$directionConfig = match ($loyaltyDirection) {
    'rising' => ['icon' => '&#9650;', 'color' => 'text-accent-green', 'label' => __('club.stadium.loyalty_rising')],
    'declining' => ['icon' => '&#9660;', 'color' => 'text-accent-red', 'label' => __('club.stadium.loyalty_declining')],
    default => ['icon' => '&#9654;', 'color' => 'text-text-secondary', 'label' => __('club.stadium.loyalty_stable')],
};

$projectedMatchday = (int) ($finances?->projected_matchday_revenue ?? 0);
$actualMatchday = (int) ($finances?->actual_matchday_revenue ?? 0);
$matchdayVariance = $actualMatchday - $projectedMatchday;
$hasActualMatchday = $actualMatchday > 0;
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

        {{-- KPI strip: capacity + fan base --}}
        <div class="flex flex-wrap gap-3 mt-6 mb-6">
            <x-summary-card
                :label="__('club.stadium.capacity')"
                :value="number_format($capacity)"
            />
            <x-summary-card :label="__('club.stadium.fan_base')">
                <div class="flex items-baseline gap-2 mt-0.5">
                    <span class="font-heading text-xl font-bold text-text-primary">{{ $loyaltyPoints }}</span>
                    <span class="text-[10px] text-text-muted">/ 100</span>
                </div>
            </x-summary-card>
            <x-summary-card :label="__('club.stadium.fan_base_trend')">
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="font-heading text-lg font-bold {{ $directionConfig['color'] }}">{!! $directionConfig['icon'] !!}</span>
                    <span class="text-xs font-semibold {{ $directionConfig['color'] }} uppercase tracking-wider">{{ $directionConfig['label'] }}</span>
                </div>
            </x-summary-card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- LEFT column (2/3): Stadium identity + last attendance --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Stadium identity card --}}
                <div class="bg-surface-800 border border-border-default rounded-xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-heading text-base font-semibold uppercase tracking-wide text-text-primary">{{ __('club.stadium.home_ground') }}</h3>
                    </div>
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

                {{-- Last home-match attendance --}}
                <div class="bg-surface-800 border border-border-default rounded-xl p-5">
                    <h3 class="font-heading text-base font-semibold uppercase tracking-wide text-text-primary mb-4">{{ __('club.stadium.last_attendance') }}</h3>

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
            </div>

            {{-- RIGHT column (1/3): Matchday revenue tracker --}}
            <div class="space-y-6">
                <div class="bg-surface-800 border border-border-default rounded-xl p-5">
                    <h3 class="font-heading text-base font-semibold uppercase tracking-wide text-text-primary mb-4">{{ __('club.stadium.matchday_revenue') }}</h3>

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

                {{-- Fan base panel --}}
                <div class="bg-surface-800 border border-border-default rounded-xl p-5">
                    <h3 class="font-heading text-base font-semibold uppercase tracking-wide text-text-primary mb-4">{{ __('club.stadium.fan_base') }}</h3>

                    <div class="space-y-3">
                        <div>
                            <div class="flex items-baseline justify-between">
                                <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.current_loyalty') }}</div>
                                <span class="font-heading text-lg font-bold text-text-primary">{{ $loyaltyPoints }}<span class="text-xs font-normal text-text-muted"> / 100</span></span>
                            </div>
                            <div class="w-full h-2 bg-surface-600 rounded-full overflow-hidden mt-1.5 relative">
                                <div class="h-full rounded-full bg-accent-blue" style="width: {{ max(0, min(100, $loyaltyPoints)) }}%"></div>
                                @if($baseLoyalty > 0)
                                    <div class="absolute top-0 bottom-0 w-px bg-text-primary/60" style="left: {{ max(0, min(100, $baseLoyalty)) }}%;"></div>
                                @endif
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-[10px] text-text-muted">{{ __('club.stadium.anchor') }}: {{ $baseLoyalty }}</span>
                                <span class="text-[10px] font-semibold {{ $directionConfig['color'] }}">{!! $directionConfig['icon'] !!} {{ $directionConfig['label'] }}</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-text-muted mt-4 leading-relaxed">{{ __('club.stadium.fan_base_help') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
