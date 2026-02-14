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
                homeTeamImage: '{{ $match->homeTeam->image }}',
                awayTeamImage: '{{ $match->awayTeam->image }}',
                lineupPlayers: {{ Js::from($lineupPlayers) }},
                benchPlayers: {{ Js::from($benchPlayers) }},
                existingSubstitutions: {{ Js::from($existingSubstitutions) }},
                userTeamId: '{{ $game->team_id }}',
                substituteUrl: '{{ $substituteUrl }}',
                csrfToken: '{{ csrf_token() }}',
                maxSubstitutions: 5,
             })"
             x-on:keydown.escape.window="skipToEnd()"
        >
            {{-- Competition & Round Info --}}
            <div class="text-center mb-4">
                <span class="text-sm text-slate-400">
                    {{ $match->competition->name }} &middot; {{ $match->round_name ?? __('game.matchday_n', ['number' => $match->round_number]) }}
                </span>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">

                    {{-- Scoreboard --}}
                    <div class="flex items-center justify-center gap-2 md:gap-6 mb-2">
                        <div class="flex items-center gap-2 md:gap-3 flex-1 justify-end">
                            <span class="text-sm md:text-xl font-semibold text-slate-900 truncate">{{ $match->homeTeam->name }}</span>
                            <img src="{{ $match->homeTeam->image }}" class="w-10 h-10 md:w-14 md:h-14 shrink-0" alt="{{ $match->homeTeam->name }}">
                        </div>

                        <div class="relative px-2 md:px-6">
                            {{-- Score --}}
                            <div class="text-3xl md:text-5xl font-bold text-slate-900 tabular-nums transition-transform duration-200"
                                 :class="goalFlash ? 'scale-125' : 'scale-100'">
                                <span x-text="homeScore">0</span>
                                <span class="text-slate-300 mx-1">-</span>
                                <span x-text="awayScore">0</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 md:gap-3 flex-1">
                            <img src="{{ $match->awayTeam->image }}" class="w-10 h-10 md:w-14 md:h-14 shrink-0" alt="{{ $match->awayTeam->name }}">
                            <span class="text-sm md:text-xl font-semibold text-slate-900 truncate">{{ $match->awayTeam->name }}</span>
                        </div>
                    </div>

                    {{-- Match Clock --}}
                    <div class="text-center mb-6">
                        <span class="inline-flex items-center gap-2 text-sm font-semibold rounded-full px-4 py-1"
                              :class="{
                                  'bg-slate-100 text-slate-500': phase === 'pre_match',
                                  'bg-green-100 text-green-700': phase === 'first_half' || phase === 'second_half',
                                  'bg-amber-100 text-amber-700': phase === 'half_time',
                                  'bg-slate-800 text-white': phase === 'full_time',
                              }">
                            <span class="relative flex h-2 w-2" x-show="isRunning">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
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
                            <template x-if="phase === 'full_time'">
                                <span>{{ __('game.live_full_time') }}</span>
                            </template>
                        </span>
                    </div>

                    {{-- Timeline Bar --}}
                    <div class="relative h-2 bg-slate-100 rounded-full mb-6 overflow-visible">
                        {{-- Progress --}}
                        <div class="absolute top-0 left-0 h-full bg-green-500 rounded-full transition-all duration-300 ease-linear"
                             :style="'width: ' + timelineProgress + '%'"></div>

                        {{-- Half-time marker --}}
                        <div class="absolute top-0 h-full w-px bg-slate-300" style="left: 50%"></div>

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
                    <div class="flex items-center justify-center gap-2 mb-6" x-show="phase !== 'full_time'">
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
                            class="px-3 py-1 text-xs font-semibold rounded-md bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors ml-2">
                            {{ __('game.live_skip') }} ▸▸
                        </button>
                    </div>

                    {{-- Substitution Panel --}}
                    <div class="mb-4" x-show="phase !== 'full_time' && phase !== 'pre_match'">
                        {{-- Substitution button & counter --}}
                        <div class="flex items-center justify-between px-3 py-2" x-show="!subPanelOpen">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-slate-500 uppercase">{{ __('game.sub_title') }}</span>
                                <span class="text-xs text-slate-400" x-text="'(' + substitutionsMade.length + '/' + maxSubstitutions + ')'"></span>
                            </div>
                            <x-primary-button
                                color="sky"
                                type="button"
                                @click="openSubPanel()"
                                class="text-xs !px-3 !py-1.5"
                                x-bind:disabled="substitutionsMade.length >= maxSubstitutions || subProcessing"
                            >
                                <span x-show="substitutionsMade.length < maxSubstitutions">{{ __('game.sub_pause_and_substitute') }}</span>
                                <span x-show="substitutionsMade.length >= maxSubstitutions">{{ __('game.sub_limit_reached') }}</span>
                            </x-primary-button>
                        </div>

                        {{-- Sub selection panel (shown when open) --}}
                        <div x-show="subPanelOpen" x-transition class="border border-sky-200 rounded-lg bg-sky-50/50 p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {{-- Player Out --}}
                                <div>
                                    <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('game.sub_player_out') }}</h4>
                                    <div class="space-y-1 max-h-48 overflow-y-auto">
                                        <template x-for="player in availableLineupPlayers" :key="player.id">
                                            <button
                                                @click="selectedPlayerOut = player"
                                                class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-left text-sm transition-colors min-h-[44px]"
                                                :class="selectedPlayerOut?.id === player.id
                                                    ? 'bg-red-100 border border-red-300 text-red-800'
                                                    : 'bg-white border border-slate-200 hover:border-slate-300 text-slate-700'"
                                            >
                                                <span class="inline-flex items-center justify-center w-7 h-7 text-xs -skew-x-12 font-semibold text-white shrink-0"
                                                      :class="getPositionBadgeColor(player.positionAbbr)">
                                                    <span class="skew-x-12" x-text="player.positionAbbr"></span>
                                                </span>
                                                <span class="flex-1 truncate font-medium" x-text="player.name"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Player In --}}
                                <div>
                                    <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('game.sub_player_in') }}</h4>
                                    <div class="space-y-1 max-h-48 overflow-y-auto">
                                        <template x-for="player in availableBenchPlayers" :key="player.id">
                                            <button
                                                @click="selectedPlayerIn = player"
                                                class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-left text-sm transition-colors min-h-[44px]"
                                                :class="selectedPlayerIn?.id === player.id
                                                    ? 'bg-green-100 border border-green-300 text-green-800'
                                                    : 'bg-white border border-slate-200 hover:border-slate-300 text-slate-700'"
                                            >
                                                <span class="inline-flex items-center justify-center w-7 h-7 text-xs -skew-x-12 font-semibold text-white shrink-0"
                                                      :class="getPositionBadgeColor(player.positionAbbr)">
                                                    <span class="skew-x-12" x-text="player.positionAbbr"></span>
                                                </span>
                                                <span class="flex-1 truncate font-medium" x-text="player.name"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            {{-- Confirm/Cancel --}}
                            <div class="flex items-center justify-end gap-2 mt-4">
                                <x-secondary-button @click="closeSubPanel()">
                                    {{ __('game.sub_cancel') }}
                                </x-secondary-button>
                                <x-primary-button
                                    color="sky"
                                    type="button"
                                    @click="confirmSubstitution()"
                                    x-bind:disabled="!selectedPlayerOut || !selectedPlayerIn || subProcessing"
                                >
                                    <span x-show="!subProcessing">{{ __('game.sub_confirm') }}</span>
                                    <span x-show="subProcessing">{{ __('game.sub_processing') }}</span>
                                </x-primary-button>
                            </div>
                        </div>

                        {{-- Made substitutions list --}}
                        <template x-if="substitutionsMade.length > 0 && !subPanelOpen">
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

                            {{-- Second half events (newest first) --}}
                            <template x-for="(event, idx) in secondHalfEvents" :key="'sh-' + idx">
                                <div class="flex items-center gap-3 py-2 px-3 rounded-lg transition-all duration-300"
                                     :class="isGoalEvent(event) ? 'bg-green-50' : ''"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 -translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                >
                                    <span class="text-xs font-mono text-slate-400 w-8 text-right shrink-0"
                                          x-text="event.minute + '\''"></span>
                                    <span class="text-sm w-6 text-center shrink-0" x-text="getEventIcon(event.type)"></span>
                                    <img :src="getEventSide(event) === 'home' ? homeTeamImage : awayTeamImage"
                                         class="w-6 h-6 shrink-0 object-contain"
                                         :alt="getEventSide(event) === 'home' ? 'Home' : 'Away'">
                                    <div class="flex-1 min-w-0">
                                        <span class="font-semibold text-sm text-slate-800" x-text="event.playerName"></span>
                                        <template x-if="event.type === 'goal'">
                                            <span class="text-xs text-slate-500 ml-1">{{ __('game.live_goal') }}</span>
                                        </template>
                                        <template x-if="event.type === 'own_goal'">
                                            <span class="text-xs text-red-500 ml-1">({{ __('game.og') }})</span>
                                        </template>
                                        <template x-if="event.type === 'yellow_card'">
                                            <span class="text-xs text-slate-500 ml-1">{{ __('game.live_yellow_card') }}</span>
                                        </template>
                                        <template x-if="event.type === 'red_card'">
                                            <span class="text-xs text-red-600 ml-1" x-text="event.metadata?.second_yellow ? '{{ __('game.live_second_yellow') }}' : '{{ __('game.live_red_card') }}'"></span>
                                        </template>
                                        <template x-if="event.type === 'injury'">
                                            <span class="text-xs text-orange-600 ml-1">{{ __('game.live_injury') }}</span>
                                        </template>
                                        <template x-if="event.type === 'substitution'">
                                            <span class="text-xs text-sky-600 ml-1" x-text="'&#8618; ' + event.playerInName"></span>
                                        </template>
                                        <template x-if="event.assistPlayerName">
                                            <div class="text-xs text-slate-400" x-text="'{{ __('game.live_assist') }} ' + event.assistPlayerName"></div>
                                        </template>
                                    </div>
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
                                    <span class="text-xs font-mono text-slate-400 w-8 text-right shrink-0"
                                          x-text="event.minute + '\''"></span>
                                    <span class="text-sm w-6 text-center shrink-0" x-text="getEventIcon(event.type)"></span>
                                    <img :src="getEventSide(event) === 'home' ? homeTeamImage : awayTeamImage"
                                         class="w-6 h-6 shrink-0 object-contain"
                                         :alt="getEventSide(event) === 'home' ? 'Home' : 'Away'">
                                    <div class="flex-1 min-w-0">
                                        <span class="font-semibold text-sm text-slate-800" x-text="event.playerName"></span>
                                        <template x-if="event.type === 'goal'">
                                            <span class="text-xs text-slate-500 ml-1">{{ __('game.live_goal') }}</span>
                                        </template>
                                        <template x-if="event.type === 'own_goal'">
                                            <span class="text-xs text-red-500 ml-1">({{ __('game.og') }})</span>
                                        </template>
                                        <template x-if="event.type === 'yellow_card'">
                                            <span class="text-xs text-slate-500 ml-1">{{ __('game.live_yellow_card') }}</span>
                                        </template>
                                        <template x-if="event.type === 'red_card'">
                                            <span class="text-xs text-red-600 ml-1" x-text="event.metadata?.second_yellow ? '{{ __('game.live_second_yellow') }}' : '{{ __('game.live_red_card') }}'"></span>
                                        </template>
                                        <template x-if="event.type === 'injury'">
                                            <span class="text-xs text-orange-600 ml-1">{{ __('game.live_injury') }}</span>
                                        </template>
                                        <template x-if="event.type === 'substitution'">
                                            <span class="text-xs text-sky-600 ml-1" x-text="'&#8618; ' + event.playerInName"></span>
                                        </template>
                                        <template x-if="event.assistPlayerName">
                                            <div class="text-xs text-slate-400" x-text="'{{ __('game.live_assist') }} ' + event.assistPlayerName"></div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Kick off message --}}
                            <template x-if="phase !== 'pre_match'">
                                <div class="flex items-center gap-3 py-2 px-3">
                                    <span class="text-xs font-mono text-slate-400 w-8 text-right shrink-0">1'</span>
                                    <span class="text-sm w-6 text-center shrink-0">📣</span>
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
                                            {{ __(App\Game\Services\InjuryService::INJURY_TRANSLATION_MAP[$injury['metadata']['injury_type']] ?? 'game.live_injury') }}
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
                                <x-primary-button-link :href="route('show-game', $game->id)" class="px-6">
                                    {{ __('game.live_continue_dashboard') }}
                                </x-primary-button-link>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

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
