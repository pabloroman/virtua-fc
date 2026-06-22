@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('app.transfers') }}</h2>
        </div>

        {{-- Flash Messages --}}
        <x-flash-message type="success" :message="session('success')" class="mb-4" />
        <x-flash-message type="error" :message="session('error')" class="mb-4" />

        @include('partials.transfers-header')

        {{-- Tab Navigation + How it works --}}
        <x-help-disclosure>
            <x-slot name="trigger">
                <x-section-nav :items="[
                    ['href' => route('game.transfers', $game->id), 'label' => __('transfers.incoming'), 'active' => false],
                    ['href' => route('game.transfers.outgoing', $game->id), 'label' => __('transfers.outgoing'), 'active' => false, 'badge' => $salidaBadgeCount > 0 ? $salidaBadgeCount : null],
                    ['href' => route('game.scouting', $game->id), 'label' => __('transfers.scouting_tab'), 'active' => true],
                    ['href' => route('game.explore', $game->id), 'label' => __('transfers.explore_tab'), 'active' => false],
                    ['href' => route('game.transfers.market', $game->id), 'label' => __('transfers.market_tab'), 'active' => false],
                ]">
                    <x-help-toggle :label="__('transfers.scouting_help_toggle')" />
                </x-section-nav>
            </x-slot>

            <p class="text-text-secondary mb-4">{{ __('transfers.scouting_help_intro') }}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Scout searches --}}
                <div>
                    <p class="font-semibold text-text-body mb-2">{{ __('transfers.scouting_help_search_title') }}</p>
                    <ul class="space-y-2">
                        <li class="flex gap-2">
                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent-blue/20 text-accent-blue text-xs font-bold">1</span>
                            <span class="text-text-secondary">{{ __('transfers.scouting_help_search_filters') }}</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent-blue/20 text-accent-blue text-xs font-bold">2</span>
                            <span class="text-text-secondary">{{ __('transfers.scouting_help_search_time') }}</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent-blue/20 text-accent-blue text-xs font-bold">3</span>
                            <span class="text-text-secondary">{{ __('transfers.scouting_help_search_scope') }}</span>
                        </li>
                    </ul>
                </div>

                {{-- Shortlist & Offers --}}
                <div>
                    <p class="font-semibold text-text-body mb-2">{{ __('transfers.scouting_help_shortlist_title') }}</p>
                    <ul class="space-y-1 text-text-secondary">
                        <li class="flex gap-2"><span class="text-accent-gold shrink-0">&#9733;</span> {{ __('transfers.scouting_help_shortlist_star') }}</li>
                        <li class="flex gap-2"><span class="text-accent-blue shrink-0">&#8594;</span> {{ __('transfers.scouting_help_shortlist_bid') }}</li>
                        <li class="flex gap-2"><span class="text-accent-green shrink-0">&#8644;</span> {{ __('transfers.scouting_help_shortlist_loan') }}</li>
                        <li class="flex gap-2"><span class="text-text-secondary shrink-0">&#10003;</span> {{ __('transfers.scouting_help_shortlist_precontract') }}</li>
                    </ul>
                </div>
            </div>
        </x-help-disclosure>

        {{-- ============================================================= --}}
        {{-- SCOUTING DESK — shortlisted targets + the latest search results --}}
        {{-- ============================================================= --}}
        <div class="mt-6"
             x-data="scoutingBoard({ players: @js($shortlistData) })"
             @shortlist-toggled.window="handleToggle($event.detail)">

            {{-- Operations status strip --}}
            <x-scout-ops-strip :game="$game" :tier="$scoutingTier" :can-search-internationally="$canSearchInternationally" />

            {{-- Intake: active search progress, otherwise the search action --}}
            @if($searchingReport)
                @php $progress = (($searchingReport->weeks_total - $searchingReport->weeks_remaining) / max(1, $searchingReport->weeks_total)) * 100; @endphp
                <div class="mt-4 border border-accent-blue/20 rounded-xl p-4 md:p-5 bg-accent-blue/10">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <svg class="w-9 h-9 shrink-0 text-accent-blue animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline gap-2 flex-wrap">
                                <h4 class="font-semibold text-text-primary">{{ __('transfers.scout_searching') }}</h4>
                                <span class="text-sm text-text-secondary">{{ trans_choice('game.weeks_remaining', $searchingReport->weeks_remaining, ['count' => $searchingReport->weeks_remaining]) }}</span>
                            </div>
                            <p class="text-xs text-text-muted mt-0.5">
                                {{ __('transfers.looking_for') }}: <span class="font-medium text-text-secondary">{{ \App\Support\PositionMapper::filterToDisplayName($searchingReport->filters['position']) }}</span>
                                @if(isset($searchingReport->filters['scope']) && count($searchingReport->filters['scope']) === 1)
                                    — <span class="font-medium text-text-secondary">{{ in_array('domestic', $searchingReport->filters['scope']) ? __('transfers.scope_domestic') : __('transfers.scope_international') }}</span>
                                @endif
                            </p>
                            <div class="w-full bg-bar-track rounded-full h-2 mt-3">
                                <div class="bg-accent-blue h-2 rounded-full transition-all" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>
                        <form method="post" action="{{ route('game.scouting.cancel', $game->id) }}" class="shrink-0">
                            @csrf
                            <x-ghost-button size="xs" type="submit">{{ __('transfers.cancel_search') }}</x-ghost-button>
                        </form>
                    </div>
                </div>
            @else
                {{-- When a search has completed, the new-search button lives in the results card header.
                     This floating button only covers the edge case of a shortlist with no completed search. --}}
                @unless($latestReport)
                    <div x-show="players.length > 0" x-cloak class="mt-4 flex justify-end">
                        <x-primary-button type="button" @click="$dispatch('open-modal', 'scout-search')" class="gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('transfers.new_scout_search') }}
                        </x-primary-button>
                    </div>
                @endunless
            @endif

            {{-- Zero-state hero: shown only at true cold start — no completed search, no shortlist, not searching. --}}
            @unless($searchingReport || $latestReport)
                <div x-show="players.length === 0" x-cloak class="mt-4 border border-dashed border-border-default rounded-xl px-6 py-12 text-center">
                    <x-primary-button type="button" @click="$dispatch('open-modal', 'scout-search')" class="gap-2 mx-auto">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        {{ __('transfers.new_scout_search') }}
                    </x-primary-button>
                    <p class="text-sm text-text-secondary mt-5 mx-auto">{!! __('transfers.scout_search_desc', [
                        'explore' => '<a href="' . route('game.explore', $game->id) . '" class="text-accent-blue hover:text-accent-blue/80 font-medium underline-offset-2 hover:underline">' . __('transfers.scouting_link_to_explore') . '</a>',
                    ]) !!}</p>
                </div>
            @endunless

            {{-- Shortlist: one list, most actionable targets first. --}}
            <div x-show="players.length > 0" x-cloak class="mt-4 border border-border-default rounded-xl overflow-hidden bg-surface-800">
                <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-border-default">
                    <h3 class="font-semibold text-sm text-text-primary flex items-center gap-2">
                        {{ __('transfers.shortlist') }}
                        <span class="text-xs font-normal text-text-secondary tabular-nums" x-text="'(' + players.length + ')'"></span>
                    </h3>
                </div>
                {{-- Same column layout as the search-results table below, so the two read as one design. --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-border-default">
                                <th class="py-2.5 pl-4 w-12"></th>
                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider"></th>
                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider text-center hidden md:table-cell">{{ __('transfers.explore_age') }}</th>
                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider text-center hidden md:table-cell">{{ __('transfers.explore_overall') }}</th>
                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('transfers.explore_value') }}</th>
                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider text-right hidden md:table-cell">{{ __('transfers.asking_price') }}</th>
                                <th class="py-2.5 pr-4"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="player in players" :key="player.id">
                                <x-scout-target-card />
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Shortlist empty but a search has run: point the user at the results below instead of the cold-start hero. --}}
            @if($latestReport)
                <div x-show="players.length === 0" x-cloak class="mt-4 border border-dashed border-border-default rounded-xl px-4 py-6 text-center text-sm text-text-muted">
                    {{ __('transfers.board_shortlist_hint') }}
                </div>
            @endif

            {{-- Latest search results — the "fresh catch", below the watchlist summary. Lazy-loads the existing
                 results partial (same as the modal) so the hub stays fast; ★-ing a player live-adds them to the board above. --}}
            @if($latestReport)
                <div class="mt-4 border border-border-default rounded-xl overflow-hidden bg-surface-800"
                     x-data="fragmentLoader({ url: @js(route('game.scouting.results', [$game->id, $latestReport->id]).'?inline=1') })">
                    <div x-show="loading" class="p-8 flex items-center justify-center">
                        <svg class="animate-spin h-7 w-7 text-text-secondary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <div x-show="!loading" x-cloak x-html="content"></div>
                </div>
            @endif

        </div>

        {{-- Search history — past intake, demoted to a collapsible section. --}}
        @if($searchHistory->isNotEmpty())
            <div class="mt-6" x-data="{ open: false }">
                <button type="button" @click="open = !open"
                        class="w-full flex items-center justify-between gap-2 px-4 py-3 rounded-xl border border-border-default bg-surface-800 hover:bg-surface-700/50 transition min-h-[44px]">
                    <span class="font-semibold text-sm text-text-primary flex items-center gap-2">
                        {{ __('transfers.search_history') }}
                        <span class="text-xs font-normal text-text-secondary">({{ $searchHistory->count() }})</span>
                    </span>
                    <svg class="w-4 h-4 text-text-secondary transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-collapse x-cloak class="mt-2 border border-border-default rounded-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left bg-surface-800 border-b border-border-default">
                                <tr>
                                    <th class="font-medium py-2 pl-4 text-text-muted">{{ __('transfers.position_required', ['*' => '']) }}</th>
                                    <th class="font-medium py-2 text-text-muted hidden md:table-cell">{{ __('transfers.scope') }}</th>
                                    <th class="font-medium py-2 text-text-muted hidden md:table-cell">{{ __('transfers.age_range') }}</th>
                                    <th class="font-medium py-2 text-center text-text-muted">{{ __('transfers.scout_results') }}</th>
                                    <th class="font-medium py-2 pr-4 text-right text-text-muted"></th>
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
                                    <tr class="border-t border-border-default hover:bg-surface-700/50">
                                        <td class="py-3 pl-4">
                                            <span class="font-medium text-text-primary">{{ isset($filters['position']) ? \App\Support\PositionMapper::filterToDisplayName($filters['position']) : '-' }}</span>
                                            <div class="text-xs text-text-secondary md:hidden">{{ $histScopeLabel }}</div>
                                        </td>
                                        <td class="py-3 text-text-secondary hidden md:table-cell">{{ $histScopeLabel }}</td>
                                        <td class="py-3 text-text-secondary hidden md:table-cell">{{ $ageLabel ?? __('transfers.all_ages') }}</td>
                                        <td class="py-3 text-center text-text-secondary tabular-nums">{{ __('transfers.results_count', ['count' => $resultCount]) }}</td>
                                        <td class="py-3 text-right pr-4">
                                            <div class="flex items-center justify-end gap-2" x-data="{ confirmDelete: false }">
                                                <x-action-button color="blue" type="button" x-data @click="$dispatch('show-scout-results', '{{ route('game.scouting.results', [$game->id, $historyReport->id]) }}')" class="sm:min-h-0">
                                                    {{ __('transfers.view_results') }}
                                                </x-action-button>
                                                <template x-if="!confirmDelete">
                                                    <x-icon-button size="sm" @click="confirmDelete = true" class="hover:text-red-500 hover:bg-accent-red/10 sm:min-h-0" title="{{ __('transfers.delete_search') }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </x-icon-button>
                                                </template>
                                                <template x-if="confirmDelete">
                                                    <form method="POST" action="{{ route('game.scouting.delete', [$game->id, $historyReport->id]) }}" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-action-button color="red" class="sm:min-h-0">
                                                            {{ __('transfers.delete_search') }}
                                                        </x-action-button>
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
            </div>
        @endif

    </div>

    <x-scout-search-modal :game="$game" :can-search-internationally="$canSearchInternationally" />
    <x-scout-results-modal />
    {{-- Shared dossier modal opened from shortlist cards AND scout-report result rows
         (inline hub + history "view results" modal). --}}
    <x-player-dossier-modal />
    <x-negotiation-chat-modal />

</x-app-layout>
