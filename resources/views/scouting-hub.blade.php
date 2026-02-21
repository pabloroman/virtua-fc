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
                        ['href' => route('game.transfers', $game->id), 'label' => __('transfers.incoming'), 'active' => false, 'badge' => $counterOfferCount > 0 ? $counterOfferCount : null],
                        ['href' => route('game.transfers.outgoing', $game->id), 'label' => __('transfers.outgoing'), 'active' => false, 'badge' => $salidaBadgeCount > 0 ? $salidaBadgeCount : null],
                        ['href' => route('game.scouting', $game->id), 'label' => __('transfers.scouting_tab'), 'active' => true],
                    ]" />

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">

                        {{-- ============================== --}}
                        {{-- LEFT COLUMN (2/3) — Shortlist + Search History --}}
                        {{-- ============================== --}}
                        <div class="md:col-span-2 space-y-6">

                            {{-- Shortlist Section --}}
                            @if(!empty($shortlistedPlayers))
                            <div class="border rounded-lg overflow-hidden">
                                <div class="px-5 py-3 bg-amber-50 border-b border-amber-200">
                                    <h4 class="font-semibold text-sm text-slate-900 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                        {{ __('transfers.shortlist') }}
                                        <span class="text-xs font-normal text-slate-400">({{ count($shortlistedPlayers) }})</span>
                                    </h4>
                                </div>
                                <div class="divide-y divide-slate-100">
                                    @foreach($shortlistedPlayers as $entry)
                                        @php
                                            $sp = $entry['gamePlayer'];
                                            $detail = $entry['detail'];
                                            $techRange = $detail['tech_range'];
                                            $canAffordFee = $detail['can_afford_fee'];
                                            $isExpiring = $sp->contract_until && $sp->contract_until <= $game->getSeasonEndDate();
                                        @endphp
                                        <div class="px-4 md:px-5 py-3 flex items-center gap-3 hover:bg-slate-50/50" x-data="{ confirmRemove: false }">
                                            <x-position-badge :position="$sp->position" />
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-semibold text-slate-900 truncate">{{ $sp->name }}</span>
                                                    <span class="text-xs text-slate-400">{{ $sp->age }} {{ __('app.years') }}</span>
                                                    @if($isExpiring)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700">{{ __('transfers.expiring_contract') }}</span>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-2 text-xs text-slate-500 mt-0.5">
                                                    @if($sp->team)
                                                        <img src="{{ $sp->team->image }}" class="w-4 h-4 shrink-0">
                                                        <span class="truncate">{{ $sp->team->name }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="text-right hidden sm:block shrink-0">
                                                <div class="text-xs text-slate-400">{{ __('transfers.ability') }}</div>
                                                <div class="text-sm font-semibold text-slate-700 tabular-nums">{{ $techRange[0] }}-{{ $techRange[1] }}</div>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <div class="text-xs text-slate-400">{{ __('transfers.asking_price') }}</div>
                                                <div class="text-sm font-semibold {{ $canAffordFee ? 'text-slate-900' : 'text-red-600' }}">{{ $detail['formatted_asking_price'] }}</div>
                                            </div>
                                            {{-- Remove from shortlist --}}
                                            <div class="shrink-0">
                                                <template x-if="!confirmRemove">
                                                    <button @click="confirmRemove = true" class="p-1.5 text-slate-300 hover:text-red-500 rounded hover:bg-red-50 transition-colors min-h-[44px] sm:min-h-0" title="{{ __('transfers.remove_from_shortlist') }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </template>
                                                <template x-if="confirmRemove">
                                                    <form method="POST" action="{{ route('game.scouting.shortlist.remove', [$game->id, $sp->id]) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="px-2 py-1 text-xs font-semibold text-red-600 border border-red-200 rounded hover:bg-red-50 transition-colors min-h-[44px] sm:min-h-0">
                                                            {{ __('transfers.remove_from_shortlist') }}
                                                        </button>
                                                    </form>
                                                </template>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @else
                            <div class="border border-dashed border-slate-200 rounded-lg p-6 text-center text-slate-400">
                                <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                                <p class="text-sm">{{ __('transfers.shortlist_empty') }}</p>
                            </div>
                            @endif

                            {{-- Search History --}}
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
                                                        <div class="flex items-center justify-end gap-2" x-data="{ confirmDelete: false }">
                                                            <button x-data @click="$dispatch('show-scout-results', '{{ route('game.scouting.results', [$game->id, $historyReport->id]) }}')"
                                                               class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-sky-600 hover:text-sky-800 border border-sky-200 hover:bg-sky-50 rounded-lg transition-colors min-h-[44px] sm:min-h-0">
                                                                {{ __('transfers.view_results') }}
                                                            </button>
                                                            <template x-if="!confirmDelete">
                                                                <button @click="confirmDelete = true" class="p-1.5 text-slate-300 hover:text-red-500 rounded hover:bg-red-50 transition-colors min-h-[44px] sm:min-h-0" title="{{ __('transfers.delete_search') }}">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                    </svg>
                                                                </button>
                                                            </template>
                                                            <template x-if="confirmDelete">
                                                                <form method="POST" action="{{ route('game.scouting.delete', [$game->id, $historyReport->id]) }}" class="inline">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="inline-flex items-center px-2 py-1.5 text-xs font-semibold text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors min-h-[44px] sm:min-h-0">
                                                                        {{ __('transfers.delete_search') }}
                                                                    </button>
                                                                </form>
                                                            </template>
                                                        </div>
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
