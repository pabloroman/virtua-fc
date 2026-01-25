@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex gap-6">
                {{-- Main standings table --}}
                <div class="flex-1 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-6">{{ $competition->name }} Standings</h3>

                        <table class="w-full text-sm">
                        <thead class="text-left text-gray-500 border-b">
                            <tr>
                                <th class="py-3 w-12">#</th>
                                <th class="py-3"></th>
                                <th class="py-3">Team</th>
                                <th class="py-3 text-center">P</th>
                                <th class="py-3 text-center">W</th>
                                <th class="py-3 text-center">D</th>
                                <th class="py-3 text-center">L</th>
                                <th class="py-3 text-center">GF</th>
                                <th class="py-3 text-center">GA</th>
                                <th class="py-3 text-center">GD</th>
                                <th class="py-3 text-center font-bold">Pts</th>
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
                                <tr class="border-t @if($isPlayer) bg-sky-50 font-semibold @endif {{ $zoneClass }}">
                                    <td class="py-3 pl-2">
                                        <div class="flex items-center gap-1">
                                            <span>{{ $standing->position }}</span>
                                            @if($standing->position_change !== 0)
                                                <span class="text-xs @if($standing->position_change > 0) text-green-500 @else text-red-500 @endif">
                                                    {{ $standing->position_change_icon }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <img src="{{ $standing->team->image }}" class="w-6 h-6">
                                    </td>
                                    <td class="py-3">{{ $standing->team->name }}</td>
                                    <td class="py-3 text-center">{{ $standing->played }}</td>
                                    <td class="py-3 text-center">{{ $standing->won }}</td>
                                    <td class="py-3 text-center">{{ $standing->drawn }}</td>
                                    <td class="py-3 text-center">{{ $standing->lost }}</td>
                                    <td class="py-3 text-center">{{ $standing->goals_for }}</td>
                                    <td class="py-3 text-center">{{ $standing->goals_against }}</td>
                                    <td class="py-3 text-center">{{ $standing->goal_difference }}</td>
                                    <td class="py-3 text-center font-bold">{{ $standing->points }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($competition->id === 'ESP1')
                    <div class="mt-6 flex gap-6 text-xs text-gray-500">
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
                    <div class="mt-6 flex gap-6 text-xs text-gray-500">
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
                </div>

                {{-- Sidebar: Top Scorers --}}
                <div class="w-72 flex-shrink-0">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4">
                            <h4 class="font-semibold text-gray-700 mb-4">Top Scorers</h4>

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
    </div>
</x-app-layout>
