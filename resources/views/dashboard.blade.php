<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white leading-tight text-center">
            {{ __('app.load_game') }}
        </h2>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-semibold text-xl text-slate-900">{{ __('game.your_games') }}</h3>
                        <a href="{{ route('select-team') }}" class="text-sky-600 hover:text-sky-800">+ {{ __('app.new_game') }}</a>
                    </div>
                    <ul role="list" class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        @foreach($games as $game)
                            <li class="col-span-1 flex flex-col rounded-lg bg-white text-center shadow">
                                <div class="flex flex-1 flex-col p-8 space-y-3">
                                    <img class="mx-auto h-20 w-20 flex-shrink-0" src="{{ $game->team->image }}" alt="">
                                    <h3 class="text-xl font-semibold leading-tight text-slate-900">{{ $game->team->name }}</h3>
                                    <dl class="flex flex-col justify-between">
                                        <dd class="text-sm text-slate-500">{{ __('game.season_n', ['season' => $game->season]) }}</dd>
                                        @if($game->current_date)
                                            <dd class="mt-2 mb-2">
                                                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                                    {{ __('game.matchday_n', ['number' => $game->current_matchday]) }} - {{ $game->current_date->format('d/m/Y') }}
                                                </span>
                                            </dd>
                                        @endif
                                    </dl>
                                    <div class="grid">
                                        <x-primary-button class="place-self-center text-md !p-0">
                                            <a class="inline-flex px-4 py-2" href="{{ route('show-game', $game->id) }}">{{ __('app.continue') }}</a>
                                        </x-primary-button>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
