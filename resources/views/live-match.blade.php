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
        <script>(function(){var t=localStorage.getItem('virtua-theme');if(t==='light'){document.documentElement.classList.add('light');document.querySelector('meta[name=theme-color]')?.setAttribute('content','#ffffff');}})()</script>
    </head>
    <body class="font-sans antialiased bg-surface-900 text-text-primary">
    <div class="min-h-screen">
    <main class="text-text-body pt-0 pb-24 sm:pt-2 sm:pb-24">
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
                tacticalActionsUrl: '{{ $tacticalActionsUrl }}',
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
                formationSlots: {{ Js::from($formationSlots) }},
                teamColors: {{ Js::from($teamColors) }},
                slotCompatibility: {{ Js::from($slotCompatibility) }},
                gridConfig: {{ Js::from($gridConfig) }},
                pitchPositions: {{ Js::from($pitchPositions) }},
                manualAssignments: {{ Js::from($slotAssignments) }},
                mvpPlayerName: {!! $mvpPlayerName ? Js::from($mvpPlayerName) : 'null' !!},
                mvpPlayerTeamId: {!! $mvpPlayerTeamId ? "'" . $mvpPlayerTeamId . "'" : 'null' !!},
                translations: {
                    unsavedTacticalChanges: {!! Js::from(__('game.tactical_unsaved_changes')) !!},
                    extraTime: {!! Js::from(__('game.live_extra_time')) !!},
                    etHalfTime: {!! Js::from(__('game.live_et_half_time')) !!},
                    penalties: {!! Js::from(__('game.live_penalties')) !!},
                    penScored: {!! Js::from(__('game.live_pen_scored')) !!},
                    penMissed: {!! Js::from(__('game.live_pen_missed')) !!},
                    penWinner: {!! Js::from(__('game.live_pen_winner')) !!},
                    tournamentChampion: {!! Js::from(__('game.tournament_champion_title')) !!},
                    tournamentRunnerUp: {!! Js::from(__('game.tournament_runner_up_title')) !!},
                    tournamentThird: {!! Js::from(__('game.tournament_third_place_title')) !!},
                    tournamentFourth: {!! Js::from(__('game.tournament_fourth_place_title')) !!},
                    tournamentEliminated: {!! Js::from(__('game.tournament_eliminated_title')) !!},
                    tournamentEliminatedIn: {!! Js::from(__('game.tournament_eliminated_in', ['round' => $knockoutRoundName ?? ''])) !!},
                    tournamentAdvance: {!! Js::from(__('game.tournament_you_advance')) !!},
                    tournamentAdvanceTo: {!! Js::from(__('game.tournament_advance_to', ['round' => ''])) !!},
                    tournamentToFinal: {!! Js::from(__('game.tournament_to_final')) !!},
                    tournamentToThirdPlace: {!! Js::from(__('game.tournament_to_third_place')) !!},
                    tournamentViewSummary: {!! Js::from(__('game.tournament_view_summary')) !!},
                    tournamentSimulating: {!! Js::from(__('game.tournament_simulating')) !!},
                    continueDashboard: {!! Js::from(__('game.live_continue_dashboard')) !!},
                    processingActions: {!! Js::from(__('game.processing_actions')) !!},
                    confirmFormation: {!! Js::from(__('game.confirm_formation')) !!},
                    confirmMentality: {!! Js::from(__('game.confirm_mentality')) !!},
                    confirmPlayingStyle: {!! Js::from(__('game.confirm_playing_style')) !!},
                    confirmPressing: {!! Js::from(__('game.confirm_pressing')) !!},
                    confirmDefLine: {!! Js::from(__('game.confirm_def_line')) !!},
                    mvpOfTheMatch: {!! Js::from(__('game.mvp_of_the_match')) !!},
                },
             })"
             x-on:keydown.escape.window="if (!tacticalPanelOpen) skipToEnd()"
        >
            @php
                $compBadge = \App\Support\CompetitionColors::badge($match->competition);
            @endphp

            <div>
                {{-- Sticky Header --}}
                <div class="sticky top-0 z-40 bg-surface-900/98 backdrop-blur-md border-b border-border-default">
                {{-- Top Meta Bar --}}
                <div class="relative flex items-center justify-between px-4 py-2.5">
                    {{-- Left: Competition indicator --}}
                    {{-- Mobile: colored dot only. Desktop: full pill with name --}}
                    <div class="shrink-0 z-10">
                        <div class="sm:hidden w-2.5 h-2.5 rounded-full {{ $compBadge['bg'] }}" x-tooltip.raw="{{ __($match->competition->name) }}"></div>
                        <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-md {{ $compBadge['bg'] }} {{ $compBadge['text'] }} text-[9px] font-semibold uppercase tracking-wider">
                            {{ __($match->competition->name) }}
                        </span>
                    </div>

                    {{-- Center: Round + Stadium (absolutely centered) --}}
                    <div class="absolute inset-x-0 flex justify-center pointer-events-none">
                        <div class="text-center px-16 truncate">
                            <div class="text-[10px] text-text-secondary font-medium uppercase tracking-wider truncate">
                                {{ $match->round_name ? __($match->round_name) : __('game.matchday_n', ['number' => $match->round_number]) }}
                            </div>
                            @if($match->homeTeam->stadium_name)
                                <div class="text-[9px] text-text-muted font-normal tracking-wide truncate hidden sm:block">{{ $match->homeTeam->stadium_name }}</div>
                            @endif
                        </div>
                    </div>

                    {{-- Right: LIVE indicator / Continue button --}}
                    <div class="shrink-0 z-10">
                        <template x-if="phase !== 'full_time'">
                            <div class="flex items-center gap-1.5">
                                <div class="w-2 h-2 rounded-full"
                                     :class="userPaused ? 'bg-accent-gold' : 'bg-accent-green animate-pulse'"></div>
                                <span class="text-[10px] font-semibold uppercase tracking-wider"
                                      :class="userPaused ? 'text-accent-gold' : 'text-accent-green'"
                                      x-text="userPaused ? '{{ __('game.live_paused') }}' : '{{ __('game.live_label') }}'"></span>
                            </div>
                        </template>
                        <template x-if="phase === 'full_time'">
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-text-muted">{{ __('game.live_full_time') }}</span>
                        </template>
                    </div>
                </div>
                <div class="px-4 sm:px-6 md:px-8 py-3 sm:py-4">

                    {{-- Scoreboard --}}
                    <div class="flex items-center justify-center gap-2 md:gap-6 mb-2">
                        <div class="flex items-center gap-2 md:gap-3 flex-1 justify-end">
                            <span class="text-sm md:text-xl font-heading font-bold uppercase tracking-wide text-text-primary truncate">{{ $match->homeTeam->name }}</span>
                            <x-team-crest :team="$match->homeTeam" class="w-10 h-10 md:w-14 md:h-14 shrink-0" />
                        </div>

                        <div class="relative px-2 md:px-6">
                            {{-- Score --}}
                            <div class="font-heading text-4xl whitespace-nowrap md:text-6xl font-extrabold text-text-primary tabular-nums transition-transform duration-200"
                                 :class="goalFlash ? 'scale-125' : 'scale-100'">
                                <span x-text="homeScore">0</span>
                                <span class="text-text-muted mx-1 font-bold">:</span>
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
                            <span class="text-sm md:text-xl font-heading font-bold uppercase tracking-wide text-text-primary truncate">{{ $match->awayTeam->name }}</span>
                        </div>
                    </div>

                    {{-- Match Clock --}}
                    <div class="text-center mb-3">
                        <span class="inline-flex items-center gap-2 font-heading text-sm font-bold rounded-full px-4 py-1"
                              :class="{
                                  'bg-surface-700 text-text-muted': phase === 'pre_match',
                                  'bg-accent-green/10 text-accent-green': phase === 'first_half' || phase === 'second_half',
                                  'bg-accent-gold/10 text-accent-gold': phase === 'half_time' || phase === 'extra_time_half_time',
                                  'bg-orange-500/10 text-orange-400': phase === 'going_to_extra_time' || phase === 'extra_time_first_half' || phase === 'extra_time_second_half',
                                  'bg-purple-500/10 text-penalty-text': phase === 'penalties',
                                  'bg-surface-700 text-text-primary': phase === 'full_time',
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
                                <span><span class="font-heading font-bold" x-text="displayMinute"></span>'</span>
                            </template>
                            <template x-if="phase === 'half_time'">
                                <span>{{ __('game.live_half_time') }}</span>
                            </template>
                            <template x-if="phase === 'going_to_extra_time'">
                                <span>{{ __('game.live_extra_time') }}</span>
                            </template>
                            <template x-if="phase === 'extra_time_first_half' || phase === 'extra_time_second_half'">
                                <span>{{ __('game.live_et_abbr') }} <span class="font-heading font-bold" x-text="displayMinute"></span>'</span>
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

                    {{-- Timeline Bar --}}
                    <div class="relative h-1.5 bg-surface-700 rounded-full mb-4 overflow-visible">
                        {{-- Progress --}}
                        <div class="absolute top-0 left-0 h-full rounded-full transition-all duration-300 ease-linear"
                             :class="isInExtraTime ? 'bg-gradient-to-r from-accent-orange to-amber-500' : 'bg-gradient-to-r from-accent-blue to-accent-green'"
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
                            <div class="absolute w-2.5 h-2.5 rounded-full top-1/2 -translate-y-1/2 -translate-x-1/2 transition-all duration-300"
                                 :style="'left: ' + marker.position + '%'"
                                 :class="{
                                     'bg-accent-green': marker.type === 'goal',
                                     'bg-accent-red': marker.type === 'own_goal',
                                     'bg-accent-gold': marker.type === 'yellow_card',
                                     'bg-accent-red': marker.type === 'red_card',
                                     'bg-accent-orange': marker.type === 'injury',
                                     'bg-accent-blue': marker.type === 'substitution',
                                 }"
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="scale-0 opacity-0"
                                 x-transition:enter-end="scale-100 opacity-100"
                            ></div>
                        </template>
                    </div>

                    {{-- Playback Controls: Pause | Speed | Skip --}}
                    <div class="flex items-center justify-center gap-0.5 pb-3" x-show="phase !== 'full_time' && !penaltyPickerOpen">
                        {{-- Pause/Play --}}
                        <button
                            @click="togglePause()"
                            class="w-8 h-8 rounded-lg bg-surface-700 border border-border-default flex items-center justify-center text-text-muted hover:text-text-primary transition-colors"
                            :class="userPaused ? 'text-accent-gold' : ''">
                            <svg x-show="!userPaused" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                <path d="M4.5 2a.5.5 0 0 0-.5.5v11a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-11a.5.5 0 0 0-.5-.5h-1ZM10.5 2a.5.5 0 0 0-.5.5v11a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-11a.5.5 0 0 0-.5-.5h-1Z" />
                            </svg>
                            <svg x-show="userPaused" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                <path d="M3 3.732a1.5 1.5 0 0 1 2.305-1.265l6.706 4.267a1.5 1.5 0 0 1 0 2.531l-6.706 4.268A1.5 1.5 0 0 1 3 12.267V3.732Z" />
                            </svg>
                        </button>

                        {{-- Speed selector --}}
                        <div class="inline-flex bg-surface-700 rounded-lg p-0.5 border border-border-default">
                            <template x-for="s in [1, 2, 4]" :key="s">
                                <button
                                    @click="setSpeed(s)"
                                    class="text-[10px] font-semibold tracking-wide w-8 h-7 rounded-md transition-colors flex items-center justify-center"
                                    :class="speed === s
                                        ? 'bg-accent-blue text-white'
                                        : 'text-text-muted hover:text-text-secondary'"
                                    x-text="s + 'x'"
                                ></button>
                            </template>
                        </div>

                        {{-- Skip to end --}}
                        <button
                            @click="skipToEnd()"
                            class="w-8 h-8 rounded-lg bg-surface-700 border border-border-default flex items-center justify-center text-text-muted hover:text-text-primary transition-colors"
                            x-bind:disabled="extraTimeLoading"
                            :class="extraTimeLoading ? 'opacity-50 cursor-not-allowed' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                <path d="M2.53 3.956A1 1 0 0 0 1 4.804v6.392a1 1 0 0 0 1.53.848l5.113-3.196c.16-.1.279-.233.357-.383v2.73a1 1 0 0 0 1.53.849l5.113-3.196a1 1 0 0 0 0-1.696L9.53 3.956A1 1 0 0 0 8 4.804v2.731a.992.992 0 0 0-.357-.383L2.53 3.956Z" />
                            </svg>
                        </button>
                    </div>

                </div>{{-- /sticky padding --}}
                </div>{{-- /sticky header --}}

                {{-- Tab Navigation --}}
                <div class="flex items-center justify-center gap-0 px-4 border-b border-border-default overflow-x-auto scrollbar-hide">
                    @foreach(['events' => __('game.live_tab_events'), 'stats' => __('game.live_tab_stats'), 'lineups' => __('game.live_tab_lineups')] as $tab => $label)
                        <button
                            @click="activeTab = '{{ $tab }}'"
                            class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap border-b-2 transition-colors"
                            :class="activeTab === '{{ $tab }}'
                                ? 'text-text-primary border-accent-blue'
                                : 'text-text-muted border-transparent hover:text-text-secondary'"
                        >{{ $label }}</button>
                    @endforeach
                    @if(count($otherMatches) > 0)
                        <button
                            @click="activeTab = 'results'"
                            class="hidden sm:block px-4 py-3 text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap border-b-2 transition-colors"
                            :class="activeTab === 'results'
                                ? 'text-text-primary border-accent-blue'
                                : 'text-text-muted border-transparent hover:text-text-secondary'"
                        >{{ __('game.live_tab_results') }}</button>
                    @endif
                </div>

                {{-- Tab: Events --}}
                <div x-show="activeTab === 'events'" class="px-4 sm:px-6 md:px-8 pb-4 sm:pb-6 md:pb-8">
                    {{-- Events Feed --}}
                    <div class="pt-2">
                        <div class="space-y-1 max-h-96 overflow-y-auto" id="events-feed">

                            {{-- Penalty kicks display --}}
                            <template x-if="penaltyKicks.length > 0 && (phase === 'penalties' || phase === 'full_time')">
                                <div class="mb-2">
                                    {{-- Header --}}
                                    <div class="flex items-center gap-3 py-2 px-4 rounded-t-lg bg-purple-500/10">
                                        <span class="text-sm w-6 text-center shrink-0">&#127942;</span>
                                        <div class="flex-1 text-center">
                                            <span class="text-sm font-heading font-bold uppercase tracking-wider text-penalty-text">{{ __('game.live_penalties') }}</span>
                                            <span class="text-lg font-heading font-extrabold text-penalty-score ml-2 tabular-nums"
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
                                                <span class="w-5 text-right text-xs font-heading font-bold text-penalty-text shrink-0"
                                                      x-text="kick.round"></span>
                                                <img :src="kick.side === 'home' ? homeTeamImage : awayTeamImage"
                                                     class="w-4 h-4 shrink-0 object-contain">
                                                <span class="flex-1 truncate text-sm text-text-body" x-text="kick.playerName"></span>
                                                <span class="text-[10px] font-bold shrink-0 px-1.5 py-0.5 rounded"
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
                                            <span class="text-sm font-heading font-bold uppercase tracking-wider text-penalty-text">{{ __('game.live_penalties') }}</span>
                                            <span class="text-lg font-heading font-extrabold text-penalty-score ml-2 tabular-nums"
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
                                <div class="flex items-center gap-3 py-2.5 px-3 rounded-lg transition-all duration-300"
                                     :class="isGoalEvent(event) ? 'bg-goal-highlight border-l-2 border-l-accent-gold' : 'border-l-2 border-l-transparent'"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 -translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                >
                                    @include('partials.live-match.event-row')
                                </div>
                            </template>

                            {{-- ET Half-time separator --}}
                            <template x-if="showETHalfTimeSeparator">
                                <div class="flex items-center gap-3 py-3">
                                    <div class="flex-1 h-px bg-accent-orange/20"></div>
                                    <span class="text-[9px] text-orange-400 uppercase tracking-widest font-heading font-semibold">{{ __('game.live_et_half_time') }}</span>
                                    <div class="flex-1 h-px bg-accent-orange/20"></div>
                                </div>
                            </template>

                            {{-- ET First half events --}}
                            <template x-for="(event, idx) in etFirstHalfEvents" :key="'etfh-' + idx">
                                <div class="flex items-center gap-3 py-2.5 px-3 rounded-lg transition-all duration-300"
                                     :class="isGoalEvent(event) ? 'bg-goal-highlight border-l-2 border-l-accent-gold' : 'border-l-2 border-l-transparent'"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 -translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                >
                                    @include('partials.live-match.event-row')
                                </div>
                            </template>

                            {{-- Extra Time separator --}}
                            <template x-if="showExtraTimeSeparator">
                                <div class="flex items-center gap-3 py-3">
                                    <div class="flex-1 h-px bg-accent-orange/20"></div>
                                    <span class="text-[9px] text-orange-500 uppercase tracking-widest font-heading font-semibold">{{ __('game.live_extra_time') }}</span>
                                    <div class="flex-1 h-px bg-accent-orange/20"></div>
                                </div>
                            </template>

                            {{-- Second half events (newest first) --}}
                            <template x-for="(event, idx) in secondHalfEvents" :key="'sh-' + idx">
                                <div class="flex items-center gap-3 py-2.5 px-3 rounded-lg transition-all duration-300"
                                     :class="isGoalEvent(event) ? 'bg-goal-highlight border-l-2 border-l-accent-gold' : 'border-l-2 border-l-transparent'"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 -translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                >
                                    @include('partials.live-match.event-row')
                                </div>
                            </template>

                            {{-- Half-time separator --}}
                            <template x-if="showHalfTimeSeparator">
                                <div class="flex items-center gap-3 py-3">
                                    <div class="flex-1 h-px bg-border-default"></div>
                                    <span class="text-[9px] text-text-faint uppercase tracking-widest font-heading font-semibold">{{ __('game.live_half_time') }}</span>
                                    <div class="flex-1 h-px bg-border-default"></div>
                                </div>
                            </template>

                            {{-- First half events (newest first) --}}
                            <template x-for="(event, idx) in firstHalfEvents" :key="'fh-' + idx">
                                <div class="flex items-center gap-3 py-2.5 px-3 rounded-lg transition-all duration-300"
                                     :class="isGoalEvent(event) ? 'bg-goal-highlight border-l-2 border-l-accent-gold' : 'border-l-2 border-l-transparent'"
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
                                    <span class="font-heading font-bold text-xs text-text-faint w-8 text-right shrink-0">1'</span>
                                    <span class="w-6 text-center shrink-0 flex items-center justify-center">
                                        <svg class="w-3 h-3 text-text-faint" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8"/></svg>
                                    </span>
                                    <span class="w-1.5 h-5 shrink-0"></span>
                                    <span class="text-[10px] text-text-faint">{{ __('game.live_kick_off') }}</span>
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

                            {{-- Tournament result banners (compact, details in bottom bar) --}}

                            {{-- ============================== --}}
                            {{-- NON-DECISIVE / NORMAL RESULTS  --}}
                            {{-- ============================== --}}

                            {{-- Semi-final win: "You're in the Final!" --}}
                            <template x-if="tournamentResultType === 'to_final'">
                                <div class="mb-4 p-4 bg-accent-gold/10 border border-accent-gold/20 rounded-xl text-center">
                                    <div class="text-3xl mb-1">&#127942;</div>
                                    <h3 class="text-lg font-heading font-bold text-accent-gold" x-text="translations.tournamentToFinal"></h3>
                                </div>
                            </template>

                            {{-- Semi-final loss: "Third-Place Match Awaits" --}}
                            <template x-if="tournamentResultType === 'to_third_place'">
                                <div class="mb-4 p-4 bg-surface-700/50 border border-border-strong rounded-xl text-center">
                                    <h3 class="text-base font-semibold text-text-secondary" x-text="translations.tournamentToThirdPlace"></h3>
                                </div>
                            </template>

                            {{-- R32/R16/QF win: "You Advance!" --}}
                            <template x-if="tournamentResultType === 'advance'">
                                <div class="mb-4 p-4 bg-accent-green/10 border border-accent-green/20 rounded-xl text-center">
                                    <h3 class="text-lg font-heading font-bold text-accent-green" x-text="translations.tournamentAdvance"></h3>
                                </div>
                            </template>

                            {{-- Injury report moved to fixed bottom bar for non-tournament matches --}}
                        </div>
                    </template>
                </div>
            </div>

                {{-- Tab: Stats --}}
                <div x-show="activeTab === 'stats'" x-cloak class="px-4 sm:px-6 md:px-8 py-4">
                    <div class="max-w-lg mx-auto space-y-4">
                        {{-- Possession --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="font-heading font-bold text-sm text-text-primary tabular-nums" x-text="homePossession + '%'"></span>
                                <span class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('game.possession') }}</span>
                                <span class="font-heading font-bold text-sm text-text-primary tabular-nums" x-text="awayPossession + '%'"></span>
                            </div>
                            <div class="flex h-2 rounded-full overflow-hidden gap-0.5">
                                <div class="h-full rounded-l-full bg-blue-500 transition-all duration-700" :style="'width: ' + homePossession + '%'"></div>
                                <div class="h-full rounded-r-full bg-blue-800 transition-all duration-700" :style="'width: ' + awayPossession + '%'"></div>
                            </div>
                        </div>

                        {{-- Goals --}}
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="font-heading font-bold text-sm text-text-primary tabular-nums" x-text="homeScore"></span>
                                <span class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('game.live_stat_goals') }}</span>
                                <span class="font-heading font-bold text-sm text-text-primary tabular-nums" x-text="awayScore"></span>
                            </div>
                        </div>

                        {{-- Cards --}}
                        <div class="flex items-center justify-between pt-3 border-t border-border-default">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-1">
                                    <div class="w-3 h-4 rounded-[2px] bg-accent-gold"></div>
                                    <span class="font-heading font-bold text-sm text-text-primary" x-text="getStatCount('yellow_card', 'home')"></span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <div class="w-3 h-4 rounded-[2px] bg-accent-red"></div>
                                    <span class="font-heading font-bold text-sm text-text-primary" x-text="getStatCount('red_card', 'home')"></span>
                                </div>
                            </div>
                            <span class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('game.live_stat_cards') }}</span>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-1">
                                    <span class="font-heading font-bold text-sm text-text-primary" x-text="getStatCount('yellow_card', 'away')"></span>
                                    <div class="w-3 h-4 rounded-[2px] bg-accent-gold"></div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="font-heading font-bold text-sm text-text-primary" x-text="getStatCount('red_card', 'away')"></span>
                                    <div class="w-3 h-4 rounded-[2px] bg-accent-red"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Substitutions --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="font-heading font-bold text-sm text-text-primary tabular-nums" x-text="getStatCount('substitution', 'home')"></span>
                                <span class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('game.live_stat_subs') }}</span>
                                <span class="font-heading font-bold text-sm text-text-primary tabular-nums" x-text="getStatCount('substitution', 'away')"></span>
                            </div>
                        </div>

                        {{-- Injuries --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="font-heading font-bold text-sm text-text-primary tabular-nums" x-text="getStatCount('injury', 'home')"></span>
                                <span class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('game.live_stat_injuries') }}</span>
                                <span class="font-heading font-bold text-sm text-text-primary tabular-nums" x-text="getStatCount('injury', 'away')"></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tab: Lineups --}}
                <div x-show="activeTab === 'lineups'" x-cloak class="px-4 sm:px-6 md:px-8 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-3xl mx-auto">
                        {{-- Home --}}
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <x-team-crest :team="$match->homeTeam" class="w-5 h-5 shrink-0" />
                                <span class="font-heading font-bold text-sm uppercase tracking-wide text-text-primary">{{ $match->homeTeam->name }}</span>
                                <span class="text-[10px] text-text-muted ml-auto">{{ $homeFormation }}</span>
                            </div>
                            <div class="space-y-0.5">
                                @forelse($homeLineupDisplay as $p)
                                    <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-surface-800/50">
                                        <span class="inline-flex items-center justify-center w-6 h-6 text-[10px] -skew-x-12 font-semibold text-white shrink-0
                                            {{ match($p['positionGroup']) {
                                                'GK' => 'bg-amber-600',
                                                'DEF' => 'bg-blue-600',
                                                'MID' => 'bg-green-600',
                                                'FWD' => 'bg-red-600',
                                                default => 'bg-surface-600',
                                            } }}">
                                            <span class="skew-x-12">{{ $p['positionAbbr'] }}</span>
                                        </span>
                                        <span class="text-xs text-text-body flex-1 truncate">{{ $p['name'] }}</span>
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[9px] font-semibold bg-surface-700 text-text-secondary shrink-0">{{ $p['overallScore'] }}</span>
                                    </div>
                                @empty
                                    <p class="text-xs text-text-muted italic px-3 py-3">{{ __('game.lineup_unknown') }}</p>
                                @endforelse
                            </div>
                        </div>

                        {{-- Away --}}
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <x-team-crest :team="$match->awayTeam" class="w-5 h-5 shrink-0" />
                                <span class="font-heading font-bold text-sm uppercase tracking-wide text-text-primary">{{ $match->awayTeam->name }}</span>
                                <span class="text-[10px] text-text-muted ml-auto">{{ $awayFormation }}</span>
                            </div>
                            <div class="space-y-0.5">
                                @forelse($awayLineupDisplay as $p)
                                    <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-surface-800/50">
                                        <span class="inline-flex items-center justify-center w-6 h-6 text-[10px] -skew-x-12 font-semibold text-white shrink-0
                                            {{ match($p['positionGroup']) {
                                                'GK' => 'bg-amber-600',
                                                'DEF' => 'bg-blue-600',
                                                'MID' => 'bg-green-600',
                                                'FWD' => 'bg-red-600',
                                                default => 'bg-surface-600',
                                            } }}">
                                            <span class="skew-x-12">{{ $p['positionAbbr'] }}</span>
                                        </span>
                                        <span class="text-xs text-text-body flex-1 truncate">{{ $p['name'] }}</span>
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[9px] font-semibold bg-surface-700 text-text-secondary shrink-0">{{ $p['overallScore'] }}</span>
                                    </div>
                                @empty
                                    <p class="text-xs text-text-muted italic px-3 py-3">{{ __('game.lineup_unknown') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

            {{-- Tactical Control Center Modal --}}
            @include('partials.live-match.tactical-panel')

            {{-- Penalty Kicker Picker Modal --}}
            @include('partials.live-match.penalty-picker')

            {{-- Tab: Results (desktop only) --}}
            @if(count($otherMatches) > 0)
                <div x-show="activeTab === 'results'" x-cloak class="hidden sm:block px-4 sm:px-6 md:px-8 py-4">
                    <div class="max-w-lg mx-auto space-y-2">
                        <template x-for="(m, idx) in otherMatches" :key="idx">
                            <div class="flex items-center gap-2 py-2 border-b border-border-default/50 text-sm">
                                <div class="flex-1 flex items-center justify-end gap-2 min-w-0">
                                    <span class="truncate text-text-secondary" x-text="m.homeTeam"></span>
                                    <img :src="m.homeTeamImage" class="w-5 h-5 shrink-0">
                                </div>
                                <span class="font-heading font-bold text-text-primary tabular-nums whitespace-nowrap px-2"
                                      x-text="otherMatchScores[idx]?.homeScore + ' - ' + otherMatchScores[idx]?.awayScore"></span>
                                <div class="flex-1 flex items-center gap-2 min-w-0">
                                    <img :src="m.awayTeamImage" class="w-5 h-5 shrink-0">
                                    <span class="truncate text-text-secondary" x-text="m.awayTeam"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            @endif

            {{-- Fixed Bottom Action Bar --}}
            <div class="fixed bottom-0 inset-x-0 z-30"
                 x-show="phase !== 'pre_match' && phase !== 'going_to_extra_time' && phase !== 'penalties' && !tacticalPanelOpen"
                 x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
            >
                <div class="bg-surface-800/95 backdrop-blur-md border-t border-border-default px-4 py-3">

                    {{-- During play: Tactical buttons --}}
                    <div x-show="phase !== 'full_time'" class="flex items-center gap-2.5">
                        <x-secondary-button
                            @click="openTacticalPanel('substitutions')"
                            class="flex-1 justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                            </svg>
                            {{ __('game.tactical_tab_substitutions') }}
                            <span class="text-text-muted tabular-nums" x-text="'(' + substitutionsMade.length + '/' + effectiveMaxSubstitutions + ')'"></span>
                        </x-secondary-button>
                        <x-secondary-button
                            @click="openTacticalPanel('tactics')"
                            class="flex-1 justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ __('game.tactical_tab_tactics') }}
                        </x-secondary-button>
                    </div>

                    {{-- At full time: Result summary + Continue --}}
                    <div x-show="phase === 'full_time'" x-cloak>
                        {{-- Tournament result label --}}
                        <template x-if="isTournamentDecisive">
                            <div class="flex items-center justify-center gap-2 mb-2">
                                <span class="text-lg"
                                      x-text="tournamentResultType === 'champion' ? '&#127942;'
                                           : tournamentResultType === 'runner_up' ? '&#129352;'
                                           : tournamentResultType === 'third' ? '&#129353;'
                                           : ''"></span>
                                <span class="font-heading text-sm font-bold uppercase tracking-wider"
                                      :class="{
                                          'text-accent-gold': tournamentResultType === 'champion',
                                          'text-text-secondary': tournamentResultType === 'runner_up' || tournamentResultType === 'fourth',
                                          'text-accent-orange': tournamentResultType === 'third',
                                          'text-accent-red': tournamentResultType === 'eliminated',
                                      }"
                                      x-text="tournamentResultType === 'champion' ? translations.tournamentChampion
                                           : tournamentResultType === 'runner_up' ? translations.tournamentRunnerUp
                                           : tournamentResultType === 'third' ? translations.tournamentThird
                                           : tournamentResultType === 'fourth' ? translations.tournamentFourth
                                           : tournamentResultType === 'eliminated' ? translations.tournamentEliminated
                                           : ''"></span>
                            </div>
                        </template>

                        {{-- Score + result label --}}
                        <div class="flex items-center justify-center gap-3 mb-3">
                            <img :src="homeTeamImage" class="w-6 h-6 shrink-0 object-contain" alt="">
                            <span class="font-heading text-lg font-extrabold text-text-primary tabular-nums"
                                  x-text="homeScore + ' - ' + awayScore"></span>
                            <img :src="awayTeamImage" class="w-6 h-6 shrink-0 object-contain" alt="">
                            <template x-if="!isTournamentDecisive">
                                <span class="text-xs font-heading font-semibold uppercase tracking-wider"
                                      :class="{
                                          'text-accent-green': (userTeamId === homeTeamId && homeScore > awayScore) || (userTeamId === awayTeamId && awayScore > homeScore),
                                          'text-accent-gold': homeScore === awayScore,
                                          'text-accent-red': (userTeamId === homeTeamId && homeScore < awayScore) || (userTeamId === awayTeamId && awayScore < homeScore),
                                      }"
                                      x-text="(userTeamId === homeTeamId && homeScore > awayScore) || (userTeamId === awayTeamId && awayScore > homeScore)
                                          ? '{{ __('game.live_result_win') }}'
                                          : (homeScore === awayScore ? '{{ __('game.live_result_draw') }}'
                                          : '{{ __('game.live_result_loss') }}')"></span>
                            </template>
                        </div>

                        {{-- Eliminated round context --}}
                        <template x-if="tournamentResultType === 'eliminated'">
                            <p class="text-center text-xs text-text-muted mb-3" x-text="translations.tournamentEliminatedIn"></p>
                        </template>

                        {{-- MVP of the match --}}
                        <template x-if="mvpPlayerName">
                            <div class="flex items-center justify-center gap-2 text-sm mb-3 py-2 px-3 rounded-lg bg-accent-yellow/10">
                                <span class="text-accent-yellow text-base">&#9733;</span>
                                <span class="text-text-secondary" x-text="translations.mvpOfTheMatch"></span>
                                <span class="font-semibold text-text-primary" x-text="mvpPlayerName"></span>
                                <span class="text-xs px-1.5 py-0.5 rounded bg-surface-700 text-text-muted"
                                      x-text="mvpPlayerTeamId === homeTeamId ? homeTeamName : awayTeamName"></span>
                            </div>
                        </template>

                        {{-- Continue button --}}
                        <form method="POST" action="{{ route('game.finalize-match', $game->id) }}">
                            @csrf
                            <template x-if="isTournamentDecisive">
                                <input type="hidden" name="tournament_end" value="1">
                            </template>
                            <x-primary-button type="submit"
                                    class="w-full justify-center gap-2"
                                    x-bind:disabled="!processingReady">
                                <svg x-show="!processingReady" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span x-text="processingReady ? '{{ __('game.live_continue') }}' : '{{ __('game.processing_short') }}'"></span>
                                <svg x-show="processingReady" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </x-primary-button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </main>
    </div>
    </body>
</html>
