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
                <h1 class="text-3xl md:text-5xl font-bold text-white mb-1">{{ __('game.season_n', ['season' => $game->formatted_season]) }}</h1>
                <p class="text-lg text-slate-400">{{ $game->team->name }}</p>
            </div>

            {{-- Flash Messages --}}
            @if(session('error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                {{ session('error') }}
            </div>
            @endif

            {{-- 2. Off-Season Recap (Season 2+ only) --}}
            @if($offseasonRecap && ($offseasonRecap['departures'] || $offseasonRecap['arrivals'] || $offseasonRecap['reputation_changed']))
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 md:p-6 mb-6" x-data="{ showAllDep: false, showAllArr: false }">
                <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-4">{{ __('game.offseason_recap') }}</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Departures --}}
                    <div class="bg-red-50 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-red-800 mb-3 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            {{ __('game.departures') }}
                            @if(count($offseasonRecap['departures']) > 0)
                                <span class="text-xs font-normal text-red-600">({{ count($offseasonRecap['departures']) }})</span>
                            @endif
                        </h3>
                        @if(empty($offseasonRecap['departures']))
                            <p class="text-sm text-red-600/70 italic">{{ __('game.no_departures') }}</p>
                        @else
                            <div class="space-y-1.5">
                                @foreach($offseasonRecap['departures'] as $i => $player)
                                    <div class="{{ $i >= 3 ? '' : '' }}" x-show="{{ $i < 3 }} || showAllDep">
                                        <div class="flex items-center gap-2">
                                            <x-position-badge :position="$player['position']" size="sm" />
                                            <span class="text-sm text-red-900 truncate">{{ $player['name'] }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if(count($offseasonRecap['departures']) > 3)
                                <button type="button" @click="showAllDep = !showAllDep" class="mt-2 text-xs text-red-600 hover:text-red-800 font-medium min-h-[44px] flex items-center">
                                    <span x-show="!showAllDep">{{ __('game.show_all') }} ({{ count($offseasonRecap['departures']) }})</span>
                                    <span x-show="showAllDep" x-cloak>{{ __('game.show_less') }}</span>
                                </button>
                            @endif
                        @endif
                    </div>

                    {{-- Arrivals --}}
                    <div class="bg-green-50 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-green-800 mb-3 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                            </svg>
                            {{ __('game.arrivals') }}
                            @if(count($offseasonRecap['arrivals']) > 0)
                                <span class="text-xs font-normal text-green-600">({{ count($offseasonRecap['arrivals']) }})</span>
                            @endif
                        </h3>
                        @if(empty($offseasonRecap['arrivals']))
                            <p class="text-sm text-green-600/70 italic">{{ __('game.no_arrivals') }}</p>
                        @else
                            <div class="space-y-1.5">
                                @foreach($offseasonRecap['arrivals'] as $i => $player)
                                    <div x-show="{{ $i < 3 }} || showAllArr">
                                        <div class="flex items-center gap-2">
                                            <x-position-badge :position="$player['position']" size="sm" />
                                            <span class="text-sm text-green-900 truncate">{{ $player['name'] }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if(count($offseasonRecap['arrivals']) > 3)
                                <button type="button" @click="showAllArr = !showAllArr" class="mt-2 text-xs text-green-600 hover:text-green-800 font-medium min-h-[44px] flex items-center">
                                    <span x-show="!showAllArr">{{ __('game.show_all') }} ({{ count($offseasonRecap['arrivals']) }})</span>
                                    <span x-show="showAllArr" x-cloak>{{ __('game.show_less') }}</span>
                                </button>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Reputation Change --}}
                @if($offseasonRecap['reputation_changed'])
                <div class="mt-4 flex items-center gap-2 text-sm text-slate-600 bg-slate-50 rounded-lg px-4 py-3">
                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                    <span>{{ __('game.reputation_changed') }}:</span>
                    <span class="font-semibold text-slate-900">{{ __('finances.reputation.' . $offseasonRecap['previous_reputation']) }}</span>
                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                    <span class="font-semibold text-slate-900">{{ __('finances.reputation.' . $offseasonRecap['current_reputation']) }}</span>
                </div>
                @endif
            </div>
            @endif

            {{-- 3. Season Mission Briefing --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 md:p-6 mb-6">
                <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-5">{{ __('game.season_preview') }}</h2>

                {{-- Board Objective --}}
                <div class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 rounded-xl p-4 md:p-5 mb-5">
                    <div class="flex items-start md:items-center justify-between gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1.5">
                                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                </svg>
                                <h3 class="text-xs font-semibold text-amber-700 uppercase tracking-wide">{{ __('game.season_objective') }}</h3>
                            </div>
                            <div class="text-lg md:text-xl font-bold text-amber-950">{{ __($seasonGoalLabel ?? 'game.goal_top_half') }}</div>
                            <div class="text-xs text-amber-800 mt-0.5">{{ __('game.board_expects_position', ['position' => $seasonGoalTarget ?? 10]) }}</div>
                        </div>
                    </div>
                </div>

                {{-- Club Identity Row --}}
                <div class="flex flex-col md:flex-row gap-3 mb-5">
                    {{-- Reputation --}}
                    <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 flex-1">
                        <div class="w-8 h-8 rounded-lg bg-slate-200 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold flex items-center gap-1">
                                {{ __('game.club_reputation') }}
                                <svg x-data x-tooltip.raw="{{ __('game.reputation_help') }}" class="w-3.5 h-3.5 text-slate-400 cursor-help shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="text-sm font-semibold text-slate-900 truncate">{{ __('finances.reputation.' . $reputationLevel) }}</div>
                        </div>
                    </div>

                    {{-- Stadium --}}
                    <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 flex-1">
                        <div class="w-8 h-8 rounded-lg bg-slate-200 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold">{{ __('game.stadium') }}</div>
                            <div class="text-sm font-semibold text-slate-900 truncate">{{ $game->team->stadium_name ?? '—' }}</div>
                            @if($game->team->stadium_seats)
                            <div class="text-xs text-slate-500">{{ __('game.seats', ['count' => number_format($game->team->stadium_seats)]) }}</div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>

            {{-- 4. Squad Snapshot --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 md:p-6 mb-6">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-2">
                        <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('game.your_squad') }}</h2>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                            {{ __('game.players_count', ['count' => $squadSnapshot['total_players']]) }}
                        </span>
                    </div>
                </div>

                {{-- Position Coverage --}}
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">{{ __('game.position_coverage') }}</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
                    @foreach($squadSnapshot['position_coverage'] as $group => $data)
                        @php
                            $statusColors = match($data['status']) {
                                'adequate' => 'border-green-200 bg-green-50/50',
                                'thin' => 'border-amber-200 bg-amber-50/50',
                                'critical' => 'border-red-200 bg-red-50/50',
                                default => 'border-slate-200',
                            };
                            $countColor = match($data['status']) {
                                'adequate' => 'text-green-700',
                                'thin' => 'text-amber-700',
                                'critical' => 'text-red-700',
                                default => 'text-slate-700',
                            };
                        @endphp
                        <div class="border rounded-lg p-3 {{ $statusColors }}">
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <x-position-badge :group="$group" size="sm" />
                                <span class="text-xs font-medium text-slate-700">{{ __('squad.' . strtolower($group) . 's') }}</span>
                            </div>
                            <div class="flex items-baseline justify-between">
                                <span class="text-lg font-bold {{ $countColor }}">{{ $data['count'] }}</span>
                                <span class="text-xs text-slate-500">{{ $data['avg_ability'] }} OVR</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Key Stats --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
                    <div class="bg-slate-50 rounded-lg p-3 text-center">
                        <div class="text-xs text-slate-500 mb-1">{{ __('game.avg_overall') }}</div>
                        <div class="text-xl font-bold text-slate-900">{{ $squadSnapshot['avg_overall'] }}</div>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3 text-center">
                        <div class="text-xs text-slate-500 mb-1">{{ __('game.avg_age') }}</div>
                        <div class="text-xl font-bold text-slate-900">{{ number_format($squadSnapshot['avg_age'], 1) }}</div>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3 text-center">
                        <div class="text-xs text-slate-500 mb-1">{{ __('game.squad_size') }}</div>
                        <div class="text-xl font-bold text-slate-900">{{ $squadSnapshot['total_players'] }}</div>
                        <div class="text-[10px] text-slate-400">{{ __('game.ideal_range', ['min' => 22, 'max' => 28]) }}</div>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3 text-center">
                        <div class="text-xs text-slate-500 mb-1">{{ __('game.annual_wages') }}</div>
                        <div class="text-xl font-bold text-slate-900">{{ \App\Support\Money::format($squadSnapshot['total_wages']) }}</div>
                    </div>
                </div>

                {{-- Areas of Concern --}}
                @if(!empty($squadSnapshot['concerns']))
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <h4 class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        {{ __('game.areas_of_concern') }}
                    </h4>
                    <ul class="space-y-1">
                        @foreach($squadSnapshot['concerns'] as $concern)
                        <li class="text-sm text-amber-800 flex items-start gap-1.5">
                            <span class="text-amber-400 mt-0.5 shrink-0">&bull;</span>
                            {{ $concern }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>

            {{-- 5. Budget Allocation --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 md:p-6 mb-20">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-2 mb-2">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ __('finances.season_budget', ['season' => $game->formatted_season]) }}</h2>
                        <p class="text-sm text-slate-500">{{ __('game.allocate_budget_hint') }}</p>
                    </div>
                    <div class="md:text-right">
                        <div class="text-2xl font-bold text-slate-900">{{ \App\Support\Money::format($availableSurplus) }}</div>
                        <div class="text-xs text-slate-500">{{ __('game.available') }}</div>
                    </div>
                </div>

                <x-budget-allocation
                    :available-surplus="$availableSurplus"
                    :tiers="$tiers"
                    :tier-thresholds="$tierThresholds"
                    :form-action="route('game.onboarding.complete', $game->id)"
                    :submit-label="__('game.begin_season')"
                />
            </div>

        </div>
    </div>
</x-app-layout>
