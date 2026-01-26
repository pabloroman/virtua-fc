@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$nextMatch"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Standings Card --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="font-semibold text-xl text-slate-900 mb-4">Standings</h3>
                        <table class="w-full text-sm">
                            <thead class="text-left text-gray-500">
                                <tr>
                                    <th class="py-2 w-8">#</th>
                                    <th class="py-2">Team</th>
                                    <th class="py-2 text-center">P</th>
                                    <th class="py-2 text-center">W</th>
                                    <th class="py-2 text-center">D</th>
                                    <th class="py-2 text-center">L</th>
                                    <th class="py-2 text-center">GD</th>
                                    <th class="py-2 text-center font-bold">Pts</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($standings as $standing)
                                <tr class="border-t @if($standing->team_id === $game->team_id) bg-sky-50 font-semibold @endif">
                                    <td class="py-2">{{ $standing->position }}</td>
                                    <td class="py-2">
                                        <div class="flex items-center gap-2">
                                            <img src="{{ $standing->team->image }}" class="w-5 h-5">
                                            <span>{{ $standing->team->name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2 text-center">{{ $standing->played }}</td>
                                    <td class="py-2 text-center">{{ $standing->won }}</td>
                                    <td class="py-2 text-center">{{ $standing->drawn }}</td>
                                    <td class="py-2 text-center">{{ $standing->lost }}</td>
                                    <td class="py-2 text-center">{{ $standing->goal_difference }}</td>
                                    <td class="py-2 text-center font-bold">{{ $standing->points }}</td>
                                </tr>
                                @endforeach
                                @if($playerStanding)
                                <tr class="border-t border-dashed">
                                    <td colspan="8" class="py-1 text-center text-gray-400 text-xs">...</td>
                                </tr>
                                <tr class="border-t bg-sky-50 font-semibold">
                                    <td class="py-2">{{ $playerStanding->position }}</td>
                                    <td class="py-2">
                                        <div class="flex items-center gap-2">
                                            <img src="{{ $playerStanding->team->image }}" class="w-5 h-5">
                                            <span>{{ $playerStanding->team->name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2 text-center">{{ $playerStanding->played }}</td>
                                    <td class="py-2 text-center">{{ $playerStanding->won }}</td>
                                    <td class="py-2 text-center">{{ $playerStanding->drawn }}</td>
                                    <td class="py-2 text-center">{{ $playerStanding->lost }}</td>
                                    <td class="py-2 text-center">{{ $playerStanding->goal_difference }}</td>
                                    <td class="py-2 text-center font-bold">{{ $playerStanding->points }}</td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                        <div class="mt-4 text-center">
                            <a href="{{ route('game.standings', $game->id) }}" class="text-sky-600 hover:text-sky-800 text-sm">View full standings</a>
                        </div>
                    </div>
                </div>

                {{-- Recent Results Card --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="font-semibold text-xl text-slate-900 mb-4">Recent Results</h3>
                        @if($recentResults->isEmpty())
                            <p class="text-gray-500">No matches played yet. Click "Continue" to start the season!</p>
                        @else
                            <div class="space-y-3">
                                @foreach($recentResults as $match)
                                <div class="flex items-center justify-between p-3 rounded-lg @if($match->involvesTeam($game->team_id)) bg-gray-50 @endif">
                                    <div class="flex items-center gap-2 flex-1">
                                        <img src="{{ $match->homeTeam->image }}" class="w-6 h-6">
                                        <span class="@if($match->home_team_id === $game->team_id) font-semibold @endif">{{ $match->homeTeam->name }}</span>
                                    </div>
                                    <div class="px-4 font-bold">
                                        {{ $match->home_score }} - {{ $match->away_score }}
                                    </div>
                                    <div class="flex items-center gap-2 flex-1 justify-end">
                                        <span class="@if($match->away_team_id === $game->team_id) font-semibold @endif">{{ $match->awayTeam->name }}</span>
                                        <img src="{{ $match->awayTeam->image }}" class="w-6 h-6">
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="mt-4 text-center">
                            <a href="{{ route('game.calendar', $game->id) }}" class="text-sky-600 hover:text-sky-800 text-sm">View full calendar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
