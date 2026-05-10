@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <x-match-detail-modal />

    @php
        $nextMatch = collect($calendar)->flatten(1)->first(fn ($m) => empty($m->is_placeholder) && $m->id === $nextMatchId);
        $nextMatchComp = $nextMatch?->competition_id;
    @endphp

    <div x-data="{ comp: 'all' }" class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-4 flex items-center justify-between gap-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('app.calendar') }}</h2>
            @if($nextMatchId)
                <button type="button"
                    @click="document.getElementById('calendar-today')?.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-accent-blue/10 hover:bg-accent-blue/20 text-accent-blue text-xs font-semibold uppercase tracking-wider transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                    </svg>
                    <span class="hidden sm:inline">{{ __('game.jump_to_next') }}</span>
                    <span class="sm:hidden">{{ __('game.next') }}</span>
                </button>
            @endif
        </div>

        {{-- Competition filter pills --}}
        @if(count($competitions) > 1)
            <div class="mb-6 flex flex-wrap items-center gap-2">
                <button type="button"
                    @click="comp = 'all'"
                    :class="comp === 'all' ? 'bg-accent-blue/15 text-accent-blue ring-1 ring-accent-blue/40' : 'bg-surface-700 text-text-secondary hover:bg-surface-600'"
                    class="px-3 py-1 text-xs font-semibold rounded-full transition-colors">
                    {{ __('game.all_competitions') }}
                </button>
                @foreach($competitions as $competition)
                    <button type="button"
                        @click="comp = @js($competition->id)"
                        :class="comp === @js($competition->id) ? 'ring-1 ring-accent-blue/60 opacity-100' : 'opacity-70 hover:opacity-100'"
                        class="rounded-full transition-opacity">
                        <x-competition-pill :competition="$competition" />
                    </button>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
            {{-- Left Column (2/3) - Calendar --}}
            <div class="md:col-span-2 space-y-8">
                @foreach($calendar as $month => $matches)
                    @php $monthComps = $monthsByCompetition[$month] ?? []; @endphp
                    <div x-show="comp === 'all' || @js($monthComps).includes(comp)">
                        <x-section-card :title="$month">
                            <div class="divide-y divide-border-default">
                                @foreach($matches as $match)
                                    @if(!empty($match->is_placeholder))
                                        <x-fixture-placeholder-row :placeholder="$match" />
                                    @else
                                        @if($nextMatchId && $match->id === $nextMatchId)
                                            <div x-show="comp === 'all' || comp === @js($nextMatchComp)">
                                                <x-today-marker :date="$game->current_date" />
                                            </div>
                                        @endif
                                        <div x-show="comp === 'all' || comp === @js($match->competition_id)">
                                            <x-fixture-row :match="$match" :game="$game" :next-match-id="$nextMatchId" />
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </x-section-card>
                    </div>
                @endforeach
            </div>

            {{-- Right Column (1/3) - Season Stats (rendered once per scope; x-show toggles which set is visible) --}}
            <div class="space-y-6">
                @php $statsScopes = array_merge(['all' => $statsByCompetition['all']], collect($competitions)->mapWithKeys(fn ($c) => [$c->id => $statsByCompetition[$c->id]])->all()); @endphp
                @foreach($statsScopes as $scope => $stats)
                    <div x-show="comp === @js($scope)" class="space-y-6">
                        {{-- Record --}}
                        <x-section-card :title="__('game.record')">
                            <div class="p-4 md:p-6">
                                <div class="flex items-center justify-between text-2xl font-bold mb-2">
                                    <span class="text-accent-green">{{ $stats['wins'] }}W</span>
                                    <span class="text-text-secondary">{{ $stats['draws'] }}D</span>
                                    <span class="text-red-500">{{ $stats['losses'] }}L</span>
                                </div>
                                @if($stats['played'] > 0)
                                <div class="w-full rounded-full h-2 overflow-hidden">
                                    @php
                                        $winWidth = ($stats['wins'] / $stats['played']) * 100;
                                        $drawWidth = ($stats['draws'] / $stats['played']) * 100;
                                        $lossWidth = ($stats['losses'] / $stats['played']) * 100;
                                    @endphp
                                    <div class="h-2 flex">
                                        <div class="bg-accent-green" style="width: {{ $winWidth }}%"></div>
                                        <div class="bg-surface-600" style="width: {{ $drawWidth }}%"></div>
                                        <div class="bg-accent-red" style="width: {{ $lossWidth }}%"></div>
                                    </div>
                                </div>
                                <div class="text-xs text-text-muted mt-1 text-right">{{ __('game.win_rate', ['percent' => $stats['winPercent']]) }}</div>
                                @endif
                            </div>
                        </x-section-card>

                        {{-- Form --}}
                        @if(count($stats['form']) > 0)
                        <x-section-card :title="__('game.form')">
                            <div class="p-4 md:p-6">
                                <div class="flex gap-1">
                                    @foreach($stats['form'] as $result)
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
                                        <div class="text-2xl font-bold text-text-primary">{{ $stats['goalsFor'] }}</div>
                                        <div class="text-xs text-text-muted">{{ __('game.scored') }}</div>
                                    </div>
                                    <div class="text-center p-3 bg-surface-700/50 rounded-lg">
                                        <div class="text-2xl font-bold text-text-primary">{{ $stats['goalsAgainst'] }}</div>
                                        <div class="text-xs text-text-muted">{{ __('game.conceded') }}</div>
                                    </div>
                                </div>
                            </div>
                        </x-section-card>

                        {{-- Home/Away Breakdown (hidden for neutral-venue tournaments) --}}
                        @unless($game->isTournamentMode())
                            <x-section-card :title="__('game.home_vs_away')">
                                <div class="p-4 md:p-6 space-y-3">
                                    {{-- Home --}}
                                    <div class="p-3 bg-accent-green/10 rounded-lg">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-sm font-semibold text-accent-green">{{ __('game.home') }}</span>
                                            <span class="text-sm font-bold text-accent-green">{{ $stats['home']['points'] }} {{ __('game.pts') }}</span>
                                        </div>
                                        <div class="text-xs text-text-secondary">
                                            {{ $stats['home']['wins'] }}W {{ $stats['home']['draws'] }}D {{ $stats['home']['losses'] }}L
                                            <span class="text-text-secondary mx-1">&middot;</span>
                                            {{ $stats['home']['goalsFor'] }}-{{ $stats['home']['goalsAgainst'] }}
                                        </div>
                                    </div>
                                    {{-- Away --}}
                                    <div class="p-3 bg-surface-700 rounded-lg">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-sm font-semibold text-text-body">{{ __('game.away') }}</span>
                                            <span class="text-sm font-bold text-text-body">{{ $stats['away']['points'] }} {{ __('game.pts') }}</span>
                                        </div>
                                        <div class="text-xs text-text-secondary">
                                            {{ $stats['away']['wins'] }}W {{ $stats['away']['draws'] }}D {{ $stats['away']['losses'] }}L
                                            <span class="text-text-secondary mx-1">&middot;</span>
                                            {{ $stats['away']['goalsFor'] }}-{{ $stats['away']['goalsAgainst'] }}
                                        </div>
                                    </div>
                                </div>
                            </x-section-card>
                        @endunless
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
