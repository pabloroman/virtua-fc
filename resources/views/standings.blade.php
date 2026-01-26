@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-12 grid grid-cols-3 gap-12">
                {{-- Main standings table --}}
                <div class="col-span-2 space-y-3">

                    <h3 class="font-semibold text-xl text-slate-900">{{ $competition->name }} Standings</h3>

                    <table class="min-w-full table-fixed text-right divide-y divide-slate-300 border-spacing-2">
                        <thead>
                        <tr class="text-slate-900">
                            <th class="font-semibold text-left w-8 p-2"></th>
                            <th class="font-semibold text-left p-2"></th>
                            <th class="font-semibold w-10 p-2">P</th>
                            <th class="font-semibold w-10 p-2">W</th>
                            <th class="font-semibold w-10 p-2">D</th>
                            <th class="font-semibold w-10 p-2">L</th>
                            <th class="font-semibold w-10 p-2">GF</th>
                            <th class="font-semibold w-10 p-2">GA</th>
                            <th class="font-semibold w-10 p-2">GD</th>
                            <th class="font-semibold w-10 p-2">Pts</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($standings as $standing)
                            @php
                                $isPlayer = $standing->team_id === $game->team_id;
                                $zoneClass = '';
                                if ($competition->id === 'ESP1') {
                                    if ($standing->position <= 4) $zoneClass = 'border-l-4 border-l-blue-500'; // UCL
                                    elseif ($standing->position <= 6) $zoneClass = 'border-l-4 border-l-orange-500'; // UEL
                                    elseif ($standing->position >= 18) $zoneClass = 'border-l-4 border-l-red-500'; // Relegation
                                } elseif ($competition->id === 'ESP2') {
                                    if ($standing->position <= 2) $zoneClass = 'border-l-4 border-l-green-500'; // Direct promotion
                                    elseif ($standing->position <= 6) $zoneClass = 'border-l-4 border-l-green-300'; // Playoff
                                    elseif ($standing->position >= 19) $zoneClass = 'border-l-4 border-l-red-500'; // Relegation
                                }
                            @endphp
                            <tr class="border-b px-2 text-lg {{ $zoneClass }}">
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
                                    <div class="flex items-center space-x-2">
                                        <img src="{{ $standing->team->image }}" class="w-6 h-6">
                                        <span>{{ $standing->team->name }}</span>
                                    </div>
                                </td>
                                <td class="align-middle whitespace-nowrap p-2">{{ $standing->played }}</td>
                                <td class="align-middle whitespace-nowrap p-2">{{ $standing->won }}</td>
                                <td class="align-middle whitespace-nowrap p-2">{{ $standing->drawn }}</td>
                                <td class="align-middle whitespace-nowrap p-2">{{ $standing->lost }}</td>
                                <td class="align-middle whitespace-nowrap p-2">{{ $standing->goals_for }}</td>
                                <td class="align-middle whitespace-nowrap p-2">{{ $standing->goals_against }}</td>
                                <td class="align-middle whitespace-nowrap p-2">{{ $standing->goal_difference }}</td>
                                <td class="align-middle whitespace-nowrap p-2 font-semibold">{{ $standing->points }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                    @if($competition->id === 'ESP1')
                        <div class="flex gap-6 text-xs text-gray-500">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-blue-500 rounded"></div>
                                <span>Champions League</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-orange-500 rounded"></div>
                                <span>Europa League</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-red-500 rounded"></div>
                                <span>Relegation</span>
                            </div>
                        </div>
                    @elseif($competition->id === 'ESP2')
                        <div class="flex gap-6 text-xs text-gray-500">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-green-500 rounded"></div>
                                <span>Direct Promotion</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-green-300 rounded"></div>
                                <span>Promotion Playoff</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-red-500 rounded"></div>
                                <span>Relegation</span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Sidebar: Top Scorers --}}
                <div class="grid-cols-1 space-y-6">
                    <h4 class="font-semibold text-xl text-slate-900">Top Scorers</h4>

                    @if($topScorers->isEmpty())
                        <p class="text-sm text-gray-500">No goals scored yet</p>
                    @else
                        <div class="space-y-2">
                            @foreach($topScorers as $index => $scorer)
                                @php
                                    $isPlayerTeam = $scorer->team_id === $game->team_id;
                                @endphp
                                <div class="flex items-center gap-2 text-sm @if($isPlayerTeam) bg-sky-50 -mx-2 px-2 py-1 rounded @endif">
                                    <span class="w-5 text-gray-400 text-xs">{{ $index + 1 }}</span>
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
</x-app-layout>
