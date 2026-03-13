@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('app.calendar') }}</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
            {{-- Left Column (2/3) - Calendar --}}
            <div class="md:col-span-2 space-y-8">
                @foreach($calendar as $month => $matches)
                    <x-section-card :title="$month">
                        <div class="divide-y divide-border-default">
                            @foreach($matches as $match)
                                <x-fixture-row :match="$match" :game="$game" :next-match-id="$nextMatchId" />
                            @endforeach
                        </div>
                    </x-section-card>
                @endforeach
            </div>

            {{-- Right Column (1/3) - Season Stats --}}
            <div class="space-y-6">
                {{-- Record --}}
                <x-section-card :title="__('game.record')">
                    <div class="p-4 md:p-6">
                        <div class="flex items-center justify-between text-2xl font-bold mb-2">
                            <span class="text-accent-green">{{ $seasonStats['wins'] }}W</span>
                            <span class="text-text-secondary">{{ $seasonStats['draws'] }}D</span>
                            <span class="text-red-500">{{ $seasonStats['losses'] }}L</span>
                        </div>
                        @if($seasonStats['played'] > 0)
                        <div class="w-full bg-bar-track rounded-full h-2 overflow-hidden">
                            @php
                                $winWidth = ($seasonStats['wins'] / $seasonStats['played']) * 100;
                                $drawWidth = ($seasonStats['draws'] / $seasonStats['played']) * 100;
                            @endphp
                            <div class="h-2 flex">
                                <div class="bg-accent-green" style="width: {{ $winWidth }}%"></div>
                                <div class="bg-surface-600" style="width: {{ $drawWidth }}%"></div>
                            </div>
                        </div>
                        <div class="text-xs text-text-muted mt-1 text-right">{{ __('game.win_rate', ['percent' => $seasonStats['winPercent']]) }}</div>
                        @endif
                    </div>
                </x-section-card>

                {{-- Form --}}
                @if(count($seasonStats['form']) > 0)
                <x-section-card :title="__('game.form')">
                    <div class="p-4 md:p-6">
                        <div class="flex gap-1">
                            @foreach($seasonStats['form'] as $result)
                                <span class="w-8 h-8 rounded text-sm font-bold flex items-center justify-center
                                    @if($result === 'W') bg-accent-green text-white
                                    @elseif($result === 'D') bg-surface-600 text-white
                                    @else bg-accent-red text-white @endif">
                                    {{ $result }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </x-section-card>
                @endif

                {{-- Goals --}}
                <x-section-card :title="__('game.goals')">
                    <div class="p-4 md:p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center p-3 bg-surface-700/50 rounded-lg">
                                <div class="text-2xl font-bold text-text-primary">{{ $seasonStats['goalsFor'] }}</div>
                                <div class="text-xs text-text-muted">{{ __('game.scored') }}</div>
                            </div>
                            <div class="text-center p-3 bg-surface-700/50 rounded-lg">
                                <div class="text-2xl font-bold text-text-primary">{{ $seasonStats['goalsAgainst'] }}</div>
                                <div class="text-xs text-text-muted">{{ __('game.conceded') }}</div>
                            </div>
                        </div>
                    </div>
                </x-section-card>

                {{-- Home/Away Breakdown --}}
                <x-section-card :title="__('game.home_vs_away')">
                    <div class="p-4 md:p-6 space-y-3">
                        {{-- Home --}}
                        <div class="p-3 bg-accent-green/10 rounded-lg">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-semibold text-accent-green">{{ __('game.home') }}</span>
                                <span class="text-sm font-bold text-accent-green">{{ $seasonStats['home']['points'] }} {{ __('game.pts') }}</span>
                            </div>
                            <div class="text-xs text-text-secondary">
                                {{ $seasonStats['home']['wins'] }}W {{ $seasonStats['home']['draws'] }}D {{ $seasonStats['home']['losses'] }}L
                                <span class="text-text-secondary mx-1">&middot;</span>
                                {{ $seasonStats['home']['goalsFor'] }}-{{ $seasonStats['home']['goalsAgainst'] }}
                            </div>
                        </div>
                        {{-- Away --}}
                        <div class="p-3 bg-surface-700 rounded-lg">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-semibold text-text-body">{{ __('game.away') }}</span>
                                <span class="text-sm font-bold text-text-body">{{ $seasonStats['away']['points'] }} {{ __('game.pts') }}</span>
                            </div>
                            <div class="text-xs text-text-secondary">
                                {{ $seasonStats['away']['wins'] }}W {{ $seasonStats['away']['draws'] }}D {{ $seasonStats['away']['losses'] }}L
                                <span class="text-text-secondary mx-1">&middot;</span>
                                {{ $seasonStats['away']['goalsFor'] }}-{{ $seasonStats['away']['goalsAgainst'] }}
                            </div>
                        </div>
                    </div>
                </x-section-card>
            </div>
        </div>
    </div>
</x-app-layout>
