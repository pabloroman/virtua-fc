@php /** @var App\Models\Game $game */ @endphp
@php /** @var App\Models\GameMatch $match */ @endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Laravel') }}</title>
        <!-- Fonts (loaded via CSS @import in app.css) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-surface-900 text-text-primary">
    <div class="min-h-screen">
    <main class="text-text-body pt-0 pb-8 sm:py-8">
        <div class="max-w-4xl mx-auto px-4 pb-8"
             x-data="liveMatch({
                events: {{ Js::from($events) }},
                homeTeamId: '{{ $match->home_team_id }}',
                awayTeamId: '{{ $match->away_team_id }}',
                finalHomeScore: {{ $match->home_score }},
                finalAwayScore: {{ $match->away_score }},
                otherMatches: {{ Js::from($otherMatches) }},
                homeTeamName: '{{ $match->homeTeam->name }}',
                awayTeamName: '{{ $match->awayTeam->name }}',
                homeTeamImage: '{{ $match->homeTeam->image }}',
                awayTeamImage: '{{ $match->awayTeam->image }}',
                lineupPlayers: {{ Js::from($lineupPlayers) }},
                benchPlayers: {{ Js::from($benchPlayers) }},
                existingSubstitutions: {{ Js::from($existingSubstitutions) }},
                userTeamId: '{{ $game->team_id }}',
                substituteUrl: '{{ $substituteUrl }}',
                csrfToken: '{{ csrf_token() }}',
                maxSubstitutions: 5,
                maxWindows: 3,
                activeFormation: '{{ $userFormation }}',
                activeMentality: '{{ $userMentality }}',
                activePlayingStyle: '{{ $userPlayingStyle }}',
                activePressing: '{{ $userPressing }}',
                activeDefLine: '{{ $userDefLine }}',
                availableFormations: {{ Js::from($availableFormations) }},
                availableMentalities: {{ Js::from($availableMentalities) }},
                availablePlayingStyles: {{ Js::from($availablePlayingStyles) }},
                availablePressing: {{ Js::from($availablePressing) }},
                availableDefLine: {{ Js::from($availableDefLine) }},
                tacticsUrl: '{{ $tacticsUrl }}',
                isKnockout: {{ $isKnockout ? 'true' : 'false' }},
                extraTimeUrl: '{{ $extraTimeUrl }}',
                penaltiesUrl: '{{ $penaltiesUrl }}',
                extraTimeData: {{ Js::from($extraTimeData) }},
                twoLeggedInfo: {{ Js::from($twoLeggedInfo) }},
                isTournamentKnockout: {{ $isTournamentKnockout ? 'true' : 'false' }},
                knockoutRoundNumber: {{ $knockoutRoundNumber ?? 'null' }},
                knockoutRoundName: '{{ $knockoutRoundName ?? '' }}',
                processingStatusUrl: {!! $processingStatusUrl ? "'" . $processingStatusUrl . "'" : 'null' !!},
                homePossession: {{ $homePossession }},
                awayPossession: {{ $awayPossession }},
                translations: {
                    unsavedTacticalChanges: '{{ __('game.tactical_unsaved_changes') }}',
                    extraTime: '{{ __('game.live_extra_time') }}',
                    etHalfTime: '{{ __('game.live_et_half_time') }}',
                    penalties: '{{ __('game.live_penalties') }}',
                    penScored: '{{ __('game.live_pen_scored') }}',
                    penMissed: '{{ __('game.live_pen_missed') }}',
                    penWinner: '{{ __('game.live_pen_winner') }}',
                    tournamentChampion: '{{ __('game.tournament_champion_title') }}',
                    tournamentRunnerUp: '{{ __('game.tournament_runner_up_title') }}',
                    tournamentThird: '{{ __('game.tournament_third_place_title') }}',
                    tournamentFourth: '{{ __('game.tournament_fourth_place_title') }}',
                    tournamentEliminated: '{{ __('game.tournament_eliminated_title') }}',
                    tournamentEliminatedIn: '{{ __('game.tournament_eliminated_in', ['round' => $knockoutRoundName ?? '']) }}',
                    tournamentAdvance: '{{ __('game.tournament_you_advance') }}',
                    tournamentAdvanceTo: '{{ __('game.tournament_advance_to', ['round' => '']) }}',
                    tournamentToFinal: '{{ __('game.tournament_to_final') }}',
                    tournamentToThirdPlace: '{{ __('game.tournament_to_third_place') }}',
                    tournamentViewSummary: '{{ __('game.tournament_view_summary') }}',
                    tournamentSimulating: '{{ __('game.tournament_simulating') }}',
                    continueDashboard: '{{ __('game.live_continue_dashboard') }}',
                    processingActions: '{{ __('game.processing_actions') }}',
                },
             })"
             x-on:keydown.escape.window="if (!tacticalPanelOpen) skipToEnd()"
        >
            @php
                $accentBanner = \App\Support\CompetitionColors::banner($match->competition);
                $accent = $accentBanner['bg'] . ' ' . $accentBanner['text'];
            @endphp

            <div class="bg-surface-800 rounded-xl overflow-hidden">
                {{-- Competition & Round Info --}}
                <div class="px-4 py-2.5 text-center text-sm font-semibold rounded-t-xl {{ $accent }}">
                    <div>{{ __($match->competition->name) }} &middot; {{ $match->round_name ? __($match->round_name) : __('game.matchday_n', ['number' => $match->round_number]) }}</div>
                    @if($match->homeTeam->stadium_name)
                        <div class="text-xs font-normal opacity-80">{{ $match->homeTeam->stadium_name }}</div>
                    @endif
                </div>
                <div class="p-4 sm:p-6 md:p-8">

                    {{-- Scoreboard --}}
                    <div class="flex items-center justify-center gap-2 md:gap-6 mb-2">
                        <div class="flex items-center gap-2 md:gap-3 flex-1 justify-end">
                            <span class="text-sm md:text-xl font-semibold text-text-primary truncate">{{ $match->homeTeam->name }}</span>
                            <x-team-crest :team="$match->homeTeam" class="w-10 h-10 md:w-14 md:h-14 shrink-0" />
                        </div>

                        <div class="relative px-2 md:px-6">
                            {{-- Score --}}
                            <div class="text-3xl whitespace-nowrap md:text-5xl font-bold text-text-primary tabular-nums transition-transform duration-200"
                                 :class="goalFlash ? 'scale-125' : 'scale-100'">
                                <span x-text="homeScore">0</span>
                                <span class="text-text-body mx-1">-</span>
                                <span x-text="awayScore">0</span>
                            </div>
                            {{-- Penalty score (shown below main score) --}}
                            <template x-if="(revealedPenaltyKicks.length > 0 || (penaltyResult && penaltyKicks.length === 0)) && (phase === 'penalties' || phase === 'full_time')">
                                <div class="text-center text-xs font-semibold text-text-muted mt-1 tabular-nums">
                                    (<span x-text="penaltyHomeScore"></span> - <span x-text="penaltyAwayScore"></span> {{ __('game.live_pen_abbr') }})
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center gap-2 md:gap-3 flex-1">
                            <x-team-crest :team="$match->awayTeam" class="w-10 h-10 md:w-14 md:h-14 shrink-0" />
                            <span class="text-sm md:text-xl font-semibold text-text-primary truncate">{{ $match->awayTeam->name }}</span>
                        </div>
                    </div>

                    {{-- Match Clock --}}
                    <div class="text-center mb-6">
                        <span class="inline-flex items-center gap-2 text-sm font-semibold rounded-full px-4 py-1"
                              :class="{
                                  'bg-surface-700 text-text-muted': phase === 'pre_match',
                                  'bg-accent-green/10 text-accent-green': phase === 'first_half' || phase === 'second_half',
                                  'bg-accent-gold/10 text-accent-gold': phase === 'half_time' || phase === 'extra_time_half_time',
                                  'bg-orange-500/10 text-orange-400': phase === 'going_to_extra_time' || phase === 'extra_time_first_half' || phase === 'extra_time_second_half',
                                  'bg-purple-500/10 text-purple-400': phase === 'penalties',
                                  'bg-surface-800 text-white': phase === 'full_time',
                              }">
                            <span class="relative flex h-2 w-2" x-show="isRunning">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                                      :class="isInExtraTime ? 'bg-orange-400' : 'bg-green-400'"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2"
                                      :class="isInExtraTime ? 'bg-orange-500' : 'bg-accent-green'"></span>
                            </span>
                            <template x-if="phase === 'pre_match'">
                                <span>{{ __('game.live_pre_match') }}</span>
                            </template>
                            <template x-if="phase === 'first_half' || phase === 'second_half'">
                                <span><span x-text="displayMinute"></span>'</span>
                            </template>
                            <template x-if="phase === 'half_time'">
                                <span>{{ __('game.live_half_time') }}</span>
                            </template>
                            <template x-if="phase === 'going_to_extra_time'">
                                <span>{{ __('game.live_extra_time') }}</span>
                            </template>
                            <template x-if="phase === 'extra_time_first_half' || phase === 'extra_time_second_half'">
                                <span>{{ __('game.live_et_abbr') }} <span x-text="displayMinute"></span>'</span>
                            </template>
                            <template x-if="phase === 'extra_time_half_time'">
                                <span>{{ __('game.live_et_half_time') }}</span>
                            </template>
                            <template x-if="phase === 'penalties'">
                                <span>{{ __('game.live_penalties') }}</span>
                            </template>
                            <template x-if="phase === 'full_time'">
                                <span>{{ __('game.live_full_time') }}</span>
                            </template>
                        </span>

                        {{-- AET indicator at full time --}}
                        <template x-if="phase === 'full_time' && hasExtraTime && !penaltyResult">
                            <div class="text-xs text-text-secondary mt-1">({{ __('game.live_aet') }})</div>
                        </template>
                    </div>

                    {{-- Possession Bar --}}
                    <div class="mb-8 mx-auto w-3/5 md:w-2/5" x-show="phase !== 'pre_match'" x-cloak>
                        <div class="flex items-center justify-between text-xs font-semibold mb-1">
                            <span class="text-text-muted tabular-nums" x-text="homePossession + '%'"></span>
                            <span class="text-text-secondary uppercase tracking-wide text-[10px]">{{ __('game.possession') }}</span>
                            <span class="text-text-muted tabular-nums" x-text="awayPossession + '%'"></span>
                        </div>
                        <div class="flex h-1 rounded-full overflow-hidden bg-surface-700">
                            <div class="bg-sky-400 transition-all duration-700 ease-out rounded-l-full"
                                 :style="'width: ' + homePossession + '%'"></div>
                            <div class="bg-accent-blue/20 transition-all duration-700 ease-out rounded-r-full"
                                 :style="'width: ' + awayPossession + '%'"></div>
                        </div>
                    </div>

                    {{-- Timeline Bar --}}
                    <div class="relative h-2 bg-surface-700 rounded-full mb-6 overflow-visible">
                        {{-- Progress --}}
                        <div class="absolute top-0 left-0 h-full rounded-full transition-all duration-300 ease-linear"
                             :class="isInExtraTime ? 'bg-orange-500' : 'bg-accent-green'"
                             :style="'width: ' + timelineProgress + '%'"></div>

                        {{-- Half-time marker --}}
                        <div class="absolute top-0 h-full w-px bg-surface-600"
                             :style="'left: ' + timelineHalfMarker + '%'"></div>

                        {{-- 90-minute marker (only during ET) --}}
                        <template x-if="totalMinutes === 120">
                            <div class="absolute top-0 h-full w-px bg-surface-600"
                                 :style="'left: ' + timelineETMarker + '%'"></div>
                        </template>

                        {{-- ET half-time marker --}}
                        <template x-if="totalMinutes === 120">
                            <div class="absolute top-0 h-full w-px bg-surface-600"
                                 :style="'left: ' + timelineETHalfMarker + '%'"></div>
                        </template>

                        {{-- Event markers --}}
                        <template x-for="marker in getTimelineMarkers()" :key="marker.minute + '-' + marker.type">
                            <div class="absolute -top-1 w-4 h-4 rounded-full border-2 border-white shadow-xs transform -translate-x-1/2 transition-all duration-300"
                                 :style="'left: ' + marker.position + '%'"
                                 :class="{
                                     'bg-accent-green': marker.type === 'goal',
                                     'bg-red-400': marker.type === 'own_goal',
                                     'bg-yellow-400': marker.type === 'yellow_card',
                                     'bg-red-600': marker.type === 'red_card',
                                     'bg-orange-400': marker.type === 'injury',
                                     'bg-accent-blue': marker.type === 'substitution',
                                 }"
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="scale-0 opacity-0"
                                 x-transition:enter-end="scale-100 opacity-100"
                            ></div>
                        </template>
                    </div>

                    {{-- Speed Controls --}}
                    <div class="flex items-center justify-center gap-2 mb-6" x-show="phase !== 'full_time' && !penaltyPickerOpen">
                        <span class="text-xs text-text-secondary mr-2">{{ __('game.live_speed') }}</span>
                        <template x-for="s in [1, 2, 4]" :key="s">
                            <x-pill-button size="xs"
                                @click="setSpeed(s)"
                                x-bind:class="speed === s
                                    ? 'bg-surface-800 text-white'
                                    : 'bg-surface-700 text-text-secondary hover:bg-surface-600'"
                                x-text="s + 'x'"
                            ></x-pill-button>
                        </template>
                        <x-pill-button size="xs"
                            @click="skipToEnd()"
                            class="bg-surface-700 text-text-secondary hover:bg-surface-600 ml-2"
                            x-bind:disabled="extraTimeLoading"
                            x-bind:class="extraTimeLoading ? 'opacity-50 cursor-not-allowed' : ''">
                            {{ __('game.live_skip') }} ▸▸
                        </x-pill-button>
                    </div>

                    {{-- Tactical Bar --}}
                    <div class="mb-4" x-show="phase !== 'full_time' && phase !== 'pre_match' && phase !== 'going_to_extra_time' && phase !== 'penalties'">
                        <div class="flex items-center justify-between px-3 py-2 bg-surface-700/50 rounded-lg">
                            {{-- Current tactical state --}}
                            <div class="flex items-center gap-2 md:gap-3 min-w-0">
                                <span class="text-xs font-bold text-text-primary tabular-nums shrink-0" x-text="activeFormation"></span>
                                <span class="text-text-body shrink-0">&middot;</span>
                                <span class="text-xs font-semibold shrink-0 truncate"
                                      :class="{
                                          'text-accent-blue': activeMentality === 'defensive',
                                          'text-text-secondary': activeMentality === 'balanced',
                                          'text-accent-red': activeMentality === 'attacking',
                                      }"
                                      x-text="mentalityLabel"></span>
                                <span class="text-text-body shrink-0">&middot;</span>
                                <span class="text-xs text-text-secondary shrink-0">
                                    {{ __('game.sub_title') }}
                                    <span x-text="substitutionsMade.length + '/' + effectiveMaxSubstitutions"></span>
                                </span>
                                <span class="text-text-body shrink-0 hidden sm:inline">&middot;</span>
                                <span class="text-xs text-text-secondary shrink-0 hidden sm:inline">
                                    {{ __('game.sub_windows') }}
                                    <span x-text="windowsUsed + '/' + effectiveMaxWindows"></span>
                                </span>
                            </div>

                            {{-- Open tactical panel --}}
                            <x-secondary-button size="xs"
                                @click="openTacticalPanel()"
                                class="gap-1.5 min-h-[44px] shrink-0"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="hidden sm:inline">{{ __('game.tactical_center') }}</span>
                            </x-secondary-button>
                        </div>

                        {{-- Made substitutions (compact, always visible outside modal) --}}
                        <template x-if="substitutionsMade.length > 0">
                            <div class="px-3 space-y-1 mt-1">
                                <template x-for="(sub, idx) in substitutionsMade" :key="idx">
                                    <div class="flex items-center gap-2 text-xs text-text-muted py-0.5">
                                        <span class="font-mono w-6 text-right" x-text="sub.minute + '\''"></span>
                                        <span class="text-red-500">&#8617;</span>
                                        <span class="truncate" x-text="sub.playerOutName"></span>
                                        <span class="text-green-500">&#8618;</span>
                                        <span class="truncate" x-text="sub.playerInName"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- Events Feed --}}
                    <div class="border-t border-border-default pt-4">
                        <div class="space-y-1 max-h-80 overflow-y-auto" id="events-feed">

                            {{-- Penalty kicks display --}}
                            <template x-if="penaltyKicks.length > 0 && (phase === 'penalties' || phase === 'full_time')">
                                <div class="mb-2">
                                    {{-- Header --}}
                                    <div class="flex items-center gap-3 py-2 px-4 rounded-t-lg bg-purple-500/10">
                                        <span class="text-sm w-6 text-center shrink-0">&#127942;</span>
                                        <div class="flex-1 text-center">
                                            <span class="text-sm font-bold text-purple-300">{{ __('game.live_penalties') }}</span>
                                            <span class="text-lg font-bold text-purple-200 ml-2 tabular-nums"
                                                  x-text="penaltyHomeScore + ' - ' + penaltyAwayScore"></span>
                                        </div>
                                    </div>
                                    {{-- Kick-by-kick rows --}}
                                    <div class="px-3 py-2 space-y-0.5"
                                         :class="penaltyWinner && phase === 'full_time' ? '' : 'rounded-b-lg'"
                                         class="bg-purple-500/10">
                                        <template x-for="(kick, idx) in revealedPenaltyKicks" :key="idx">
                                            <div class="flex items-center gap-2 py-1 text-sm"
                                                 x-transition:enter="transition ease-out duration-300"
                                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                                 x-transition:enter-end="opacity-100 translate-y-0">
                                                <span class="w-5 text-right text-xs font-mono text-purple-400 shrink-0"
                                                      x-text="kick.round"></span>
                                                <img :src="kick.side === 'home' ? homeTeamImage : awayTeamImage"
                                                     class="w-4 h-4 shrink-0 object-contain">
                                                <span class="flex-1 truncate text-sm text-text-body" x-text="kick.playerName"></span>
                                                <span class="text-xs font-bold shrink-0 px-1.5 py-0.5 rounded-sm"
                                                      :class="kick.scored ? 'bg-accent-green/10 text-accent-green' : 'bg-red-500/10 text-accent-red'"
                                                      x-text="kick.scored ? translations.penScored : translations.penMissed"></span>
                                            </div>
                                        </template>
                                    </div>
                                    {{-- Winner banner --}}
                                    <template x-if="penaltyWinner && phase === 'full_time'">
                                        <div class="flex items-center justify-center gap-2 px-4 py-2.5 bg-purple-600 rounded-b-lg"
                                             x-transition:enter="transition ease-out duration-500"
                                             x-transition:enter-start="opacity-0"
                                             x-transition:enter-end="opacity-100">
                                            <img :src="penaltyWinner.image" class="w-5 h-5 shrink-0 object-contain">
                                            <span class="text-sm font-bold text-white" x-text="penaltyWinner.name + ' ' + translations.penWinner"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- Simple penalty banner fallback (preloaded without kick data) --}}
                            <template x-if="penaltyResult && penaltyKicks.length === 0 && (phase === 'penalties' || phase === 'full_time')">
                                <div class="mb-2">
                                    <div class="flex items-center gap-3 py-3 px-4 bg-purple-500/10"
                                         :class="penaltyWinner && phase === 'full_time' ? 'rounded-t-lg' : 'rounded-lg'">
                                        <span class="text-sm w-6 text-center shrink-0">&#127942;</span>
                                        <div class="flex-1 text-center">
                                            <span class="text-sm font-bold text-purple-300">{{ __('game.live_penalties') }}</span>
                                            <span class="text-lg font-bold text-purple-200 ml-2 tabular-nums"
                                                  x-text="penaltyHomeScore + ' - ' + penaltyAwayScore"></span>
                                        </div>
                                    </div>
                                    <template x-if="penaltyWinner && phase === 'full_time'">
                                        <div class="flex items-center justify-center gap-2 px-4 py-2.5 bg-purple-600 rounded-b-lg">
                                            <img :src="penaltyWinner.image" class="w-5 h-5 shrink-0 object-contain">
                                            <span class="text-sm font-bold text-white" x-text="penaltyWinner.name + ' ' + translations.penWinner"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- ET Second half events --}}
                            <template x-for="(event, idx) in etSecondHalfEvents" :key="'etsh-' + idx">
                                <div class="flex items-center gap-3 py-2 px-3 rounded-lg transition-all duration-300"
                                     :class="isGoalEvent(event) ? 'bg-accent-green/10' : ''"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 -translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                >
                                    @include('partials.live-match.event-row')
                                </div>
                            </template>

                            {{-- ET Half-time separator --}}
                            <template x-if="showETHalfTimeSeparator">
                                <div class="flex items-center gap-3 py-2">
                                    <div class="flex-1 border-t border-accent-orange/20"></div>
                                    <span class="text-xs font-semibold text-orange-400 uppercase">{{ __('game.live_et_half_time') }}</span>
                                    <div class="flex-1 border-t border-accent-orange/20"></div>
                                </div>
                            </template>

                            {{-- ET First half events --}}
                            <template x-for="(event, idx) in etFirstHalfEvents" :key="'etfh-' + idx">
                                <div class="flex items-center gap-3 py-2 px-3 rounded-lg transition-all duration-300"
                                     :class="isGoalEvent(event) ? 'bg-accent-green/10' : ''"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 -translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                >
                                    @include('partials.live-match.event-row')
                                </div>
                            </template>

                            {{-- Extra Time separator --}}
                            <template x-if="showExtraTimeSeparator">
                                <div class="flex items-center gap-3 py-2">
                                    <div class="flex-1 border-t border-accent-orange/20"></div>
                                    <span class="text-xs font-semibold text-orange-500 uppercase">{{ __('game.live_extra_time') }}</span>
                                    <div class="flex-1 border-t border-accent-orange/20"></div>
                                </div>
                            </template>

                            {{-- Second half events (newest first) --}}
                            <template x-for="(event, idx) in secondHalfEvents" :key="'sh-' + idx">
                                <div class="flex items-center gap-3 py-2 px-3 rounded-lg transition-all duration-300"
                                     :class="isGoalEvent(event) ? 'bg-accent-green/10' : ''"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 -translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                >
                                    @include('partials.live-match.event-row')
                                </div>
                            </template>

                            {{-- Half-time separator --}}
                            <template x-if="showHalfTimeSeparator">
                                <div class="flex items-center gap-3 py-2">
                                    <div class="flex-1 border-t border-border-strong"></div>
                                    <span class="text-xs font-semibold text-text-secondary uppercase">{{ __('game.live_half_time') }}</span>
                                    <div class="flex-1 border-t border-border-strong"></div>
                                </div>
                            </template>

                            {{-- First half events (newest first) --}}
                            <template x-for="(event, idx) in firstHalfEvents" :key="'fh-' + idx">
                                <div class="flex items-center gap-3 py-2 px-3 rounded-lg transition-all duration-300"
                                     :class="isGoalEvent(event) ? 'bg-accent-green/10' : ''"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 -translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                >
                                    @include('partials.live-match.event-row')
                                </div>
                            </template>

                            {{-- Kick off message --}}
                            <template x-if="phase !== 'pre_match'">
                                <div class="flex items-center gap-3 py-2 px-3">
                                    <span class="text-xs font-mono text-text-secondary w-8 text-right shrink-0">1'</span>
                                    <span class="text-sm w-6 text-center shrink-0">&#128227;</span>
                                    <span class="w-1.5 h-6 shrink-0"></span>
                                    <span class="text-xs text-text-secondary">{{ __('game.live_kick_off') }}</span>
                                </div>
                            </template>

                            {{-- Empty state before kick off --}}
                            <template x-if="phase === 'pre_match'">
                                <div class="text-center py-8 text-text-secondary text-sm">
                                    {{ __('game.live_about_to_start') }}
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Full Time Summary --}}
                    <template x-if="phase === 'full_time'">
                        <div class="mt-6 pt-6 border-t border-border-strong">

                            {{-- ============================== --}}
                            {{-- TOURNAMENT DRAMATIC RESULTS    --}}
                            {{-- ============================== --}}

                            {{-- Champion: Gold celebration --}}
                            <template x-if="tournamentResultType === 'champion'">
                                <div class="relative -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 px-6 sm:px-8 py-10 bg-linear-to-b from-amber-400 via-yellow-500 to-amber-600 text-center overflow-hidden">
                                    {{-- Decorative stars --}}
                                    <div class="absolute inset-0 opacity-20">
                                        <div class="absolute top-4 left-8 text-4xl">&#11088;</div>
                                        <div class="absolute top-12 right-12 text-3xl">&#11088;</div>
                                        <div class="absolute bottom-8 left-16 text-2xl">&#11088;</div>
                                        <div class="absolute bottom-4 right-8 text-4xl">&#11088;</div>
                                    </div>
                                    <div class="relative z-10">
                                        <div class="text-6xl mb-3">&#127942;</div>
                                        <img :src="userTeamId === homeTeamId ? homeTeamImage : awayTeamImage"
                                             class="w-20 h-20 mx-auto mb-4 drop-shadow-lg" alt="">
                                        <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-2 drop-shadow-md"
                                            x-text="translations.tournamentChampion"></h2>
                                        <p class="text-amber-100 text-lg font-semibold"
                                           x-text="userTeamId === homeTeamId ? homeTeamName : awayTeamName"></p>
                                    </div>
                                    <div class="relative z-10 mt-8">
                                        <form method="POST" action="{{ route('game.finalize-match', $game->id) }}">
                                            @csrf
                                            <input type="hidden" name="tournament_end" value="1">
                                            <x-secondary-button type="submit"
                                                    class="px-8 py-3 text-accent-gold font-bold shadow-lg hover:bg-accent-gold/10"
                                                    x-text="translations.tournamentViewSummary"
                                                    x-bind:disabled="!processingReady">
                                            </x-secondary-button>
                                        </form>
                                    </div>
                                </div>
                            </template>

                            {{-- Runner-up: Silver/respectful --}}
                            <template x-if="tournamentResultType === 'runner_up'">
                                <div class="relative -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 px-6 sm:px-8 py-10 bg-surface-800 text-center">
                                    <div class="text-5xl mb-3">&#129352;</div>
                                    <img :src="userTeamId === homeTeamId ? homeTeamImage : awayTeamImage"
                                         class="w-16 h-16 mx-auto mb-4 opacity-90" alt="">
                                    <h2 class="text-2xl md:text-3xl font-bold text-white mb-2"
                                        x-text="translations.tournamentRunnerUp"></h2>
                                    <p class="text-text-body text-sm"
                                       x-text="userTeamId === homeTeamId ? homeTeamName : awayTeamName"></p>
                                    <div class="mt-8">
                                        <form method="POST" action="{{ route('game.finalize-match', $game->id) }}">
                                            @csrf
                                            <input type="hidden" name="tournament_end" value="1">
                                            <x-secondary-button type="submit"
                                                    class="px-8 py-3 bg-surface-800/10 text-white border-white/20 hover:bg-surface-800/20"
                                                    x-text="translations.tournamentViewSummary"
                                                    x-bind:disabled="!processingReady">
                                            </x-secondary-button>
                                        </form>
                                    </div>
                                </div>
                            </template>

                            {{-- Third place: Bronze tinted --}}
                            <template x-if="tournamentResultType === 'third'">
                                <div class="relative -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 px-6 sm:px-8 py-10 bg-linear-to-b from-orange-600 via-amber-700 to-orange-800 text-center">
                                    <div class="text-5xl mb-3">&#129353;</div>
                                    <img :src="userTeamId === homeTeamId ? homeTeamImage : awayTeamImage"
                                         class="w-16 h-16 mx-auto mb-4" alt="">
                                    <h2 class="text-2xl md:text-3xl font-bold text-white mb-2"
                                        x-text="translations.tournamentThird"></h2>
                                    <p class="text-orange-200 text-sm"
                                       x-text="userTeamId === homeTeamId ? homeTeamName : awayTeamName"></p>
                                    <div class="mt-8">
                                        <form method="POST" action="{{ route('game.finalize-match', $game->id) }}">
                                            @csrf
                                            <input type="hidden" name="tournament_end" value="1">
                                            <x-secondary-button type="submit"
                                                    class="px-8 py-3 bg-surface-800/10 text-white border-white/20 hover:bg-surface-800/20"
                                                    x-text="translations.tournamentViewSummary"
                                                    x-bind:disabled="!processingReady">
                                            </x-secondary-button>
                                        </form>
                                    </div>
                                </div>
                            </template>

                            {{-- Fourth place: Somber --}}
                            <template x-if="tournamentResultType === 'fourth'">
                                <div class="relative -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 px-6 sm:px-8 py-10 bg-surface-800 text-center">
                                    <img :src="userTeamId === homeTeamId ? homeTeamImage : awayTeamImage"
                                         class="w-14 h-14 mx-auto mb-4 opacity-70" alt="">
                                    <h2 class="text-2xl md:text-3xl font-bold text-text-body mb-2"
                                        x-text="translations.tournamentFourth"></h2>
                                    <p class="text-text-secondary text-sm"
                                       x-text="userTeamId === homeTeamId ? homeTeamName : awayTeamName"></p>
                                    <div class="mt-8">
                                        <form method="POST" action="{{ route('game.finalize-match', $game->id) }}">
                                            @csrf
                                            <input type="hidden" name="tournament_end" value="1">
                                            <x-secondary-button type="submit"
                                                    class="px-8 py-3 bg-surface-800/10 text-text-body border-white/20 hover:bg-surface-800/20"
                                                    x-text="translations.tournamentViewSummary"
                                                    x-bind:disabled="!processingReady">
                                            </x-secondary-button>
                                        </form>
                                    </div>
                                </div>
                            </template>

                            {{-- Eliminated in R32/R16/QF: Somber/dramatic --}}
                            <template x-if="tournamentResultType === 'eliminated'">
                                <div class="relative -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 px-6 sm:px-8 py-10 bg-surface-900 text-center">
                                    <img :src="userTeamId === homeTeamId ? homeTeamImage : awayTeamImage"
                                         class="w-14 h-14 mx-auto mb-4 opacity-60 grayscale" alt="">
                                    <h2 class="text-2xl md:text-3xl font-bold text-white mb-1"
                                        x-text="translations.tournamentEliminated"></h2>
                                    <p class="text-text-secondary text-sm"
                                       x-text="translations.tournamentEliminatedIn"></p>
                                    <div class="mt-8">
                                        <form method="POST" action="{{ route('game.finalize-match', $game->id) }}">
                                            @csrf
                                            <input type="hidden" name="tournament_end" value="1">
                                            <x-secondary-button type="submit"
                                                    class="px-8 py-3 bg-surface-800/10 text-text-body border-white/20 hover:bg-surface-800/20"
                                                    x-text="translations.tournamentViewSummary"
                                                    x-bind:disabled="!processingReady">
                                            </x-secondary-button>
                                        </form>
                                    </div>
                                </div>
                            </template>

                            {{-- ============================== --}}
                            {{-- NON-DECISIVE / NORMAL RESULTS  --}}
                            {{-- ============================== --}}

                            {{-- Semi-final win: "You're in the Final!" --}}
                            <template x-if="tournamentResultType === 'to_final'">
                                <div class="mb-4 p-4 bg-accent-gold/10 border border-accent-gold/20 rounded-lg text-center">
                                    <div class="text-3xl mb-1">&#127942;</div>
                                    <h3 class="text-lg font-bold text-accent-gold" x-text="translations.tournamentToFinal"></h3>
                                </div>
                            </template>

                            {{-- Semi-final loss: "Third-Place Match Awaits" --}}
                            <template x-if="tournamentResultType === 'to_third_place'">
                                <div class="mb-4 p-4 bg-surface-700/50 border border-border-strong rounded-lg text-center">
                                    <h3 class="text-base font-semibold text-text-secondary" x-text="translations.tournamentToThirdPlace"></h3>
                                </div>
                            </template>

                            {{-- R32/R16/QF win: "You Advance!" --}}
                            <template x-if="tournamentResultType === 'advance'">
                                <div class="mb-4 p-4 bg-accent-green/10 border border-accent-green/20 rounded-lg text-center">
                                    <h3 class="text-lg font-bold text-accent-green" x-text="translations.tournamentAdvance"></h3>
                                </div>
                            </template>

                            {{-- Standard full-time content (non-decisive tournaments + all non-tournament matches) --}}
                            <template x-if="!isTournamentDecisive">
                                <div>
                                    {{-- Injury details revealed at full time --}}
                                    @php
                                        $injuries = collect($events)->filter(fn($e) => $e['type'] === 'injury');
                                    @endphp
                                    @if($injuries->isNotEmpty())
                                        <div class="mb-4 p-3 bg-orange-500/10 rounded-lg">
                                            <h4 class="text-sm font-semibold text-orange-400 mb-1">{{ __('game.live_injuries_report') }}</h4>
                                            @foreach($injuries as $injury)
                                                <div class="text-xs text-orange-300">
                                                    {{ $injury['playerName'] }} &mdash;
                                                    {{ __(App\Modules\Player\Services\InjuryService::INJURY_TRANSLATION_MAP[$injury['metadata']['injury_type']] ?? 'game.live_injury') }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="text-center mb-4">
                                        <template x-if="!processingReady">
                                            <x-secondary-button type="button" disabled
                                                    class="gap-2 px-6 bg-surface-600 text-text-muted cursor-wait">
                                                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                                <span x-text="translations.processingActions"></span>
                                            </x-secondary-button>
                                        </template>
                                        <template x-if="processingReady">
                                            <form method="POST" action="{{ route('game.finalize-match', $game->id) }}">
                                                @csrf
                                                <x-primary-button class="px-6">
                                                    {{ __('game.live_continue_dashboard') }}
                                                </x-primary-button>
                                            </form>
                                        </template>
                                    </div>

                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Tactical Control Center Modal --}}
            @include('partials.live-match.tactical-panel')

            {{-- Penalty Kicker Picker Modal --}}
            @include('partials.live-match.penalty-picker')

            {{-- Other Matches Ticker --}}
            @if(count($otherMatches) > 0)
                <div class="mt-4 px-4 py-3">
                    <p class="text-text-primary/50 font-semibold text-xs uppercase mb-1.5">{{ __('game.live_other_results') }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-1 text-xs">
                        <template x-for="(m, idx) in otherMatches" :key="idx">
                            <div class="flex items-center gap-1.5 text-text-primary/40">
                                <img :src="m.homeTeamImage" class="w-4 h-4">
                                <span class="truncate max-w-24" x-text="m.homeTeam"></span>
                                <span class="font-semibold tabular-nums whitespace-nowrap"
                                      x-text="otherMatchScores[idx]?.homeScore + ' - ' + otherMatchScores[idx]?.awayScore"></span>
                                <span class="truncate max-w-24" x-text="m.awayTeam"></span>
                                <img :src="m.awayTeamImage" class="w-4 h-4">
                            </div>
                        </template>
                    </div>
                </div>
            @endif
        </div>
    </main>
    </div>
    </body>
</html>
