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
$knockoutStarted = $knockoutTies->isNotEmpty();
$defaultTab = $knockoutStarted ? 'knockout' : 'league';
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" x-data="{ activeTab: '{{ $defaultTab }}' }">
                <div class="p-6 sm:p-8">
                    <h3 class="font-semibold text-xl text-slate-900 mb-6">{{ $competition->name }}</h3>

                    {{-- Tab Navigation --}}
                    @if($hasKnockout)
                        <div class="flex border-b border-slate-200 mb-0">
                            <button @click="activeTab = 'league'"
                                    :class="activeTab === 'league' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
                                {{ __('game.league_phase') }}
                                @if($leaguePhaseComplete)
                                    <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-bold bg-green-600 text-white rounded-full">{{ __('game.completed') }}</span>
                                @endif
                            </button>
                            <button @click="activeTab = 'knockout'"
                                    :class="activeTab === 'knockout' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
                                {{ __('game.knockout_phase') }}
                            </button>
                        </div>
                    @endif

                    {{-- Knockout Phase Bracket --}}
                    @if($hasKnockout)
                        <div x-show="activeTab === 'knockout'" class="mt-6 space-y-6">
                            <div class="overflow-x-auto">
                                <div class="flex gap-4" style="min-width: fit-content;">
                                    @foreach($knockoutRounds as $round)
                                        @php $ties = $knockoutTies->get($round->round, collect()); @endphp
                                        <div class="flex-shrink-0 w-64">
                                            <div class="text-center mb-4">
                                                <h4 class="font-semibold text-slate-700">{{ $round->name }}</h4>
                                                <div class="text-xs text-slate-400">
                                                    {{ $round->firstLegDate->format('M d') }}
                                                    @if($round->twoLegged)
                                                        / {{ $round->secondLegDate->format('M d') }}
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
                    @endif

                    {{-- League Phase Standings --}}
                    <div x-show="activeTab === 'league'" @if($hasKnockout) x-cloak @endif class="mt-6">
                        <div class="grid grid-cols-3 gap-8">
                            <div class="col-span-2 space-y-3">
                                @if(!$hasKnockout)
                                    <h4 class="font-semibold text-lg text-slate-700">
                                        {{ __('game.league_phase') }}
                                    </h4>
                                @endif

                                <table class="min-w-full table-fixed text-right divide-y divide-slate-300 border-spacing-2">
                                    <thead>
                                    <tr>
                                        <th class="font-semibold text-left w-8 p-2"></th>
                                        <th class="font-semibold text-left p-2"></th>
                                        <th class="font-semibold w-8 p-2">{{ __('game.played_abbr') }}</th>
                                        <th class="font-semibold w-8 p-2 hidden md:table-cell">{{ __('game.won_abbr') }}</th>
                                        <th class="font-semibold w-8 p-2 hidden md:table-cell">{{ __('game.drawn_abbr') }}</th>
                                        <th class="font-semibold w-8 p-2 hidden md:table-cell">{{ __('game.lost_abbr') }}</th>
                                        <th class="font-semibold w-8 p-2 hidden md:table-cell">{{ __('game.goals_for_abbr') }}</th>
                                        <th class="font-semibold w-8 p-2 hidden md:table-cell">{{ __('game.goals_against_abbr') }}</th>
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
                                            <td class="align-middle whitespace-nowrap p-2 text-slate-400 hidden md:table-cell">{{ $standing->won }}</td>
                                            <td class="align-middle whitespace-nowrap p-2 text-slate-400 hidden md:table-cell">{{ $standing->drawn }}</td>
                                            <td class="align-middle whitespace-nowrap p-2 text-slate-400 hidden md:table-cell">{{ $standing->lost }}</td>
                                            <td class="align-middle whitespace-nowrap p-2 text-slate-400 hidden md:table-cell">{{ $standing->goals_for }}</td>
                                            <td class="align-middle whitespace-nowrap p-2 text-slate-400 hidden md:table-cell">{{ $standing->goals_against }}</td>
                                            <td class="align-middle whitespace-nowrap p-2 text-slate-400">{{ $standing->goal_difference }}</td>
                                            <td class="align-middle whitespace-nowrap p-2 font-semibold">{{ $standing->points }}</td>
                                            <td class="align-middle whitespace-nowrap p-2">
                                                <div class="flex justify-center">
                                                    @foreach($teamForms[$standing->team_id] ?? [] as $result)
                                                        <x-form-icon :result="$result" />
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

                            <x-top-scorers :top-scorers="$topScorers" :player-team-id="$game->team_id" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
