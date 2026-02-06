@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$nextMatch"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if($nextMatch)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-12 grid grid-cols-3 gap-12">
                    {{-- Left Column (2/3) - Main Content --}}
                    <div class="col-span-2 space-y-8">
                        {{-- Next Match --}}
                        <div>
                            {{-- Competition & Date Header --}}
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="font-semibold text-xl text-slate-900">Next Match</h3>
                                <div class="flex items-center gap-3 text-sm text-slate-500">
                                    <span>{{ $nextMatch->competition->name ?? 'League' }}</span>
                                    <span class="text-slate-300">|</span>
                                    <span>{{ $nextMatch->round_name ?? 'Matchday '.$nextMatch->round_number }}</span>
                                    <span class="text-slate-300">|</span>
                                    <span class="font-medium text-slate-700">{{ $nextMatch->scheduled_date->format('D, M j') }}</span>
                                </div>
                            </div>

                            {{-- Teams Face-off --}}
                            <div class="flex items-center justify-between py-4">
                                {{-- Home Team --}}
                                <div class="flex-1">
                                    <div class="flex items-center gap-4">
                                        <img src="{{ $nextMatch->homeTeam->image }}" class="w-20 h-20">
                                        <div>
                                            <h4 class="text-xl font-bold text-slate-900">{{ $nextMatch->homeTeam->name }}</h4>
                                            @if($homeStanding)
                                            <div class="text-sm text-slate-500 mt-1">
                                                {{ $homeStanding->position }}{{ $homeStanding->position == 1 ? 'st' : ($homeStanding->position == 2 ? 'nd' : ($homeStanding->position == 3 ? 'rd' : 'th')) }} &middot; {{ $homeStanding->points }} pts
                                            </div>
                                            @endif
                                            <div class="flex gap-1 mt-2">
                                                @php $homeForm = $nextMatch->home_team_id === $game->team_id ? $playerForm : $opponentForm; @endphp
                                                @forelse($homeForm as $result)
                                                    <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center
                                                        @if($result === 'W') bg-green-500 text-white
                                                        @elseif($result === 'D') bg-slate-400 text-white
                                                        @else bg-red-500 text-white @endif">
                                                        {{ $result }}
                                                    </span>
                                                @empty
                                                    <span class="text-slate-400 text-xs">No form</span>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- VS --}}
                                <div class="px-8 text-center">
                                    <div class="text-2xl font-black text-slate-300">vs</div>
                                </div>

                                {{-- Away Team --}}
                                <div class="flex-1">
                                    <div class="flex items-center gap-4 flex-row-reverse">
                                        <img src="{{ $nextMatch->awayTeam->image }}" class="w-20 h-20">
                                        <div class="text-right">
                                            <h4 class="text-xl font-bold text-slate-900">{{ $nextMatch->awayTeam->name }}</h4>
                                            @if($awayStanding)
                                            <div class="text-sm text-slate-500 mt-1">
                                                {{ $awayStanding->position }}{{ $awayStanding->position == 1 ? 'st' : ($awayStanding->position == 2 ? 'nd' : ($awayStanding->position == 3 ? 'rd' : 'th')) }} &middot; {{ $awayStanding->points }} pts
                                            </div>
                                            @endif
                                            <div class="flex gap-1 mt-2 justify-end">
                                                @php $awayForm = $nextMatch->away_team_id === $game->team_id ? $playerForm : $opponentForm; @endphp
                                                @forelse($awayForm as $result)
                                                    <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center
                                                        @if($result === 'W') bg-green-500 text-white
                                                        @elseif($result === 'D') bg-slate-400 text-white
                                                        @else bg-red-500 text-white @endif">
                                                        {{ $result }}
                                                    </span>
                                                @empty
                                                    <span class="text-slate-400 text-xs">No form</span>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Set Lineup Button --}}
                            <div class="mt-4">
                                <a href="{{ route('game.lineup', [$game->id, $nextMatch->id]) }}"
                                   class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                    Set Lineup
                                </a>
                            </div>
                        </div>

                        {{-- Upcoming Fixtures --}}
                        @if($upcomingFixtures->isNotEmpty())
                        <div class="pt-8 border-t">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-semibold text-xl text-slate-900">Upcoming Fixtures</h3>
                                <a href="{{ route('game.calendar', $game->id) }}" class="text-sm text-sky-600 hover:text-sky-800">
                                    Full Calendar &rarr;
                                </a>
                            </div>

                            <div class="space-y-2">
                                @foreach($upcomingFixtures->take(5) as $fixture)
                                    <x-fixture-row :match="$fixture" :game="$game" :show-score="false" :highlight-next="false" />
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Right Column (1/3) - Alerts & Notifications --}}
                    <div class="space-y-8">
                        {{-- Squad Status --}}
                        @php
                            $hasSquadAlerts = !empty($squadAlerts['injured']) || !empty($squadAlerts['suspended']) || !empty($squadAlerts['lowFitness']) || !empty($squadAlerts['yellowCardRisk']);
                            $hasScoutNotification = isset($scoutReport) && $scoutReport;
                        @endphp
                        @if($hasSquadAlerts || $hasScoutNotification)
                        <div>
                            <h4 class="font-semibold text-xl text-slate-900 mb-4">Squad Status</h4>

                            <div class="space-y-4">
                                {{-- Scout Report Notification --}}
                                @if($hasScoutNotification)
                                <div>
                                    @if($scoutReport->isCompleted())
                                    <a href="{{ route('game.scouting', $game->id) }}" class="block p-3 bg-sky-50 border border-sky-200 rounded-lg hover:bg-sky-100 transition-colors">
                                        <div class="flex items-center gap-2 text-sky-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                            </svg>
                                            <span class="font-medium text-sm">Scout Report Ready</span>
                                        </div>
                                        <p class="text-xs text-sky-600 mt-1 pl-6">{{ $scoutReport->players->count() }} players available to review.</p>
                                    </a>
                                    @else
                                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg">
                                        <div class="flex items-center gap-2 text-slate-600">
                                            <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                            </svg>
                                            <span class="font-medium text-sm">Scout Searching...</span>
                                        </div>
                                        <p class="text-xs text-slate-500 mt-1 pl-6">{{ $scoutReport->weeks_remaining }} week{{ $scoutReport->weeks_remaining > 1 ? 's' : '' }} remaining</p>
                                    </div>
                                    @endif
                                </div>
                                @endif
                                {{-- Injured --}}
                                @if(!empty($squadAlerts['injured']))
                                <div>
                                    <div class="flex items-center gap-2 text-red-600 mb-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <span class="font-medium text-sm">Injured</span>
                                    </div>
                                    @foreach($squadAlerts['injured'] as $alert)
                                    <div class="flex items-center justify-between text-sm pl-6 py-1">
                                        <span class="text-slate-700">{{ $alert['player']->name }}</span>
                                        <span class="text-slate-400 text-xs">{{ $alert['daysRemaining'] }}d</span>
                                    </div>
                                    @endforeach
                                </div>
                                @endif

                                {{-- Suspended --}}
                                @if(!empty($squadAlerts['suspended']))
                                <div>
                                    <div class="flex items-center gap-2 text-orange-600 mb-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                        <span class="font-medium text-sm">Suspended</span>
                                    </div>
                                    @foreach($squadAlerts['suspended'] as $alert)
                                    <div class="flex items-center justify-between text-sm pl-6 py-1">
                                        <span class="text-slate-700">{{ $alert['player']->name }}</span>
                                        <span class="text-slate-400 text-xs">{{ $alert['matchesRemaining'] }}m ({{ $alert['competition'] }})</span>
                                    </div>
                                    @endforeach
                                </div>
                                @endif

                                {{-- Low Fitness --}}
                                @if(!empty($squadAlerts['lowFitness']))
                                <div>
                                    <div class="flex items-center gap-2 text-amber-600 mb-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        <span class="font-medium text-sm">Low Fitness</span>
                                    </div>
                                    @foreach($squadAlerts['lowFitness'] as $alert)
                                    <div class="flex items-center justify-between text-sm pl-6 py-1">
                                        <span class="text-slate-700">{{ $alert['player']->name }}</span>
                                        <span class="text-amber-600 text-xs font-medium">{{ $alert['fitness'] }}%</span>
                                    </div>
                                    @endforeach
                                </div>
                                @endif

                                {{-- Yellow Card Risk --}}
                                @if(!empty($squadAlerts['yellowCardRisk']))
                                <div>
                                    <div class="flex items-center gap-2 text-yellow-600 mb-2">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <rect x="6" y="3" width="12" height="18" rx="2" />
                                        </svg>
                                        <span class="font-medium text-sm">Card Risk</span>
                                    </div>
                                    @foreach($squadAlerts['yellowCardRisk'] as $alert)
                                    <div class="flex items-center justify-between text-sm pl-6 py-1">
                                        <span class="text-slate-700">{{ $alert['player']->name }}</span>
                                        <span class="text-yellow-600 text-xs font-medium">{{ $alert['yellowCards'] }} YC</span>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        {{-- Transfer Offers --}}
                        @php
                            $hasTransferAlerts = !empty($transferAlerts['newOffers']) || !empty($transferAlerts['expiringOffers']);
                        @endphp
                        @if($hasTransferAlerts)
                        <div class="@if($hasSquadAlerts) pt-8 border-t @endif">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-xl text-slate-900">Transfer Offers</h4>
                                <a href="{{ route('game.transfers', $game->id) }}" class="text-sm text-sky-600 hover:text-sky-800">
                                    View All
                                </a>
                            </div>

                            <div class="space-y-3">
                                @foreach($transferAlerts['expiringOffers'] as $alert)
                                <div class="p-3 bg-amber-50 rounded-lg">
                                    <div class="text-sm font-medium text-slate-900">{{ $alert['playerName'] }}</div>
                                    <div class="text-xs text-slate-600 mt-1">
                                        {{ $alert['teamName'] }} &middot;
                                        <span class="text-green-600 font-medium">{{ $alert['fee'] }}</span>
                                    </div>
                                    <div class="text-xs text-amber-600 font-medium mt-1">
                                        Expires in {{ $alert['daysLeft'] }}d
                                    </div>
                                </div>
                                @endforeach

                                @foreach($transferAlerts['newOffers'] as $alert)
                                <div class="p-3 bg-slate-50 rounded-lg">
                                    <div class="text-sm font-medium text-slate-900">{{ $alert['playerName'] }}</div>
                                    <div class="text-xs text-slate-600 mt-1">
                                        {{ $alert['teamName'] }} &middot;
                                        <span class="text-green-600 font-medium">{{ $alert['fee'] }}</span>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- No Alerts State --}}
                        @if(!$hasSquadAlerts && !$hasTransferAlerts)
                        <div>
                            <h4 class="font-semibold text-xl text-slate-900 mb-4">Notifications</h4>
                            <div class="text-center py-8">
                                <div class="text-slate-300 mb-2">
                                    <svg class="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <p class="text-sm text-slate-400">All clear</p>
                            </div>
                        </div>
                        @endif

                        {{-- Club Finances Summary --}}
                        @if($finances)
                        <div class="pt-8 border-t">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-xl text-slate-900">Club Finances</h4>
                                <a href="{{ route('game.finances', $game->id) }}" class="text-sm text-sky-600 hover:text-sky-800">
                                    Details &rarr;
                                </a>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                    <span class="text-sm text-slate-600">Projected Position</span>
                                    <span class="font-bold text-slate-900">{{ $finances->projected_position }}</span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                    <span class="text-sm text-slate-600">Transfer Budget</span>
                                    <span class="font-bold text-sky-700">{{ $investment?->formatted_transfer_budget ?? '€0' }}</span>
                                </div>
                                @if($finances->carried_debt > 0)
                                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                                    <span class="text-sm text-red-600">Carried Debt</span>
                                    <span class="font-bold text-red-700">{{ $finances->formatted_carried_debt }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @else
            {{-- Season Complete State --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-12 text-center">
                    <div class="text-6xl mb-4">&#127942;</div>
                    <h2 class="text-3xl font-bold text-slate-900 mb-2">Season Complete!</h2>
                    <p class="text-slate-500 mb-8">Congratulations on finishing the {{ $game->season }} season.</p>
                    <a href="{{ route('game.season-end', $game->id) }}"
                       class="inline-flex items-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors">
                        View Season Summary
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
