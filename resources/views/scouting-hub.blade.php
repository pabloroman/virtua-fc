@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Flash Messages --}}
            @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
                {{ session('success') }}
            </div>
            @endif
            @if(session('error'))
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                {{ session('error') }}
            </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 md:p-8">
                    @include('partials.transfers-header')

                    {{-- Tab Navigation --}}
                    <x-section-nav :items="[
                        ['href' => route('game.transfers', $game->id), 'label' => __('transfers.outgoing'), 'active' => false, 'badge' => $salidaBadgeCount > 0 ? $salidaBadgeCount : null],
                        ['href' => route('game.scouting', $game->id), 'label' => __('transfers.incoming'), 'active' => false, 'badge' => $counterOfferCount > 0 ? $counterOfferCount : null],
                        ['href' => route('game.scouting.hub', $game->id), 'label' => __('transfers.scouting_tab'), 'active' => true],
                    ]" />

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">

                        {{-- ============================== --}}
                        {{-- LEFT COLUMN (2/3) — Search History --}}
                        {{-- ============================== --}}
                        <div class="md:col-span-2 space-y-6">

                            @if($searchHistory->isNotEmpty())
                            <div class="border rounded-lg overflow-hidden">
                                <div class="px-5 py-3 bg-slate-50 border-b">
                                    <h4 class="font-semibold text-sm text-slate-900 flex items-center gap-2">
                                        {{ __('transfers.search_history') }}
                                        <span class="text-xs font-normal text-slate-400">({{ $searchHistory->count() }})</span>
                                    </h4>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="text-left bg-slate-50/50 border-b border-slate-100">
                                            <tr>
                                                <th class="font-medium py-2 pl-4 text-slate-500">{{ __('transfers.position_required', ['*' => '']) }}</th>
                                                <th class="font-medium py-2 text-slate-500 hidden md:table-cell">{{ __('transfers.scope') }}</th>
                                                <th class="font-medium py-2 text-slate-500 hidden md:table-cell">{{ __('transfers.age_range') }}</th>
                                                <th class="font-medium py-2 text-center text-slate-500">{{ __('transfers.scout_results') }}</th>
                                                <th class="font-medium py-2 pr-4 text-right text-slate-500"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($searchHistory as $historyReport)
                                                @php
                                                    $filters = $historyReport->filters;
                                                    $histScopeLabel = isset($filters['scope']) && count($filters['scope']) === 1
                                                        ? (in_array('domestic', $filters['scope']) ? __('transfers.scope_domestic') : __('transfers.scope_international'))
                                                        : __('transfers.scope_domestic') . ' + ' . __('transfers.scope_international');
                                                    $resultCount = is_array($historyReport->player_ids) ? count($historyReport->player_ids) : 0;
                                                    $ageLabel = null;
                                                    if (isset($filters['age_min']) || isset($filters['age_max'])) {
                                                        $ageMin = $filters['age_min'] ?? '16';
                                                        $ageMax = $filters['age_max'] ?? '40';
                                                        $ageLabel = $ageMin . '-' . $ageMax;
                                                    }
                                                @endphp
                                                <tr class="border-t border-slate-100 hover:bg-slate-50/50">
                                                    <td class="py-3 pl-4">
                                                        <span class="font-medium text-slate-900">{{ isset($filters['position']) ? \App\Support\PositionMapper::filterToDisplayName($filters['position']) : '-' }}</span>
                                                        <div class="text-xs text-slate-400 md:hidden">{{ $histScopeLabel }}</div>
                                                    </td>
                                                    <td class="py-3 text-slate-600 hidden md:table-cell">{{ $histScopeLabel }}</td>
                                                    <td class="py-3 text-slate-600 hidden md:table-cell">{{ $ageLabel ?? __('transfers.all_ages') }}</td>
                                                    <td class="py-3 text-center text-slate-600 tabular-nums">{{ __('transfers.results_count', ['count' => $resultCount]) }}</td>
                                                    <td class="py-3 text-right pr-4">
                                                        <button x-data @click="$dispatch('show-scout-results', '{{ route('game.scouting.results', [$game->id, $historyReport->id]) }}')"
                                                           class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-sky-600 hover:text-sky-800 border border-sky-200 hover:bg-sky-50 rounded-lg transition-colors min-h-[44px] sm:min-h-0">
                                                            {{ __('transfers.view_results') }}
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @else
                            <div class="text-center py-12 text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <p class="font-medium">{{ __('transfers.no_search_history') }}</p>
                                <p class="text-sm mt-1">{{ __('transfers.scout_search_desc') }}</p>
                            </div>
                            @endif

                        </div>

                        {{-- ============================== --}}
                        {{-- RIGHT COLUMN (1/3) — Search Panel --}}
                        {{-- ============================== --}}
                        <div class="space-y-6">

                            @if($searchingReport)
                                {{-- Searching State --}}
                                <div class="border rounded-lg p-5 bg-sky-50">
                                    <div class="text-center">
                                        <svg class="w-10 h-10 mx-auto mb-3 text-sky-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        <h4 class="font-semibold text-slate-900 mb-1">{{ __('transfers.scout_searching') }}</h4>
                                        <p class="text-sm text-slate-600 mb-1">
                                            {{ trans_choice('game.weeks_remaining', $searchingReport->weeks_remaining, ['count' => $searchingReport->weeks_remaining]) }}
                                        </p>
                                        <p class="text-xs text-slate-500 mb-4">
                                            {{ __('transfers.looking_for') }}: <span class="font-medium">{{ \App\Support\PositionMapper::filterToDisplayName($searchingReport->filters['position']) }}</span>
                                            @if(isset($searchingReport->filters['scope']) && count($searchingReport->filters['scope']) === 1)
                                                — <span class="font-medium">{{ in_array('domestic', $searchingReport->filters['scope']) ? __('transfers.scope_domestic') : __('transfers.scope_international') }}</span>
                                            @endif
                                        </p>
                                        <div class="w-full bg-slate-200 rounded-full h-2 mb-4">
                                            @php $progress = (($searchingReport->weeks_total - $searchingReport->weeks_remaining) / $searchingReport->weeks_total) * 100; @endphp
                                            <div class="bg-sky-500 h-2 rounded-full transition-all" style="width: {{ $progress }}%"></div>
                                        </div>
                                        <form method="post" action="{{ route('game.scouting.cancel', $game->id) }}">
                                            @csrf
                                            <x-ghost-button type="submit" class="text-sm text-center">
                                                {{ __('transfers.cancel_search') }}
                                            </x-ghost-button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                {{-- New Search Button --}}
                                <div x-data>
                                    <button @click="$dispatch('open-modal', 'scout-search')"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-lg transition-colors min-h-[44px]">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        {{ __('transfers.new_scout_search') }}
                                    </button>
                                </div>
                            @endif

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <x-scout-search-modal :game="$game" :can-search-internationally="$canSearchInternationally" />
    <x-scout-results-modal />

</x-app-layout>
