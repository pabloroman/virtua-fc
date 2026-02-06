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
                        <div class="text-center text-slate-500 font-semibold text-sm uppercase tracking-wide mb-4">Season Finances</div>

                        {{-- Projected vs Actual Comparison --}}
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="bg-slate-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-slate-500 uppercase tracking-wide mb-1">Projected Position</div>
                                <div class="text-2xl font-bold text-slate-700">{{ $finances->projected_position }}</div>
                            </div>
                            <div class="bg-slate-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-slate-500 uppercase tracking-wide mb-1">Actual Position</div>
                                <div class="text-2xl font-bold text-slate-900">{{ $playerStanding->position }}</div>
                            </div>
                            <div class="rounded-lg p-4 text-center {{ $playerStanding->position <= $finances->projected_position ? 'bg-green-50' : 'bg-red-50' }}">
                                <div class="text-xs {{ $playerStanding->position <= $finances->projected_position ? 'text-green-600' : 'text-red-600' }} uppercase tracking-wide mb-1">Difference</div>
                                <div class="text-2xl font-bold {{ $playerStanding->position <= $finances->projected_position ? 'text-green-700' : 'text-red-700' }}">
                                    @if($playerStanding->position < $finances->projected_position)
                                        +{{ $finances->projected_position - $playerStanding->position }}
                                    @elseif($playerStanding->position > $finances->projected_position)
                                        -{{ $playerStanding->position - $finances->projected_position }}
                                    @else
                                        0
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Revenue Comparison --}}
                        <div class="border rounded-lg overflow-hidden mb-4">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-100">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-slate-600">Revenue Source</th>
                                        <th class="px-4 py-2 text-right text-slate-600">Projected</th>
                                        <th class="px-4 py-2 text-right text-slate-600">Actual</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr>
                                        <td class="px-4 py-2">TV Rights</td>
                                        <td class="px-4 py-2 text-right">{{ $finances->formatted_projected_tv_revenue }}</td>
                                        <td class="px-4 py-2 text-right font-medium">{{ $finances->formatted_actual_tv_revenue ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2">Matchday</td>
                                        <td class="px-4 py-2 text-right">{{ $finances->formatted_projected_matchday_revenue }}</td>
                                        <td class="px-4 py-2 text-right font-medium">{{ $finances->formatted_actual_matchday_revenue ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2">Prizes</td>
                                        <td class="px-4 py-2 text-right">{{ \App\Support\Money::format($finances->projected_prize_revenue) }}</td>
                                        <td class="px-4 py-2 text-right font-medium">{{ $finances->formatted_actual_prize_revenue ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2">Commercial</td>
                                        <td class="px-4 py-2 text-right">{{ $finances->formatted_projected_commercial_revenue }}</td>
                                        <td class="px-4 py-2 text-right font-medium">{{ $finances->formatted_actual_commercial_revenue ?? '-' }}</td>
                                    </tr>
                                    @if($finances->actual_transfer_income > 0)
                                    <tr>
                                        <td class="px-4 py-2">Transfer Sales</td>
                                        <td class="px-4 py-2 text-right text-slate-400">-</td>
                                        <td class="px-4 py-2 text-right font-medium text-green-600">{{ $finances->formatted_actual_transfer_income }}</td>
                                    </tr>
                                    @endif
                                    <tr class="bg-slate-50 font-semibold">
                                        <td class="px-4 py-2">Total Revenue</td>
                                        <td class="px-4 py-2 text-right">{{ $finances->formatted_projected_total_revenue }}</td>
                                        <td class="px-4 py-2 text-right">{{ $finances->formatted_actual_total_revenue ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2">Wages</td>
                                        <td class="px-4 py-2 text-right text-red-600">-{{ $finances->formatted_projected_wages }}</td>
                                        <td class="px-4 py-2 text-right font-medium text-red-600">-{{ $finances->formatted_actual_wages ?? '-' }}</td>
                                    </tr>
                                    <tr class="bg-slate-100 font-bold">
                                        <td class="px-4 py-2">Surplus</td>
                                        <td class="px-4 py-2 text-right">{{ $finances->formatted_projected_surplus }}</td>
                                        <td class="px-4 py-2 text-right">{{ $finances->formatted_actual_surplus ?? '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- Variance Result --}}
                        @if($finances->variance !== null)
                        <div class="p-4 rounded-lg {{ $finances->variance >= 0 ? 'bg-green-100' : 'bg-red-100' }}">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="font-semibold {{ $finances->variance >= 0 ? 'text-green-800' : 'text-red-800' }}">
                                        @if($finances->variance >= 0)
                                            Overperformed!
                                        @else
                                            Underperformed
                                        @endif
                                    </span>
                                    <p class="text-sm {{ $finances->variance >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">
                                        @if($finances->variance >= 0)
                                            No debt incurred this season.
                                        @else
                                            This debt will reduce next season's budget.
                                        @endif
                                    </p>
                                </div>
                                <span class="text-2xl font-bold {{ $finances->variance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $finances->formatted_variance }}
                                </span>
                            </div>
                        </div>
                        @endif
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
