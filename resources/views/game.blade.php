@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$nextMatch"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Next Match Hero --}}
            @if($nextMatch)
            <div class="bg-gradient-to-br from-orange-50 to-orange-200 overflow-hidden shadow-lg sm:rounded-lg">
                <div class="p-8">
                    {{-- Competition & Date --}}
                    <div class="text-center mb-6">
                        <span class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 rounded-full text-sm text-slate-600">
                            <span>{{ $nextMatch->competition->name ?? 'League' }}</span>
                            <span class="text-slate-500">|</span>
                            <span>{{ $nextMatch->round_name ?? 'Matchday '.$nextMatch->round_number }}</span>
                            <span class="text-slate-500">|</span>
                            <span>{{ $nextMatch->scheduled_date->format('D, M j') }}</span>
                        </span>
                    </div>

                    {{-- Teams --}}
                    <div class="flex items-center justify-center gap-8 md:gap-16">
                        {{-- Home Team --}}
                        <div class="flex-1 text-center">
                            <img src="{{ $nextMatch->homeTeam->image }}" class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-3">
                            <h3 class="text-3xl font-bold text-slate-800">
                                {{ $nextMatch->homeTeam->name }}
                            </h3>
                            @if($nextMatch->home_team_id === $game->team_id)
                                {{-- Player's form --}}
                                <div class="flex justify-center gap-1 mt-2">
                                    @forelse($playerForm as $result)
                                        <span class="w-6 h-6 rounded text-xs font-bold flex items-center justify-center
                                            @if($result === 'W') bg-green-500 text-white
                                            @elseif($result === 'D') bg-slate-500 text-white
                                            @else bg-red-500 text-white @endif">
                                            {{ $result }}
                                        </span>
                                    @empty
                                        <span class="text-slate-600 text-sm">No matches yet</span>
                                    @endforelse
                                </div>
                            @else
                                {{-- Opponent's form --}}
                                <div class="flex justify-center gap-1 mt-2">
                                    @forelse($opponentForm as $result)
                                        <span class="w-6 h-6 rounded text-xs font-bold flex items-center justify-center
                                            @if($result === 'W') bg-green-500/80 text-white
                                            @elseif($result === 'D') bg-slate-500/80 text-white
                                            @else bg-red-500/80 text-white @endif">
                                            {{ $result }}
                                        </span>
                                    @empty
                                        <span class="text-slate-600 text-sm">No matches yet</span>
                                    @endforelse
                                </div>
                            @endif
                        </div>

                        {{-- VS --}}
                        <div class="flex flex-col items-center">
                            <div class="text-3xl md:text-4xl font-black text-slate-800">&mdash;</div>
                        </div>

                        {{-- Away Team --}}
                        <div class="flex-1 text-center">
                            <img src="{{ $nextMatch->awayTeam->image }}" class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-3">
                            <h3 class="text-3xl font-bold text-slate-800">
                                {{ $nextMatch->awayTeam->name }}
                            </h3>
                            @if($nextMatch->away_team_id === $game->team_id)
                                {{-- Player's form --}}
                                <div class="flex justify-center gap-1 mt-2">
                                    @forelse($playerForm as $result)
                                        <span class="w-6 h-6 rounded text-xs font-bold flex items-center justify-center
                                            @if($result === 'W') bg-green-500 text-white
                                            @elseif($result === 'D') bg-slate-500 text-white
                                            @else bg-red-500 text-white @endif">
                                            {{ $result }}
                                        </span>
                                    @empty
                                        <span class="text-slate-500 text-sm">No matches yet</span>
                                    @endforelse
                                </div>
                            @else
                                {{-- Opponent's form --}}
                                <div class="flex justify-center gap-1 mt-2">
                                    @forelse($opponentForm as $result)
                                        <span class="w-6 h-6 rounded text-xs font-bold flex items-center justify-center
                                            @if($result === 'W') bg-green-500/80 text-white
                                            @elseif($result === 'D') bg-slate-500/80 text-white
                                            @else bg-red-500/80 text-white @endif">
                                            {{ $result }}
                                        </span>
                                    @empty
                                        <span class="text-slate-500 text-sm">No matches yet</span>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @else
            {{-- Season Complete State --}}
            <div class="bg-gradient-to-br from-amber-600 to-amber-700 overflow-hidden shadow-lg sm:rounded-lg">
                <div class="p-8 text-center">
                    <div class="text-5xl mb-4">&#127942;</div>
                    <h2 class="text-2xl font-bold text-white mb-2">Season Complete!</h2>
                    <p class="text-amber-100 mb-6">Congratulations on finishing the {{ $game->season }} season.</p>
                    <a href="{{ route('game.season-end', $game->id) }}"
                       class="inline-flex items-center px-6 py-3 bg-white/20 hover:bg-white/30 border border-white/30 rounded-lg text-white font-semibold transition-colors">
                        View Season Summary
                    </a>
                </div>
            </div>
            @endif

            {{-- Squad Alerts --}}
            @php
                $hasAlerts = !empty($squadAlerts['injured']) || !empty($squadAlerts['suspended']) || !empty($squadAlerts['lowFitness']) || !empty($squadAlerts['yellowCardRisk']);
            @endphp
            @if($hasAlerts)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="font-semibold text-lg text-slate-900 mb-4">Squad Alerts</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        {{-- Injured --}}
                        @if(!empty($squadAlerts['injured']))
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-red-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span class="font-semibold text-sm">Injured ({{ count($squadAlerts['injured']) }})</span>
                            </div>
                            @foreach($squadAlerts['injured'] as $alert)
                            <div class="flex items-center justify-between text-sm pl-7">
                                <span class="text-slate-700">{{ $alert['player']->name }}</span>
                                <span class="text-slate-500 text-xs">{{ $alert['daysRemaining'] }}d</span>
                            </div>
                            @endforeach
                        </div>
                        @endif

                        {{-- Suspended --}}
                        @if(!empty($squadAlerts['suspended']))
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-orange-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                                <span class="font-semibold text-sm">Suspended ({{ count($squadAlerts['suspended']) }})</span>
                            </div>
                            @foreach($squadAlerts['suspended'] as $alert)
                            <div class="flex items-center justify-between text-sm pl-7">
                                <span class="text-slate-700">{{ $alert['player']->name }}</span>
                                <span class="text-slate-500 text-xs">{{ $alert['matchesRemaining'] }} match{{ $alert['matchesRemaining'] > 1 ? 'es' : '' }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif

                        {{-- Low Fitness --}}
                        @if(!empty($squadAlerts['lowFitness']))
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-amber-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span class="font-semibold text-sm">Low Fitness ({{ count($squadAlerts['lowFitness']) }})</span>
                            </div>
                            @foreach($squadAlerts['lowFitness'] as $alert)
                            <div class="flex items-center justify-between text-sm pl-7">
                                <span class="text-slate-700">{{ $alert['player']->name }}</span>
                                <span class="text-amber-600 text-xs font-medium">{{ $alert['fitness'] }}%</span>
                            </div>
                            @endforeach
                        </div>
                        @endif

                        {{-- Yellow Card Risk --}}
                        @if(!empty($squadAlerts['yellowCardRisk']))
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-yellow-600">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <rect x="6" y="3" width="12" height="18" rx="2" />
                                </svg>
                                <span class="font-semibold text-sm">Card Risk ({{ count($squadAlerts['yellowCardRisk']) }})</span>
                            </div>
                            @foreach($squadAlerts['yellowCardRisk'] as $alert)
                            <div class="flex items-center justify-between text-sm pl-7">
                                <span class="text-slate-700">{{ $alert['player']->name }}</span>
                                <span class="text-yellow-600 text-xs font-medium">{{ $alert['yellowCards'] }} yellows</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- Two Column Layout --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Your Position Card --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="font-semibold text-lg text-slate-900 mb-4">Your Position</h3>

                        @if($playerStanding)
                        <div class="flex items-center gap-6">
                            {{-- Position Badge --}}
                            <div class="text-center">
                                <div class="text-5xl font-black text-slate-900">{{ $playerStanding->position }}</div>
                                <div class="text-sm text-slate-500 mt-1">
                                    @if($playerStanding->position === 1)
                                        1st
                                    @elseif($playerStanding->position === 2)
                                        2nd
                                    @elseif($playerStanding->position === 3)
                                        3rd
                                    @else
                                        {{ $playerStanding->position }}th
                                    @endif
                                </div>
                            </div>

                            {{-- Stats --}}
                            <div class="flex-1 space-y-3">
                                {{-- Points --}}
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600">Points</span>
                                    <span class="font-bold text-slate-900">{{ $playerStanding->points }}</span>
                                </div>

                                {{-- Record --}}
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600">Record</span>
                                    <span class="font-medium text-slate-900">
                                        {{ $playerStanding->won }}W - {{ $playerStanding->drawn }}D - {{ $playerStanding->lost }}L
                                    </span>
                                </div>

                                {{-- Goals --}}
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600">Goals</span>
                                    <span class="font-medium text-slate-900">
                                        {{ $playerStanding->goals_for }} scored, {{ $playerStanding->goals_against }} conceded
                                        <span class="text-sm @if($playerStanding->goal_difference > 0) text-green-600 @elseif($playerStanding->goal_difference < 0) text-red-600 @else text-slate-400 @endif">
                                            ({{ $playerStanding->goal_difference > 0 ? '+' : '' }}{{ $playerStanding->goal_difference }})
                                        </span>
                                    </span>
                                </div>

                                {{-- Gap to Leader --}}
                                @if($leaderStanding && $playerStanding->position > 1)
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600">Gap to 1st</span>
                                    <span class="font-medium text-red-600">
                                        -{{ $leaderStanding->points - $playerStanding->points }} pts
                                    </span>
                                </div>
                                @elseif($playerStanding->position === 1)
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600">Status</span>
                                    <span class="font-medium text-green-600">League Leader!</span>
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- Form --}}
                        <div class="mt-4 pt-4 border-t">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-600">Recent Form</span>
                                <div class="flex gap-1">
                                    @forelse($playerForm as $result)
                                        <span class="w-7 h-7 rounded text-xs font-bold flex items-center justify-center
                                            @if($result === 'W') bg-green-500 text-white
                                            @elseif($result === 'D') bg-slate-400 text-white
                                            @else bg-red-500 text-white @endif">
                                            {{ $result }}
                                        </span>
                                    @empty
                                        <span class="text-slate-400 text-sm">No matches played yet</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                        @else
                        <p class="text-slate-500">No standings data available yet.</p>
                        @endif

                        <div class="mt-4 pt-4 border-t text-center">
                            <a href="{{ route('game.standings', $game->id) }}" class="text-sky-600 hover:text-sky-800 text-sm font-medium">
                                View Full Standings
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Upcoming Fixtures Card --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="font-semibold text-lg text-slate-900 mb-4">Upcoming Fixtures</h3>

                        @if($upcomingFixtures->isEmpty())
                            <p class="text-slate-500">No upcoming fixtures.</p>
                        @else
                            <div class="space-y-3">
                                @foreach($upcomingFixtures as $index => $fixture)
                                    @php
                                        $isHome = $fixture->home_team_id === $game->team_id;
                                        $opponent = $isHome ? $fixture->awayTeam : $fixture->homeTeam;
                                    @endphp
                                    <div class="flex items-center gap-4 p-3 rounded-lg @if($index === 0) bg-sky-50 border border-sky-100 @else hover:bg-slate-50 @endif">
                                        {{-- Date --}}
                                        <div class="text-center w-14">
                                            <div class="text-xs text-slate-500 uppercase">{{ $fixture->scheduled_date->format('M') }}</div>
                                            <div class="text-xl font-bold text-slate-900">{{ $fixture->scheduled_date->format('j') }}</div>
                                        </div>

                                        {{-- Opponent --}}
                                        <div class="flex-1 flex items-center gap-3">
                                            <img src="{{ $opponent->image }}" class="w-8 h-8">
                                            <div>
                                                <div class="font-medium text-slate-900">
                                                    {{ $isHome ? 'vs' : '@' }} {{ $opponent->name }}
                                                </div>
                                                <div class="text-xs text-slate-500">
                                                    {{ $fixture->competition->name ?? 'League' }}
                                                    @if($fixture->round_name)
                                                        &middot; {{ $fixture->round_name }}
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Home/Away Badge --}}
                                        <div class="text-xs font-semibold px-2 py-1 rounded @if($isHome) bg-green-100 text-green-700 @else bg-slate-100 text-slate-600 @endif">
                                            {{ $isHome ? 'H' : 'A' }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-4 pt-4 border-t text-center">
                            <a href="{{ route('game.calendar', $game->id) }}" class="text-sky-600 hover:text-sky-800 text-sm font-medium">
                                View Full Calendar
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
