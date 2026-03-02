@php /** @var App\Models\Game $game */ @endphp
@php /** @var App\Models\GameMatch $match */ @endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=barlow-semi-condensed:400,600,800&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
    <div class="min-h-screen bg-gradient-to-bl from-slate-900 via-cyan-950 to-teal-950">
    <main class="text-slate-700 py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8"
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
                $comp = $match->competition;
                $accent = match(true) {
                    ($comp->scope ?? '') === 'continental' => 'bg-blue-600 text-blue-100',
                    ($comp->type ?? '') === 'cup' => 'bg-emerald-600 text-emerald-100',
                    default => 'bg-amber-600 text-amber-100',
                };
            @endphp

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                {{-- Competition & Round Info --}}
                <div class="px-4 py-2.5 text-center text-sm font-semibold sm:rounded-t-lg {{ $accent }}">
                    {{ __($match->competition->name) }} &middot; {{ $match->round_name ? __($match->round_name) : __('game.matchday_n', ['number' => $match->round_number]) }}
                </div>
                <div class="p-6 sm:p-8">

                    {{-- Scoreboard --}}
                    <div class="flex items-center justify-center gap-2 md:gap-6 mb-2">
                        <div class="flex items-center gap-2 md:gap-3 flex-1 justify-end">
                            <span class="text-sm md:text-xl font-semibold text-slate-900 truncate">{{ $match->homeTeam->name }}</span>
                            <x-team-crest :team="$match->homeTeam" class="w-10 h-10 md:w-14 md:h-14 shrink-0" />
                        </div>

                        <div class="relative px-2 md:px-6">
                            {{-- Score --}}
                            <div class="text-3xl whitespace-nowrap md:text-5xl font-bold text-slate-900 tabular-nums transition-transform duration-200"
                                 :class="goalFlash ? 'scale-125' : 'scale-100'">
                                <span x-text="homeScore">0</span>
                                <span class="text-slate-300 mx-1">-</span>
                                <span x-text="awayScore">0</span>
                            </div>
                            {{-- Penalty score (shown below main score) --}}
                            <template x-if="(revealedPenaltyKicks.length > 0 || (penaltyResult && penaltyKicks.length === 0)) && (phase === 'penalties' || phase === 'full_time')">
                                <div class="text-center text-xs font-semibold text-slate-500 mt-1 tabular-nums">
                                    (<span x-text="penaltyHomeScore"></span> - <span x-text="penaltyAwayScore"></span> {{ __('game.live_pen_abbr') }})
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center gap-2 md:gap-3 flex-1">
                            <x-team-crest :team="$match->awayTeam" class="w-10 h-10 md:w-14 md:h-14 shrink-0" />
                            <span class="text-sm md:text-xl font-semibold text-slate-900 truncate">{{ $match->awayTeam->name }}</span>
                        </div>
                    </div>

                    {{-- Match Clock --}}
                    <div class="text-center mb-6">
                        <span class="inline-flex items-center gap-2 text-sm font-semibold rounded-full px-4 py-1"
                              :class="{
                                  'bg-slate-100 text-slate-500': phase === 'pre_match',
                                  'bg-green-100 text-green-700': phase === 'first_half' || phase === 'second_half',
                                  'bg-amber-100 text-amber-700': phase === 'half_time' || phase === 'extra_time_half_time',
                                  'bg-orange-100 text-orange-700': phase === 'going_to_extra_time' || phase === 'extra_time_first_half' || phase === 'extra_time_second_half',
                                  'bg-purple-100 text-purple-700': phase === 'penalties',
                                  'bg-slate-800 text-white': phase === 'full_time',
                              }">
                            <span class="relative flex h-2 w-2" x-show="isRunning">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                                      :class="isInExtraTime ? 'bg-orange-400' : 'bg-green-400'"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2"
                                      :class="isInExtraTime ? 'bg-orange-500' : 'bg-green-500'"></span>
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
                            <div class="text-xs text-slate-400 mt-1">({{ __('game.live_aet') }})</div>
                        </template>
                    </div>

                    {{-- Timeline Bar --}}
                    <div class="relative h-2 bg-slate-100 rounded-full mb-6 overflow-visible">
                        {{-- Progress --}}
                        <div class="absolute top-0 left-0 h-full rounded-full transition-all duration-300 ease-linear"
                             :class="isInExtraTime ? 'bg-orange-500' : 'bg-green-500'"
                             :style="'width: ' + timelineProgress + '%'"></div>

                        {{-- Half-time marker --}}
                        <div class="absolute top-0 h-full w-px bg-slate-300"
                             :style="'left: ' + timelineHalfMarker + '%'"></div>

                        {{-- 90-minute marker (only during ET) --}}
                        <template x-if="totalMinutes === 120">
                            <div class="absolute top-0 h-full w-px bg-slate-400"
                                 :style="'left: ' + timelineETMarker + '%'"></div>
                        </template>

                        {{-- ET half-time marker --}}
                        <template x-if="totalMinutes === 120">
                            <div class="absolute top-0 h-full w-px bg-slate-300"
                                 :style="'left: ' + timelineETHalfMarker + '%'"></div>
                        </template>

                        {{-- Event markers --}}
                        <template x-for="marker in getTimelineMarkers()" :key="marker.minute + '-' + marker.type">
                            <div class="absolute -top-1 w-4 h-4 rounded-full border-2 border-white shadow-sm transform -translate-x-1/2 transition-all duration-300"
                                 :style="'left: ' + marker.position + '%'"
                                 :class="{
                                     'bg-green-500': marker.type === 'goal',
                                     'bg-red-400': marker.type === 'own_goal',
                                     'bg-yellow-400': marker.type === 'yellow_card',
                                     'bg-red-600': marker.type === 'red_card',
                                     'bg-orange-400': marker.type === 'injury',
                                     'bg-sky-500': marker.type === 'substitution',
                                 }"
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="scale-0 opacity-0"
                                 x-transition:enter-end="scale-100 opacity-100"
                            ></div>
                        </template>
                    </div>

                    {{-- Speed Controls --}}
                    <div class="flex items-center justify-center gap-2 mb-6" x-show="phase !== 'full_time' && !penaltyPickerOpen">
                        <span class="text-xs text-slate-400 mr-2">{{ __('game.live_speed') }}</span>
                        <template x-for="s in [1, 2]" :key="s">
                            <button
                                @click="setSpeed(s)"
                                class="px-3 py-1 text-xs font-semibold rounded-md transition-colors"
                                :class="speed === s
                                    ? 'bg-slate-800 text-white'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                x-text="s + 'x'"
                            ></button>
                        </template>
                        <button
                            @click="skipToEnd()"
                            class="px-3 py-1 text-xs font-semibold rounded-md bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors ml-2"
                            :disabled="extraTimeLoading"
                            :class="extraTimeLoading ? 'opacity-50 cursor-not-allowed' : ''">
                            {{ __('game.live_skip') }} ▸▸
                        </button>
                    </div>

                    {{-- Tactical Bar --}}
                    <div class="mb-4" x-show="phase !== 'full_time' && phase !== 'pre_match' && phase !== 'going_to_extra_time' && phase !== 'penalties'">
                        <div class="flex items-center justify-between px-3 py-2 bg-slate-50 rounded-lg">
                            {{-- Current tactical state --}}
                            <div class="flex items-center gap-2 md:gap-3 min-w-0">
                                <span class="text-xs font-bold text-slate-800 tabular-nums shrink-0" x-text="activeFormation"></span>
                                <span class="text-slate-300 shrink-0">&middot;</span>
                                <span class="text-xs font-semibold shrink-0 truncate"
                                      :class="{
                                          'text-blue-600': activeMentality === 'defensive',
                                          'text-slate-600': activeMentality === 'balanced',
                                          'text-red-600': activeMentality === 'attacking',
                                      }"
                                      x-text="mentalityLabel"></span>
                                <span class="text-slate-300 shrink-0">&middot;</span>
                                <span class="text-xs text-slate-400 shrink-0">
                                    {{ __('game.sub_title') }}
                                    <span x-text="substitutionsMade.length + '/' + effectiveMaxSubstitutions"></span>
                                </span>
                                <span class="text-slate-300 shrink-0 hidden sm:inline">&middot;</span>
                                <span class="text-xs text-slate-400 shrink-0 hidden sm:inline">
                                    {{ __('game.sub_windows') }}
                                    <span x-text="windowsUsed + '/' + effectiveMaxWindows"></span>
                                </span>
                            </div>

                            {{-- Open tactical panel --}}
                            <button
                                @click="openTacticalPanel()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-100 hover:border-slate-300 transition-colors min-h-[44px] shrink-0"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="hidden sm:inline">{{ __('game.tactical_center') }}</span>
                            </button>
                        </div>

                        {{-- Made substitutions (compact, always visible outside modal) --}}
                        <template x-if="substitutionsMade.length > 0">
                            <div class="px-3 space-y-1 mt-1">
                                <template x-for="(sub, idx) in substitutionsMade" :key="idx">
                                    <div class="flex items-center gap-2 text-xs text-slate-500 py-0.5">
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
                    <div class="border-t border-slate-100 pt-4">
                        <div class="space-y-1 max-h-80 overflow-y-auto" id="events-feed">

                            {{-- Penalty kicks display --}}
                            <template x-if="penaltyKicks.length > 0 && (phase === 'penalties' || phase === 'full_time')">
                                <div class="mb-2">
                                    {{-- Header --}}
                                    <div class="flex items-center gap-3 py-2 px-4 rounded-t-lg bg-purple-100">
                                        <span class="text-sm w-6 text-center shrink-0">&#127942;</span>
                                        <div class="flex-1 text-center">
                                            <span class="text-sm font-bold text-purple-800">{{ __('game.live_penalties') }}</span>
                                            <span class="text-lg font-bold text-purple-900 ml-2 tabular-nums"
                                                  x-text="penaltyHomeScore + ' - ' + penaltyAwayScore"></span>
                                        </div>
                                    </div>
                                    {{-- Kick-by-kick rows --}}
                                    <div class="px-3 py-2 space-y-0.5"
                                         :class="penaltyWinner && phase === 'full_time' ? '' : 'rounded-b-lg'"
                                         class="bg-purple-50">
                                        <template x-for="(kick, idx) in revealedPenaltyKicks" :key="idx">
                                            <div class="flex items-center gap-2 py-1 text-sm"
                                                 x-transition:enter="transition ease-out duration-300"
                                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                                 x-transition:enter-end="opacity-100 translate-y-0">
                                                <span class="w-5 text-right text-xs font-mono text-purple-400 shrink-0"
                                                      x-text="kick.round"></span>
                                                <img :src="kick.side === 'home' ? homeTeamImage : awayTeamImage"
                                                     class="w-4 h-4 shrink-0 object-contain">
                                                <span class="flex-1 truncate text-sm text-slate-700" x-text="kick.playerName"></span>
                                                <span class="text-xs font-bold shrink-0 px-1.5 py-0.5 rounded"
                                                      :class="kick.scored ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'"
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
                                    <div class="flex items-center gap-3 py-3 px-4 bg-purple-50"
                                         :class="penaltyWinner && phase === 'full_time' ? 'rounded-t-lg' : 'rounded-lg'">
                                        <span class="text-sm w-6 text-center shrink-0">&#127942;</span>
                                        <div class="flex-1 text-center">
                                            <span class="text-sm font-bold text-purple-800">{{ __('game.live_penalties') }}</span>
                                            <span class="text-lg font-bold text-purple-900 ml-2 tabular-nums"
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
                                     :class="isGoalEvent(event) ? 'bg-green-50' : ''"
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
                                    <div class="flex-1 border-t border-orange-200"></div>
                                    <span class="text-xs font-semibold text-orange-400 uppercase">{{ __('game.live_et_half_time') }}</span>
                                    <div class="flex-1 border-t border-orange-200"></div>
                                </div>
                            </template>

                            {{-- ET First half events --}}
                            <template x-for="(event, idx) in etFirstHalfEvents" :key="'etfh-' + idx">
                                <div class="flex items-center gap-3 py-2 px-3 rounded-lg transition-all duration-300"
                                     :class="isGoalEvent(event) ? 'bg-green-50' : ''"
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
                                    <div class="flex-1 border-t border-orange-200"></div>
                                    <span class="text-xs font-semibold text-orange-500 uppercase">{{ __('game.live_extra_time') }}</span>
                                    <div class="flex-1 border-t border-orange-200"></div>
                                </div>
                            </template>

                            {{-- Second half events (newest first) --}}
                            <template x-for="(event, idx) in secondHalfEvents" :key="'sh-' + idx">
                                <div class="flex items-center gap-3 py-2 px-3 rounded-lg transition-all duration-300"
                                     :class="isGoalEvent(event) ? 'bg-green-50' : ''"
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
                                    <div class="flex-1 border-t border-slate-200"></div>
                                    <span class="text-xs font-semibold text-slate-400 uppercase">{{ __('game.live_half_time') }}</span>
                                    <div class="flex-1 border-t border-slate-200"></div>
                                </div>
                            </template>

                            {{-- First half events (newest first) --}}
                            <template x-for="(event, idx) in firstHalfEvents" :key="'fh-' + idx">
                                <div class="flex items-center gap-3 py-2 px-3 rounded-lg transition-all duration-300"
                                     :class="isGoalEvent(event) ? 'bg-green-50' : ''"
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
                                    <span class="text-xs font-mono text-slate-400 w-8 text-right shrink-0">1'</span>
                                    <span class="text-sm w-6 text-center shrink-0">&#128227;</span>
                                    <span class="w-1.5 h-6 shrink-0"></span>
                                    <span class="text-xs text-slate-400">{{ __('game.live_kick_off') }}</span>
                                </div>
                            </template>

                            {{-- Empty state before kick off --}}
                            <template x-if="phase === 'pre_match'">
                                <div class="text-center py-8 text-slate-400 text-sm">
                                    {{ __('game.live_about_to_start') }}
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Full Time Summary --}}
                    <template x-if="phase === 'full_time'">
                        <div class="mt-6 pt-6 border-t border-slate-200">

                            {{-- ============================== --}}
                            {{-- TOURNAMENT DRAMATIC RESULTS    --}}
                            {{-- ============================== --}}

                            {{-- Champion: Gold celebration --}}
                            <template x-if="tournamentResultType === 'champion'">
                                <div class="relative -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 px-6 sm:px-8 py-10 bg-gradient-to-b from-amber-400 via-yellow-500 to-amber-600 text-center overflow-hidden">
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
                                            <button type="submit"
                                                    class="inline-flex items-center px-8 py-3 bg-white text-amber-700 font-bold rounded-lg shadow-lg hover:bg-amber-50 transition-colors min-h-[44px]"
                                                    x-text="translations.tournamentViewSummary"
                                                    :disabled="!processingReady">
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </template>

                            {{-- Runner-up: Silver/respectful --}}
                            <template x-if="tournamentResultType === 'runner_up'">
                                <div class="relative -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 px-6 sm:px-8 py-10 bg-gradient-to-b from-slate-500 via-slate-600 to-slate-700 text-center">
                                    <div class="text-5xl mb-3">&#129352;</div>
                                    <img :src="userTeamId === homeTeamId ? homeTeamImage : awayTeamImage"
                                         class="w-16 h-16 mx-auto mb-4 opacity-90" alt="">
                                    <h2 class="text-2xl md:text-3xl font-bold text-white mb-2"
                                        x-text="translations.tournamentRunnerUp"></h2>
                                    <p class="text-slate-300 text-sm"
                                       x-text="userTeamId === homeTeamId ? homeTeamName : awayTeamName"></p>
                                    <div class="mt-8">
                                        <form method="POST" action="{{ route('game.finalize-match', $game->id) }}">
                                            @csrf
                                            <input type="hidden" name="tournament_end" value="1">
                                            <button type="submit"
                                                    class="inline-flex items-center px-8 py-3 bg-white/10 text-white font-semibold rounded-lg border border-white/20 hover:bg-white/20 transition-colors min-h-[44px]"
                                                    x-text="translations.tournamentViewSummary"
                                                    :disabled="!processingReady">
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </template>

                            {{-- Third place: Bronze tinted --}}
                            <template x-if="tournamentResultType === 'third'">
                                <div class="relative -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 px-6 sm:px-8 py-10 bg-gradient-to-b from-orange-600 via-amber-700 to-orange-800 text-center">
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
                                            <button type="submit"
                                                    class="inline-flex items-center px-8 py-3 bg-white/10 text-white font-semibold rounded-lg border border-white/20 hover:bg-white/20 transition-colors min-h-[44px]"
                                                    x-text="translations.tournamentViewSummary"
                                                    :disabled="!processingReady">
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </template>

                            {{-- Fourth place: Somber --}}
                            <template x-if="tournamentResultType === 'fourth'">
                                <div class="relative -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 px-6 sm:px-8 py-10 bg-gradient-to-b from-slate-600 via-slate-700 to-slate-800 text-center">
                                    <img :src="userTeamId === homeTeamId ? homeTeamImage : awayTeamImage"
                                         class="w-14 h-14 mx-auto mb-4 opacity-70" alt="">
                                    <h2 class="text-2xl md:text-3xl font-bold text-slate-300 mb-2"
                                        x-text="translations.tournamentFourth"></h2>
                                    <p class="text-slate-400 text-sm"
                                       x-text="userTeamId === homeTeamId ? homeTeamName : awayTeamName"></p>
                                    <div class="mt-8">
                                        <form method="POST" action="{{ route('game.finalize-match', $game->id) }}">
                                            @csrf
                                            <input type="hidden" name="tournament_end" value="1">
                                            <button type="submit"
                                                    class="inline-flex items-center px-8 py-3 bg-white/10 text-slate-200 font-semibold rounded-lg border border-white/20 hover:bg-white/20 transition-colors min-h-[44px]"
                                                    x-text="translations.tournamentViewSummary"
                                                    :disabled="!processingReady">
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </template>

                            {{-- Eliminated in R32/R16/QF: Somber/dramatic --}}
                            <template x-if="tournamentResultType === 'eliminated'">
                                <div class="relative -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 px-6 sm:px-8 py-10 bg-gradient-to-b from-slate-700 via-slate-800 to-slate-900 text-center">
                                    <img :src="userTeamId === homeTeamId ? homeTeamImage : awayTeamImage"
                                         class="w-14 h-14 mx-auto mb-4 opacity-60 grayscale" alt="">
                                    <h2 class="text-2xl md:text-3xl font-bold text-white mb-1"
                                        x-text="translations.tournamentEliminated"></h2>
                                    <p class="text-slate-400 text-sm"
                                       x-text="translations.tournamentEliminatedIn"></p>
                                    <div class="mt-8">
                                        <form method="POST" action="{{ route('game.finalize-match', $game->id) }}">
                                            @csrf
                                            <input type="hidden" name="tournament_end" value="1">
                                            <button type="submit"
                                                    class="inline-flex items-center px-8 py-3 bg-white/10 text-slate-200 font-semibold rounded-lg border border-white/20 hover:bg-white/20 transition-colors min-h-[44px]"
                                                    x-text="translations.tournamentViewSummary"
                                                    :disabled="!processingReady">
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </template>

                            {{-- ============================== --}}
                            {{-- NON-DECISIVE / NORMAL RESULTS  --}}
                            {{-- ============================== --}}

                            {{-- Semi-final win: "You're in the Final!" --}}
                            <template x-if="tournamentResultType === 'to_final'">
                                <div class="mb-4 p-4 bg-gradient-to-r from-amber-50 to-yellow-50 border border-amber-200 rounded-lg text-center">
                                    <div class="text-3xl mb-1">&#127942;</div>
                                    <h3 class="text-lg font-bold text-amber-800" x-text="translations.tournamentToFinal"></h3>
                                </div>
                            </template>

                            {{-- Semi-final loss: "Third-Place Match Awaits" --}}
                            <template x-if="tournamentResultType === 'to_third_place'">
                                <div class="mb-4 p-4 bg-slate-50 border border-slate-200 rounded-lg text-center">
                                    <h3 class="text-base font-semibold text-slate-600" x-text="translations.tournamentToThirdPlace"></h3>
                                </div>
                            </template>

                            {{-- R32/R16/QF win: "You Advance!" --}}
                            <template x-if="tournamentResultType === 'advance'">
                                <div class="mb-4 p-4 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg text-center">
                                    <h3 class="text-lg font-bold text-green-700" x-text="translations.tournamentAdvance"></h3>
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
                                        <div class="mb-4 p-3 bg-orange-50 rounded-lg">
                                            <h4 class="text-sm font-semibold text-orange-800 mb-1">{{ __('game.live_injuries_report') }}</h4>
                                            @foreach($injuries as $injury)
                                                <div class="text-xs text-orange-700">
                                                    {{ $injury['playerName'] }} &mdash;
                                                    {{ __(App\Modules\Squad\Services\InjuryService::INJURY_TRANSLATION_MAP[$injury['metadata']['injury_type']] ?? 'game.live_injury') }}
                                                    @if(isset($injury['metadata']['weeks_out']))
                                                        ({{ trans_choice('game.live_weeks_out', $injury['metadata']['weeks_out'], ['count' => $injury['metadata']['weeks_out']]) }})
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Other Results --}}
                                    @if(count($otherMatches) > 0)
                                        <div class="mb-4">
                                            <h4 class="text-sm font-semibold text-slate-500 uppercase mb-2">{{ __('game.live_other_results') }}</h4>
                                            <div class="space-y-1">
                                                @foreach($otherMatches as $other)
                                                    <div class="flex items-center py-1.5 px-2 rounded text-sm bg-slate-50">
                                                        <div class="flex items-center gap-2 flex-1 justify-end">
                                                            <span class="@if($other['homeScore'] > $other['awayScore']) font-semibold @endif text-slate-700 truncate">{{ $other['homeTeam'] }}</span>
                                                            <img src="{{ $other['homeTeamImage'] }}" class="w-5 h-5">
                                                        </div>
                                                        <div class="px-3 font-semibold tabular-nums text-slate-900">
                                                            {{ $other['homeScore'] }} - {{ $other['awayScore'] }}
                                                        </div>
                                                        <div class="flex items-center gap-2 flex-1">
                                                            <img src="{{ $other['awayTeamImage'] }}" class="w-5 h-5">
                                                            <span class="@if($other['awayScore'] > $other['homeScore']) font-semibold @endif text-slate-700 truncate">{{ $other['awayTeam'] }}</span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <div class="text-center">
                                        <template x-if="!processingReady">
                                            <button type="button" disabled
                                                    class="inline-flex items-center gap-2 px-6 py-2 bg-slate-200 text-slate-500 font-semibold rounded-lg cursor-wait min-h-[44px]">
                                                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                                <span x-text="translations.processingActions"></span>
                                            </button>
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
                <div class="mt-4 px-4 py-3 bg-slate-800/50 rounded-lg">
                    <div class="flex items-center gap-6 overflow-x-auto text-xs">
                        <span class="text-slate-500 font-semibold shrink-0 uppercase">{{ __('game.live_other_results') }}</span>
                        <template x-for="(m, idx) in otherMatches" :key="idx">
                            <div class="flex items-center gap-2 shrink-0 text-slate-300">
                                <img :src="m.homeTeamImage" class="w-4 h-4">
                                <span class="truncate max-w-20" x-text="m.homeTeam"></span>
                                <span class="font-bold tabular-nums"
                                      x-text="otherMatchScores[idx]?.homeScore + ' - ' + otherMatchScores[idx]?.awayScore"></span>
                                <span class="truncate max-w-20" x-text="m.awayTeam"></span>
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
