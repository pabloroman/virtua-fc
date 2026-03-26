@php
/** @var App\Models\Game $game */
/** @var App\Models\GameFinances $finances */
/** @var int $availableSurplus */
/** @var array $tiers */
/** @var array $tierThresholds */
/** @var string|null $seasonGoal */
/** @var string|null $seasonGoalLabel */
/** @var int|null $seasonGoalTarget */
/** @var string $reputationLevel */
/** @var array $squadSnapshot */
/** @var array|null $offseasonRecap */
@endphp

<x-app-layout :hide-footer="true">
    <div class="min-h-screen py-6 md:py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- 1. Season Hero Banner --}}
            <div class="text-center mb-8 md:mb-10">
                <div class="inline-block drop-shadow-lg mb-4">
                    <x-team-crest :team="$game->team" class="w-20 h-20 md:w-28 md:h-28 mx-auto" />
                </div>
                <h1 class="font-heading text-3xl md:text-5xl font-bold text-text-primary mb-1">{{ __('game.season_n', ['season' => $game->formatted_season]) }}</h1>
                <p class="text-lg text-text-secondary">{{ $game->team->name }}</p>
            </div>

            {{-- Flash Messages --}}
            <x-flash-message type="error" :message="session('error')" class="mb-6" />

            {{-- 2. Off-Season Recap (Season 2+ only) --}}
            @if($offseasonRecap && ($offseasonRecap['departures'] || $offseasonRecap['arrivals'] || $offseasonRecap['reputation_changed']))
            <x-section-card :title="__('game.offseason_recap')" class="mb-6">
                <div class="p-5 md:p-6" x-data="{ showAllDep: false, showAllArr: false }">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-0 md:gap-0 divide-y md:divide-y-0 md:divide-x divide-border-default">
                        {{-- Departures --}}
                        <div class="pb-4 md:pb-0 md:pr-5">
                            <h3 class="text-sm font-semibold text-accent-red mb-3 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                {{ __('game.departures') }}
                                @if(count($offseasonRecap['departures']) > 0)
                                    <span class="text-xs font-normal text-accent-red/70">({{ count($offseasonRecap['departures']) }})</span>
                                @endif
                            </h3>
                            @if(empty($offseasonRecap['departures']))
                                <p class="text-sm text-text-muted italic">{{ __('game.no_departures') }}</p>
                            @else
                                <div class="space-y-1.5">
                                    @foreach($offseasonRecap['departures'] as $i => $player)
                                        <div x-show="{{ $i < 3 }} || showAllDep">
                                            <div class="flex items-center gap-2">
                                                <x-position-badge :position="$player['position']" size="sm" />
                                                <span class="text-sm text-text-body truncate">{{ $player['name'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if(count($offseasonRecap['departures']) > 3)
                                    <x-ghost-button color="red" size="xs" @click="showAllDep = !showAllDep" class="mt-2">
                                        <span x-show="!showAllDep">{{ __('game.show_all') }} ({{ count($offseasonRecap['departures']) }})</span>
                                        <span x-show="showAllDep" x-cloak>{{ __('game.show_less') }}</span>
                                    </x-ghost-button>
                                @endif
                            @endif
                        </div>

                        {{-- Arrivals --}}
                        <div class="pt-4 md:pt-0 md:pl-5">
                            <h3 class="text-sm font-semibold text-accent-green mb-3 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                </svg>
                                {{ __('game.arrivals') }}
                                @if(count($offseasonRecap['arrivals']) > 0)
                                    <span class="text-xs font-normal text-accent-green/70">({{ count($offseasonRecap['arrivals']) }})</span>
                                @endif
                            </h3>
                            @if(empty($offseasonRecap['arrivals']))
                                <p class="text-sm text-text-muted italic">{{ __('game.no_arrivals') }}</p>
                            @else
                                <div class="space-y-1.5">
                                    @foreach($offseasonRecap['arrivals'] as $i => $player)
                                        <div x-show="{{ $i < 3 }} || showAllArr">
                                            <div class="flex items-center gap-2">
                                                <x-position-badge :position="$player['position']" size="sm" />
                                                <span class="text-sm text-text-body truncate">{{ $player['name'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if(count($offseasonRecap['arrivals']) > 3)
                                    <x-ghost-button color="green" size="xs" @click="showAllArr = !showAllArr" class="mt-2">
                                        <span x-show="!showAllArr">{{ __('game.show_all') }} ({{ count($offseasonRecap['arrivals']) }})</span>
                                        <span x-show="showAllArr" x-cloak>{{ __('game.show_less') }}</span>
                                    </x-ghost-button>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- Reputation Change --}}
                    @if($offseasonRecap['reputation_changed'])
                    <div class="mt-4 pt-4 border-t border-border-default flex items-center gap-2 text-sm text-text-secondary">
                        <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                        <span>{{ __('game.reputation_changed') }}:</span>
                        <span class="font-semibold text-text-primary">{{ __('finances.reputation.' . $offseasonRecap['previous_reputation']) }}</span>
                        <svg class="w-3.5 h-3.5 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                        <span class="font-semibold text-text-primary">{{ __('finances.reputation.' . $offseasonRecap['current_reputation']) }}</span>
                    </div>
                    @endif
                </div>
            </x-section-card>
            @endif

            {{-- 3. Season Objective --}}
            <div class="border-l-4 border-l-accent-gold bg-accent-gold/10 rounded-r-lg px-5 py-4 mb-6">
                <div class="flex items-center gap-2 mb-1">
                    <svg class="w-4 h-4 text-accent-gold shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                    <span class="font-heading text-xs font-semibold text-accent-gold uppercase tracking-widest">{{ __('game.season_objective') }}</span>
                </div>
                <div class="font-heading text-lg md:text-xl font-bold text-text-primary">{{ __($seasonGoalLabel ?? 'game.goal_top_half') }}</div>
                <div class="text-xs text-accent-gold/80 mt-0.5">{{ __('game.board_expects_position', ['position' => $seasonGoalTarget ?? 10]) }}</div>
            </div>

            {{-- Club context (inline, no card wrapper) --}}
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-text-secondary mb-8">
                <div class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                    <span x-data x-tooltip.raw="{{ __('game.reputation_help') }}" class="cursor-help">{{ __('game.club_reputation') }}: <span class="font-semibold text-text-primary">{{ __('finances.reputation.' . $reputationLevel) }}</span></span>
                </div>
                <div class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <span>{{ $game->team->stadium_name ?? '—' }}@if($game->team->stadium_seats) · {{ __('game.seats', ['count' => number_format($game->team->stadium_seats)]) }}@endif</span>
                </div>
            </div>

            {{-- 4. Squad Snapshot --}}
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-heading text-sm font-semibold text-text-secondary uppercase tracking-widest">{{ __('game.your_squad') }}</h2>
                    <span class="text-xs text-text-muted">{{ __('game.players_count', ['count' => $squadSnapshot['total_players']]) }}</span>
                </div>

                {{-- Position Coverage --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    @foreach($squadSnapshot['position_coverage'] as $group => $data)
                        @php
                            $statusColors = match($data['status']) {
                                'adequate' => 'border-accent-green/20 bg-accent-green/10',
                                'thin' => 'border-accent-gold/20 bg-accent-gold/10',
                                'critical' => 'border-accent-red/20 bg-accent-red/10',
                                default => 'border-border-default bg-surface-700/50',
                            };
                            $countColor = match($data['status']) {
                                'adequate' => 'text-accent-green',
                                'thin' => 'text-accent-gold',
                                'critical' => 'text-accent-red',
                                default => 'text-text-body',
                            };
                        @endphp
                        <div class="border rounded-lg p-3 {{ $statusColors }}">
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <x-position-badge :group="$group" size="sm" />
                                <span class="text-xs font-medium text-text-body">{{ __('squad.' . strtolower($group) . 's') }}</span>
                            </div>
                            <div class="flex items-baseline justify-between">
                                <span class="font-heading text-lg font-bold {{ $countColor }}">{{ $data['count'] }}</span>
                                <span class="text-xs text-text-muted">{{ $data['avg_ability'] }} OVR</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Key Stats --}}
                <div class="grid grid-cols-4 gap-3 mb-4">
                    <div class="text-center">
                        <div class="font-heading text-xl font-bold text-text-primary">{{ $squadSnapshot['avg_overall'] }}</div>
                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('game.avg_overall') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="font-heading text-xl font-bold text-text-primary">{{ number_format($squadSnapshot['avg_age'], 1) }}</div>
                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('game.avg_age') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="font-heading text-xl font-bold text-text-primary">{{ $squadSnapshot['total_players'] }}</div>
                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('game.squad_size') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="font-heading text-xl font-bold text-text-primary">{{ \App\Support\Money::format($squadSnapshot['total_wages']) }}</div>
                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('game.annual_wages') }}</div>
                    </div>
                </div>

                {{-- Areas of Concern --}}
                @if(!empty($squadSnapshot['concerns']))
                <div class="border-l-4 border-l-accent-gold bg-accent-gold/10 rounded-r-lg px-4 py-3">
                    <h4 class="font-heading text-xs font-semibold text-accent-gold uppercase tracking-widest mb-1.5 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        {{ __('game.areas_of_concern') }}
                    </h4>
                    <ul class="space-y-0.5">
                        @foreach($squadSnapshot['concerns'] as $concern)
                        <li class="text-sm text-text-body flex items-start gap-1.5">
                            <span class="text-accent-gold/60 mt-0.5 shrink-0">&bull;</span>
                            {{ $concern }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>

            {{-- Divider --}}
            <div class="border-t border-border-default mb-8"></div>

            {{-- 5. Budget Allocation --}}
            <div class="mb-20">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-2 mb-5">
                    <div>
                        <h2 class="font-heading text-sm font-semibold text-text-secondary uppercase tracking-widest">{{ __('finances.season_budget', ['season' => $game->formatted_season]) }}</h2>
                        <p class="text-sm text-text-muted mt-0.5">{{ __('game.allocate_budget_hint') }}</p>
                    </div>
                    <div class="md:text-right">
                        <div class="font-heading text-2xl font-bold text-text-primary">{{ \App\Support\Money::format($availableSurplus) }}</div>
                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('game.available') }}</div>
                    </div>
                </div>

                <x-budget-allocation
                    :available-surplus="$availableSurplus"
                    :tiers="$tiers"
                    :min-tiers="$minTiers"
                    :tier-thresholds="$tierThresholds"
                    :form-action="route('game.new-season.complete', $game->id)"
                    :submit-label="__('game.begin_season')"
                />
            </div>

        </div>
    </div>
</x-app-layout>
