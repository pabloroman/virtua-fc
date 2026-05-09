@php
    /** @var App\Models\Game $game */
    /** @var App\Models\GameMatch $match */
    /** @var App\Models\Team $opponent */
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-12">
        @include('partials.opponent-analysis-content')
    </div>
</x-app-layout>
