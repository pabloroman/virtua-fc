@php
/** @var App\Models\Game $game */
/** @var array $summary */

$capacity = $summary['capacity'];
$stadiumName = $summary['stadium_name'];
$lastHomeMatch = $summary['last_home_match'];
$finances = $summary['finances'];

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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">

            {{-- LEFT column (2/3): Stadium identity + last attendance --}}
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

            {{-- RIGHT column (1/3): Matchday revenue tracker --}}
            <div class="space-y-6">
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
</x-app-layout>
