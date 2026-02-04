@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="null"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 py-8">
            {{-- Season Complete Banner --}}
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-yellow-400 text-white px-6 py-3 rounded-full text-lg font-bold shadow-lg">
                    <span>&#9733;</span>
                    <span>SEASON {{ $game->season }} COMPLETE</span>
                    <span>&#9733;</span>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-8 space-y-8">

                    {{-- League Champion Section --}}
                    <div class="text-center py-6 bg-gradient-to-b from-amber-50 to-white rounded-lg border border-amber-200">
                        <div class="text-amber-600 font-semibold text-sm uppercase tracking-wide mb-2">League Champion</div>
                        <div class="flex justify-center items-center gap-3 mb-2">
                            <img src="{{ $champion->team->image }}" class="w-16 h-16">
                        </div>
                        <div class="text-2xl font-bold text-slate-900">{{ $champion->team->name }}</div>
                        <div class="text-lg text-slate-600">{{ $champion->points }} points</div>
                    </div>

                    {{-- Your Season Section --}}
                    <div class="text-center py-6">
                        <div class="text-slate-500 font-semibold text-sm uppercase tracking-wide mb-4">Your Season</div>

                        <div class="flex justify-center items-center gap-4 mb-4">
                            <img src="{{ $game->team->image }}" class="w-12 h-12">
                            <div>
                                <div class="text-xl font-bold text-slate-900">{{ $game->team->name }}</div>
                            </div>
                        </div>

                        <div class="inline-block bg-slate-100 rounded-lg px-6 py-4 mb-4">
                            <div class="text-3xl font-bold text-slate-900">
                                {{ $playerStanding->position }}{{ $playerStanding->position == 1 ? 'st' : ($playerStanding->position == 2 ? 'nd' : ($playerStanding->position == 3 ? 'rd' : 'th')) }} Place
                            </div>
                            <div class="text-lg text-slate-600">{{ $playerStanding->points }} points</div>
                        </div>

                        <div class="flex justify-center gap-6 text-sm text-slate-600">
                            <div><span class="font-semibold">W</span> {{ $playerTeamStats['won'] }}</div>
                            <div><span class="font-semibold">D</span> {{ $playerTeamStats['drawn'] }}</div>
                            <div><span class="font-semibold">L</span> {{ $playerTeamStats['lost'] }}</div>
                            <div><span class="font-semibold">GF</span> {{ $playerTeamStats['goalsFor'] }}</div>
                            <div><span class="font-semibold">GA</span> {{ $playerTeamStats['goalsAgainst'] }}</div>
                        </div>
                    </div>

                    {{-- Club Finances Section --}}
                    @if($finances)
                    <div class="border-t pt-6">
                        <div class="text-center text-slate-500 font-semibold text-sm uppercase tracking-wide mb-4">Club Finances</div>

                        <div class="grid grid-cols-2 gap-6">
                            {{-- Revenue --}}
                            <div class="bg-green-50 rounded-lg p-4">
                                <div class="text-xs text-green-600 uppercase tracking-wide font-semibold mb-3">Revenue</div>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-slate-600">TV Rights</span>
                                        <span class="font-medium text-slate-900">{{ $finances->formatted_tv_revenue }}</span>
                                    </div>
                                    @if($finances->performance_bonus > 0)
                                    <div class="flex justify-between">
                                        <span class="text-slate-600">Performance Bonus</span>
                                        <span class="font-medium text-slate-900">{{ \App\Support\Money::format($finances->performance_bonus) }}</span>
                                    </div>
                                    @endif
                                    @if($finances->cup_bonus > 0)
                                    <div class="flex justify-between">
                                        <span class="text-slate-600">Cup Prize Money</span>
                                        <span class="font-medium text-slate-900">{{ \App\Support\Money::format($finances->cup_bonus) }}</span>
                                    </div>
                                    @endif
                                    <div class="flex justify-between pt-2 border-t border-green-200">
                                        <span class="font-semibold text-green-700">Total Revenue</span>
                                        <span class="font-bold text-green-700">{{ $finances->formatted_total_revenue }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Expenses --}}
                            <div class="bg-red-50 rounded-lg p-4">
                                <div class="text-xs text-red-600 uppercase tracking-wide font-semibold mb-3">Expenses</div>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-slate-600">Wages</span>
                                        <span class="font-medium text-slate-900">{{ $finances->formatted_wage_expense }}</span>
                                    </div>
                                    @if($finances->transfer_expense > 0)
                                    <div class="flex justify-between">
                                        <span class="text-slate-600">Transfers</span>
                                        <span class="font-medium text-slate-900">{{ \App\Support\Money::format($finances->transfer_expense) }}</span>
                                    </div>
                                    @endif
                                    <div class="flex justify-between pt-2 border-t border-red-200">
                                        <span class="font-semibold text-red-700">Total Expenses</span>
                                        <span class="font-bold text-red-700">{{ $finances->formatted_total_expense }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Season Result --}}
                        <div class="mt-4 p-4 rounded-lg {{ $finances->season_profit_loss >= 0 ? 'bg-green-100' : 'bg-red-100' }}">
                            <div class="flex justify-between items-center">
                                <span class="font-semibold {{ $finances->season_profit_loss >= 0 ? 'text-green-800' : 'text-red-800' }}">
                                    Season {{ $finances->season_profit_loss >= 0 ? 'Profit' : 'Loss' }}
                                </span>
                                <span class="text-xl font-bold {{ $finances->season_profit_loss >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $finances->formatted_season_profit_loss }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center mt-2 pt-2 border-t {{ $finances->season_profit_loss >= 0 ? 'border-green-200' : 'border-red-200' }}">
                                <span class="text-sm text-slate-600">Club Balance</span>
                                <span class="font-semibold {{ $finances->balance >= 0 ? 'text-slate-900' : 'text-red-600' }}">
                                    {{ $finances->formatted_balance }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Season Awards Section --}}
                    <div class="border-t pt-6">
                        <div class="text-center text-slate-500 font-semibold text-sm uppercase tracking-wide mb-6">
                            <span>&#9733;</span> Season Awards <span>&#9733;</span>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            {{-- Top Scorer --}}
                            <div class="bg-slate-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-slate-500 uppercase tracking-wide mb-2">Top Scorer</div>
                                @if($topScorers->isNotEmpty())
                                    @php $scorer = $topScorers->first(); @endphp
                                    <div class="flex items-center justify-center gap-2 mb-1">
                                        <img src="{{ $scorer->team->image }}" class="w-5 h-5">
                                        <span class="font-semibold text-slate-900">{{ $scorer->player->name }}</span>
                                    </div>
                                    <div class="text-2xl font-bold text-slate-900">{{ $scorer->goals }}</div>
                                    <div class="text-xs text-slate-500">goals</div>
                                @else
                                    <div class="text-slate-400">No goals scored</div>
                                @endif
                            </div>

                            {{-- Most Assists --}}
                            <div class="bg-slate-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-slate-500 uppercase tracking-wide mb-2">Most Assists</div>
                                @if($topAssisters->isNotEmpty())
                                    @php $assister = $topAssisters->first(); @endphp
                                    <div class="flex items-center justify-center gap-2 mb-1">
                                        <img src="{{ $assister->team->image }}" class="w-5 h-5">
                                        <span class="font-semibold text-slate-900">{{ $assister->player->name }}</span>
                                    </div>
                                    <div class="text-2xl font-bold text-slate-900">{{ $assister->assists }}</div>
                                    <div class="text-xs text-slate-500">assists</div>
                                @else
                                    <div class="text-slate-400">No assists recorded</div>
                                @endif
                            </div>

                            {{-- Best Goalkeeper --}}
                            <div class="bg-slate-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-slate-500 uppercase tracking-wide mb-2">Best Goalkeeper</div>
                                @if($bestGoalkeeper)
                                    <div class="flex items-center justify-center gap-2 mb-1">
                                        <img src="{{ $bestGoalkeeper->team->image }}" class="w-5 h-5">
                                        <span class="font-semibold text-slate-900">{{ $bestGoalkeeper->player->name }}</span>
                                    </div>
                                    <div class="text-2xl font-bold text-slate-900">{{ $bestGoalkeeper->clean_sheets }}</div>
                                    <div class="text-xs text-slate-500">clean sheets</div>
                                    <div class="text-xs text-slate-400 mt-1">
                                        {{ number_format($bestGoalkeeper->goals_conceded / max(1, $bestGoalkeeper->appearances), 2) }} goals/game
                                    </div>
                                @else
                                    <div class="text-slate-400">Not enough data</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Player Development Preview --}}
                    @if($developmentPreview->isNotEmpty())
                    <div class="border-t pt-6">
                        <div class="text-center text-slate-500 font-semibold text-sm uppercase tracking-wide mb-4">Player Development</div>

                        <div class="space-y-2">
                            @foreach($developmentPreview as $item)
                                @php
                                    $change = $item['overallChange'];
                                    $icon = $change > 0 ? '&#9650;' : ($change < 0 ? '&#9660;' : '&#8212;');
                                    $colorClass = $change > 0 ? 'text-green-600' : ($change < 0 ? 'text-red-600' : 'text-slate-400');
                                    $statusLabel = match($item['status']) {
                                        'growing' => 'Growing',
                                        'peak' => 'Peak',
                                        'declining' => 'Declining',
                                        default => '',
                                    };
                                    $statusClass = match($item['status']) {
                                        'growing' => 'text-green-600',
                                        'peak' => 'text-slate-600',
                                        'declining' => 'text-orange-600',
                                        default => '',
                                    };
                                @endphp
                                <div class="flex items-center justify-between py-2 px-3 rounded {{ $loop->even ? 'bg-slate-50' : '' }}">
                                    <div class="flex items-center gap-2">
                                        <span class="{{ $colorClass }}">{!! $icon !!}</span>
                                        <span class="font-medium">{{ $item['player']->name }}</span>
                                        <span class="text-slate-400 text-sm">({{ $item['age'] }})</span>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-sm">
                                            <span class="text-slate-500">{{ $item['overallBefore'] }}</span>
                                            <span class="text-slate-400 mx-1">&#8594;</span>
                                            <span class="font-semibold">{{ $item['overallAfter'] }}</span>
                                            <span class="{{ $colorClass }} font-medium ml-1">
                                                ({{ $change > 0 ? '+' : '' }}{{ $change }})
                                            </span>
                                        </div>
                                        <span class="text-xs {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Start New Season CTA --}}
                    <div class="border-t pt-8 text-center">
                        <form method="post" action="{{ route('game.start-new-season', $game->id) }}" x-data="{ loading: false }" @submit="loading = true">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-2 bg-gradient-to-r from-green-600 to-green-500 hover:from-green-700 hover:to-green-600 text-white px-8 py-4 rounded-lg text-xl font-bold shadow-lg transition-all transform hover:scale-105"
                                    :disabled="loading">
                                <span x-show="!loading">&#9733; Start Season {{ (int)$game->season + 1 }} &#9733;</span>
                                <span x-show="loading" x-cloak>
                                    <svg class="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
