@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-6">{{ $game->team->name }} - Season Calendar</h3>

                    @foreach($calendar as $month => $matches)
                        <div class="mb-8">
                            <h4 class="text-md font-semibold text-gray-600 mb-3 border-b pb-2">{{ $month }}</h4>
                            <div class="space-y-2">
                                @foreach($matches as $match)
                                    @php
                                        $isHome = $match->home_team_id === $game->team_id;
                                        $opponent = $isHome ? $match->awayTeam : $match->homeTeam;
                                        $isNextMatch = !$match->played && $game->next_match?->id === $match->id;
                                    @endphp
                                    <div class="flex items-center p-3 rounded-lg @if($isNextMatch) bg-yellow-50 ring-2 ring-yellow-400 @elseif($match->played) bg-gray-50 @else bg-white border @endif">
                                        <div class="w-24 text-sm text-gray-500">
                                            {{ $match->scheduled_date->format('D d M') }}
                                        </div>
                                        <div class="w-16 text-center text-xs uppercase tracking-wide text-gray-400">
                                            {{ $isHome ? 'HOME' : 'AWAY' }}
                                        </div>
                                        <div class="flex-1 flex items-center gap-2">
                                            <img src="{{ $opponent->image }}" class="w-6 h-6">
                                            <span class="font-medium">{{ $opponent->name }}</span>
                                        </div>
                                        <div class="w-20 text-center font-mono">
                                            @if($match->played)
                                                @php
                                                    $yourScore = $isHome ? $match->home_score : $match->away_score;
                                                    $oppScore = $isHome ? $match->away_score : $match->home_score;
                                                    $result = $yourScore > $oppScore ? 'W' : ($yourScore < $oppScore ? 'L' : 'D');
                                                    $resultClass = $result === 'W' ? 'text-green-600' : ($result === 'L' ? 'text-red-600' : 'text-gray-600');
                                                @endphp
                                                <span class="{{ $resultClass }} font-bold">{{ $yourScore }} - {{ $oppScore }}</span>
                                            @elseif($isNextMatch)
                                                <span class="text-yellow-600 font-semibold">NEXT</span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
