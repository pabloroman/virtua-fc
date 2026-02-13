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
                        @if($canCreateGame)
                            <a href="{{ route('select-team') }}" class="text-sky-600 hover:text-sky-800">+ {{ __('app.new_game') }}</a>
                        @endif
                    </div>

                    @if($errors->has('limit'))
                        <div class="mb-4 rounded-md bg-red-50 p-4">
                            <p class="text-sm text-red-700">{{ $errors->first('limit') }}</p>
                        </div>
                    @endif

                    @if(session('success'))
                        <div class="mb-4 rounded-md bg-green-50 p-4">
                            <p class="text-sm text-green-700">{{ session('success') }}</p>
                        </div>
                    @endif

                    <ul role="list" class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        @foreach($games as $game)
                            <li class="col-span-1 flex flex-col rounded-lg bg-white text-center shadow" x-data="{ confirmDelete: false }">
                                <div class="flex flex-1 flex-col p-8 space-y-3" x-show="!confirmDelete">
                                    <img class="mx-auto h-20 w-20 flex-shrink-0" src="{{ $game->team->image }}" alt="">
                                    <h3 class="text-xl font-semibold leading-tight text-slate-900">{{ $game->team->name }}</h3>
                                    <dl class="flex flex-col justify-between">
                                        <dd class="text-sm text-slate-500">{{ __('game.season_n', ['season' => $game->formatted_season]) }}</dd>
                                        @if($game->current_date)
                                            <dd class="mt-2 mb-2">
                                                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                                    {{ __('game.matchday_n', ['number' => $game->current_matchday]) }} - {{ $game->current_date->format('d/m/Y') }}
                                                </span>
                                            </dd>
                                        @endif
                                    </dl>
                                    <div class="flex items-center justify-center gap-3">
                                        <x-primary-button class="text-md !p-0">
                                            <a class="inline-flex px-4 py-2" href="{{ route('show-game', $game->id) }}">{{ __('app.continue') }}</a>
                                        </x-primary-button>
                                        <button
                                            type="button"
                                            @click="confirmDelete = true"
                                            class="inline-flex items-center justify-center w-9 h-9 min-h-[44px] min-w-[44px] rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                            title="{{ __('game.delete_game') }}"
                                        >
                                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                {{-- Confirmation overlay --}}
                                <div class="flex flex-1 flex-col items-center justify-center p-8 space-y-4" x-show="confirmDelete" x-cloak>
                                    <svg class="w-10 h-10 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                    <p class="text-sm text-slate-600 text-center">{{ __('game.confirm_delete_game') }}</p>
                                    <div class="flex gap-3">
                                        <button
                                            type="button"
                                            @click="confirmDelete = false"
                                            class="inline-flex items-center px-3 py-2 min-h-[44px] bg-white border border-slate-300 rounded-lg font-semibold text-xs text-slate-700 uppercase tracking-widest shadow-sm hover:bg-slate-50"
                                        >
                                            {{ __('app.cancel') }}
                                        </button>
                                        <form method="POST" action="{{ route('game.destroy', $game->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="inline-flex items-center px-3 py-2 min-h-[44px] bg-red-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                                            >
                                                {{ __('game.delete_game') }}
                                            </button>
                                        </form>
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
