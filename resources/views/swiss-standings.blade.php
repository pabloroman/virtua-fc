@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition $competition */
/** @var array $standingsZones */
/** @var \Illuminate\Support\Collection $knockoutTies */

// Map border colors to complete Tailwind classes
$borderColorMap = [
    'blue-500' => 'border-l-4 border-l-blue-500',
    'orange-500' => 'border-l-4 border-l-orange-500',
    'red-500' => 'border-l-4 border-l-red-500',
    'green-300' => 'border-l-4 border-l-green-300',
    'green-500' => 'border-l-4 border-l-green-500',
    'yellow-500' => 'border-l-4 border-l-yellow-500',
];

$getZoneClass = function($position) use ($standingsZones, $borderColorMap) {
    foreach ($standingsZones as $zone) {
        if ($position >= $zone['minPosition'] && $position <= $zone['maxPosition']) {
            return $borderColorMap[$zone['borderColor']] ?? '';
        }
    }
    return '';
};

$hasKnockout = $knockoutTies->isNotEmpty() || $knockoutRounds->isNotEmpty();
$defaultTab = $hasKnockout ? 'knockout' : 'league';
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div x-data="{ activeTab: '{{ $defaultTab }}' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Tab Navigation --}}
            @if($hasKnockout)
                <div class="flex border-b border-slate-200 mb-0">
                    <button @click="activeTab = 'knockout'"
                            :class="activeTab === 'knockout' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                            class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
                        {{ __('game.knockout_phase') }}
                    </button>
                    <button @click="activeTab = 'league'"
                            :class="activeTab === 'league' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                            class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
                        {{ __('game.league_phase') }}
                        @if($leaguePhaseComplete)
                            <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-bold bg-green-600 text-white rounded-full">{{ __('game.completed') }}</span>
                        @endif
                    </button>
                </div>
            @endif

            {{-- Knockout Phase Bracket --}}
            @if($hasKnockout)
                <div x-show="activeTab === 'knockout'">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 sm:p-8 space-y-6">
                            <h3 class="font-semibold text-xl text-slate-900">{{ __('game.knockout_phase') }}</h3>

                            <div class="overflow-x-auto">
                                <div class="flex gap-4" style="min-width: fit-content;">
                                    @foreach($knockoutRounds as $round)
                                        @php $ties = $knockoutTies->get($round->round_number, collect()); @endphp
                                        <div class="flex-shrink-0 w-64">
                                            <div class="text-center mb-4">
                                                <h4 class="font-semibold text-slate-700">{{ $round->round_name }}</h4>
                                                <div class="text-xs text-slate-400">
                                                    {{ $round->first_leg_date->format('M d') }}
                                                    @if($round->isTwoLegged())
                                                        / {{ $round->second_leg_date->format('M d') }}
                                                    @endif
                                                </div>
                                            </div>

                                            @if($ties->isEmpty())
                                                <div class="p-4 text-center border border-dashed rounded-lg">
                                                    <div class="text-slate-400 text-sm">-</div>
                                                </div>
                                            @else
                                                <div class="space-y-2">
                                                    @foreach($ties as $tie)
                                                        <x-cup-tie-card :tie="$tie" :player-team-id="$game->team_id" />
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- League Phase Standings --}}
            <div x-show="activeTab === 'league'" @if($hasKnockout) x-cloak @endif>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 sm:p-8 grid grid-cols-3 gap-8">
                        <div class="col-span-2 space-y-3">
                            <h3 class="font-semibold text-xl text-slate-900">
                                {{ $competition->name }} - {{ __('game.league_phase') }}
                                @if($leaguePhaseComplete)
                                    <span class="text-sm text-green-600 font-normal ml-2">{{ __('game.completed') }}</span>
                                @endif
                            </h3>

                            <table class="min-w-full table-fixed text-right divide-y divide-slate-300 border-spacing-2">
                                <thead>
                                <tr>
                                    <th class="font-semibold text-left w-8 p-2"></th>
                                    <th class="font-semibold text-left p-2"></th>
                                    <th class="font-semibold w-8 p-2">{{ __('game.played_abbr') }}</th>
                                    <th class="font-semibold w-8 p-2">{{ __('game.won_abbr') }}</th>
                                    <th class="font-semibold w-8 p-2">{{ __('game.drawn_abbr') }}</th>
                                    <th class="font-semibold w-8 p-2">{{ __('game.lost_abbr') }}</th>
                                    <th class="font-semibold w-8 p-2">{{ __('game.goals_for_abbr') }}</th>
                                    <th class="font-semibold w-8 p-2">{{ __('game.goals_against_abbr') }}</th>
                                    <th class="font-semibold w-8 p-2">{{ __('game.goal_diff_abbr') }}</th>
                                    <th class="font-semibold w-8 p-2">{{ __('game.pts_abbr') }}</th>
                                    <th class="font-semibold w-8 p-2 text-center">{{ __('game.last_5') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($standings as $standing)
                                    @php
                                        $isPlayer = $standing->team_id === $game->team_id;
                                        $zoneClass = $getZoneClass($standing->position);
                                    @endphp
                                    <tr class="border-b px-2 text-lg {{ $zoneClass }} @if($isPlayer) bg-amber-50 @endif">
                                        <td class="align-middle whitespace-nowrap text-left px-2 text-slate-900 font-semibold">
                                            <div class="flex items-center gap-1">
                                                <span>{{ $standing->position }}</span>
                                                @if($standing->position_change !== 0)
                                                    <span class="text-xs @if($standing->position_change > 0) text-green-500 @else text-red-500 @endif">
                                                        {{ $standing->position_change_icon }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="align-middle whitespace-nowrap py-1.5 px-2">
                                            <div class="flex items-center space-x-2 @if($isPlayer) font-semibold @endif">
                                                <img src="{{ $standing->team->image }}" class="w-6 h-6">
                                                <span>{{ $standing->team->name }}</span>
                                            </div>
                                        </td>
                                        <td class="align-middle whitespace-nowrap p-2 text-slate-400">{{ $standing->played }}</td>
                                        <td class="align-middle whitespace-nowrap p-2 text-slate-400">{{ $standing->won }}</td>
                                        <td class="align-middle whitespace-nowrap p-2 text-slate-400">{{ $standing->drawn }}</td>
                                        <td class="align-middle whitespace-nowrap p-2 text-slate-400">{{ $standing->lost }}</td>
                                        <td class="align-middle whitespace-nowrap p-2 text-slate-400">{{ $standing->goals_for }}</td>
                                        <td class="align-middle whitespace-nowrap p-2 text-slate-400">{{ $standing->goals_against }}</td>
                                        <td class="align-middle whitespace-nowrap p-2 text-slate-400">{{ $standing->goal_difference }}</td>
                                        <td class="align-middle whitespace-nowrap p-2 font-semibold">{{ $standing->points }}</td>
                                        <td class="align-middle whitespace-nowrap p-2">
                                            <div class="flex justify-center">
                                                @foreach($teamForms[$standing->team_id] ?? [] as $result)
                                                    @if($result === 'W')
                                                        <svg class="w-5 h-5 rounded-full bg-white fill-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M320 576C178.6 576 64 461.4 64 320C64 178.6 178.6 64 320 64C461.4 64 576 178.6 576 320C576 461.4 461.4 576 320 576zM438 209.7C427.3 201.9 412.3 204.3 404.5 215L285.1 379.2L233 327.1C223.6 317.7 208.4 317.7 199.1 327.1C189.8 336.5 189.7 351.7 199.1 361L271.1 433C276.1 438 282.9 440.5 289.9 440C296.9 439.5 303.3 435.9 307.4 430.2L443.3 243.2C451.1 232.5 448.7 217.5 438 209.7z"/></svg>
                                                    @elseif($result === 'D')
                                                        <svg class="w-5 h-5 rounded-full bg-white fill-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576zM232 296L408 296C421.3 296 432 306.7 432 320C432 333.3 421.3 344 408 344L232 344C218.7 344 208 333.3 208 320C208 306.7 218.7 296 232 296z"/></svg>
                                                    @else
                                                        <svg class="w-5 h-5 rounded-full bg-white fill-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576zM231 231C240.4 221.6 255.6 221.6 264.9 231L319.9 286L374.9 231C384.3 221.6 399.5 221.6 408.8 231C418.1 240.4 418.2 255.6 408.8 264.9L353.8 319.9L408.8 374.9C418.2 384.3 418.2 399.5 408.8 408.8C399.4 418.1 384.2 418.2 374.9 408.8L319.9 353.8L264.9 408.8C255.5 418.2 240.3 418.2 231 408.8C221.7 399.4 221.6 384.2 231 374.9L286 319.9L231 264.9C221.6 255.5 221.6 240.3 231 231z"/></svg>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>

                            @if(count($standingsZones) > 0)
                                <div class="flex gap-6 text-xs text-slate-500">
                                    @foreach($standingsZones as $zone)
                                        <div class="flex items-center gap-2">
                                            <div class="w-3 h-3 {{ $zone['bgColor'] }} rounded"></div>
                                            <span>{{ __($zone['label']) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Sidebar: Top Scorers --}}
                        <div class="grid-cols-1 space-y-6">
                            <h4 class="font-semibold text-xl text-slate-900">{{ __('game.top_scorers') }}</h4>

                            @if($topScorers->isEmpty())
                                <p class="text-sm text-slate-500">{{ __('game.no_goals_yet') }}</p>
                            @else
                                <div class="space-y-2">
                                    @foreach($topScorers as $index => $scorer)
                                        @php
                                            $isPlayerTeam = $scorer->team_id === $game->team_id;
                                        @endphp
                                        <div class="flex items-center gap-2 text-sm @if($isPlayerTeam) bg-sky-50 -mx-2 px-2 py-1 rounded @endif">
                                            <span class="w-5 text-slate-400 text-xs">{{ $index + 1 }}</span>
                                            <img src="{{ $scorer->team->image }}" class="w-4 h-4" title="{{ $scorer->team->name }}">
                                            <span class="flex-1 truncate @if($isPlayerTeam) font-medium @endif">{{ $scorer->player->name }}</span>
                                            <span class="font-semibold">{{ $scorer->goals }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
