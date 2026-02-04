@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-12">
                    <h3 class="font-semibold text-xl text-slate-900 mb-6">{{ $game->team->name }} - Season Calendar</h3>

                    @foreach($calendar as $month => $matches)
                        <div class="mb-8">
                            <h4 class="text-md font-semibold mb-3 border-b pb-2">{{ $month }}</h4>
                            <div class="space-y-2">
                                @foreach($matches as $match)
                                    <x-fixture-row :match="$match" :game="$game" />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
