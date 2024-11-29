<div class="flex justify-between text-gray-400">
    <div class="flex items-center space-x-4">
        <img src="{{ $game->team->image }}" class="w-16 h-16">
        <div>
            <h2 class="font-semibold text-xl text-white leading-tight">
                {{ $game->team->name }}
            </h2>
            @if($game->nextMatchday)
            <p>{{ $game->nextMatchday->competition->name }} {{ $game->seasonName }} - {{ $game->nextMatchday->name }}</p>
            @endif
        </div>
    </div>
    <div class="text-right flex items-center space-x-4">
        @if($game->nextMatchday)
        <div>
            <div class="text-xs">Próximo partido - {{ $game->nextMatchday->date->format('d/m/Y') }}</div>
            <div class="flex items-center space-x-1">
                <img class="w-4 h-4" src="{{ $game->nextFixture?->homeTeam->image }}"><span>{{ $game->nextFixture?->homeTeam->name }}</span><span> - </span><span>{{ $game->nextFixture?->awayTeam->name }}</span><img class="w-4 h-4" src="{{ $game->nextFixture?->awayTeam->image }}"></div>
        </div>
        <form method="post" action="{{ route('game.matchday.play', $game) }}" x-data="{ loading: false }" @submit="loading = true">
            {{ csrf_field() }}
            <x-primary-button-spin>Continuar</x-primary-button-spin>
        </form>
        @endif
    </div>
</div>

<nav class="flex text-white/40 space-x-4 mt-4 items-center text-xl">
{{--    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.news') text-white @endif" href="{{ route('game.news', $game) }}">Noticias</a></div>--}}
{{--    <div><a class="hover:text-slate-300 @if(in_array(Route::currentRouteName(), ['game.results', 'game.calendar'])) text-white @endif" href="{{ route('game.results', $game) }}">Resultados</a></div>--}}
{{--    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.standings') text-white @endif" href="{{ route('game.standings', $game) }}">Clasificación</a></div>--}}
{{--    @if($game->nextMatchday)--}}
{{--    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.lineup') text-white @endif" href="{{ route('game.lineup', $game) }}">Alineación</a></div>--}}
{{--    @endif--}}
{{--    <div><a class="hover:text-slate-300 @if(in_array(Route::currentRouteName(), ['game.transfers', 'game.transfer-map'])) text-white @endif" href="{{ route('game.transfers', ['game' => $game, 'filter' => 'free-agents']) }}">Fichajes</a></div>--}}
{{--    <div><a class="hover:text-slate-300 @if(in_array(Route::currentRouteName(), ['game.squad', 'game.signings', 'game.offers'])) text-white @endif" href="{{ route('game.squad', $game) }}">Plantilla</a></div>--}}
{{--    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.budget') text-white @endif" href="{{ route('game.budget', $game) }}">Finanzas</a></div>--}}
{{--    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.stadium') text-white @endif" href="{{ route('game.stadium', $game) }}">Estadio</a></div>--}}
</nav>
