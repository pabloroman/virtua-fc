@php
/** @var App\Models\Game $game */
/** @var App\Models\GameFinances $finances */
/** @var int $availableSurplus */
/** @var array $tiers */
/** @var array $tierThresholds */
@endphp

<x-app-layout>
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Welcome Header --}}
            <div class="text-center mb-8">
                <img src="{{ $game->team->image }}" alt="{{ $game->team->name }}" class="w-20 h-20 mx-auto mb-4">
                <h1 class="text-3xl font-bold text-white mb-1">Welcome to {{ $game->team->name }}</h1>
                <p class="text-slate-500">Season {{ $game->season }}</p>
            </div>

            {{-- Flash Messages --}}
            @if(session('error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                {{ session('error') }}
            </div>
            @endif

            {{-- Club Briefing --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
                <div class="grid grid-cols-2 gap-8">
                    {{-- Left Column --}}
                    <div class="space-y-6">
                        {{-- Stadium --}}
                        <div>
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Home Ground</h3>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $game->team->stadium_name ?? 'Club Stadium' }}</div>
                                    <div class="text-sm text-slate-500">{{ number_format($game->team->stadium_seats ?? 0) }} seats</div>
                                </div>
                            </div>
                        </div>

                        {{-- Squad Stats --}}
                        <div>
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Squad</h3>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <div class="text-2xl font-bold text-slate-900">{{ $squadSize }}</div>
                                    <div class="text-xs text-slate-500">Players</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-slate-900">{{ $averageAge }}</div>
                                    <div class="text-xs text-slate-500">Avg Age</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-slate-900">{{ \App\Support\Money::format($squadValue) }}</div>
                                    <div class="text-xs text-slate-500">Value</div>
                                </div>
                            </div>
                        </div>

                        {{-- Board Expectations --}}
                        <div>
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Board Expectations</h3>
                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-amber-700 font-bold text-sm">{{ $finances->projected_position ?? '?' }}</span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-amber-900">Finish {{ $finances->projected_position ?? '?' }}{{ $finances->projected_position == 1 ? 'st' : ($finances->projected_position == 2 ? 'nd' : ($finances->projected_position == 3 ? 'rd' : 'th')) }} or better</div>
                                        <div class="text-xs text-amber-700 mt-0.5">Based on squad strength and financial projections</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Right Column --}}
                    <div class="space-y-6">
                        {{-- Key Players --}}
                        <div>
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Key Players</h3>
                            <div class="space-y-2">
                                @foreach($keyPlayers as $player)
                                <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-slate-100' : '' }}">
                                    <div class="flex items-center gap-3">
                                        <span class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs font-semibold {{ $player->position_display['text'] }}">
                                            {{ $player->position_display['abbreviation'] }}
                                        </span>
                                        <div>
                                            <div class="font-medium text-slate-900">{{ $player->player->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $player->age }} years old</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-slate-900">{{ (int) round(($player->game_technical_ability + $player->game_physical_ability) / 2) }}</div>
                                        <div class="text-xs text-slate-400">OVR</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- First Match --}}
                        @if($nextMatch)
                        <div>
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">First Match</h3>
                            <div class="bg-slate-50 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium px-2 py-0.5 rounded {{ $isHomeMatch ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' }}">
                                            {{ $isHomeMatch ? 'HOME' : 'AWAY' }}
                                        </span>
                                        <span class="text-sm text-slate-500">vs</span>
                                        <img src="{{ $opponent->image }}" alt="{{ $opponent->name }}" class="w-6 h-6">
                                        <span class="font-medium text-slate-900">{{ $opponent->name }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 mt-2 text-xs text-slate-500">
                                    <span>{{ $nextMatch->scheduled_date->format('D, M j') }}</span>
                                    <span>&middot;</span>
                                    <span>{{ $nextMatch->competition->name }}</span>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Budget Allocation --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Season Budget</h2>
                        <p class="text-sm text-slate-500">Allocate funds across infrastructure and transfers</p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-slate-900">{{ \App\Support\Money::format($availableSurplus) }}</div>
                        <div class="text-xs text-slate-500">Available</div>
                    </div>
                </div>

                <x-budget-allocation
                    :available-surplus="$availableSurplus"
                    :tiers="$tiers"
                    :tier-thresholds="$tierThresholds"
                    :form-action="route('game.onboarding.complete', $game->id)"
                    submit-label="Begin Season"
                />
            </div>

        </div>
    </div>
</x-app-layout>
