@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match" :continue-to-home="true"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-6">
            @if($matches->first()->round_name)
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                    @if($competition)
                        <span>{{ __($competition->name) }} &centerdot;</span>
                    @endif
                    {{ __('game.matchday_results', ['name' => __($matches->first()?->round_name ?? '')]) }}
                </h2>
            @else
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                    @if($competition)
                        <span>{{ __($competition->name) }} &centerdot;</span>
                    @endif
                    {{ __('game.matchday_results', ['name' => __('game.matchday_n', ['number' => $matchday])]) }}
                </h2>
            @endif
        </div>

        {{-- Player's Match Card --}}
        @if($playerMatch)
            <div class="mb-6">
                @include('partials.match-summary', ['match' => $playerMatch])
            </div>
        @endif

        {{-- All Results --}}
        <div class="bg-surface-800 rounded-lg border border-border-default p-4 md:p-6">
            <div class="space-y-1">
                @foreach($matches as $match)
                    <div class="flex items-center py-3 px-4 rounded-lg {{ $match->id === $playerMatch?->id ? 'bg-surface-600' : 'bg-surface-700/50' }}">
                        <div class="flex items-center gap-2 flex-1 justify-end">
                            <span class="text-sm truncate {{ ($match->home_score > $match->away_score) ? 'font-semibold text-text-primary' : 'text-text-secondary' }}">
                                {{ $match->homeTeam->name }}
                            </span>
                            <x-team-crest :team="$match->homeTeam" class="w-6 h-6" />
                        </div>
                        <div class="px-4 font-semibold tabular-nums text-text-primary">
                            {{ $match->home_score }} - {{ $match->away_score }}
                        </div>
                        <div class="flex items-center gap-2 flex-1">
                            <x-team-crest :team="$match->awayTeam" class="w-6 h-6" />
                            <span class="text-sm truncate {{ ($match->away_score > $match->home_score) ? 'font-semibold text-text-primary' : 'text-text-secondary'  }}">
                                {{ $match->awayTeam->name }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
