@php
    /** @var App\Models\Game $game */
    /** @var array $projection */
    /** @var \App\Modules\Lineup\Enums\Formation $formation */
    /** @var array<string, array{group: string, need: int, have: int, delta: int}> $formationFit */
    /** @var array<int, \App\Modules\Squad\Services\Advisory> $advisories */
    /** @var string $horizon */
    $isNextSeason = $horizon === \App\Modules\Squad\Services\NextSeasonProjectionService::HORIZON_NEXT;
    $currentYear = $game->current_date->year;

    $staying = $projection['staying'];
    $outgoing = $projection['outgoing'];
    $incoming = $projection['incoming'];
    $counts = $projection['counts'];
    $nextSeasonStartYear = $projection['nextSeasonStartYear'];

    $positionGroups = [
        ['key' => 'goalkeepers', 'label' => __('planner.goalkeepers'), 'group' => 'Goalkeeper', 'players' => $staying['goalkeepers']],
        ['key' => 'defenders', 'label' => __('planner.defenders'), 'group' => 'Defender', 'players' => $staying['defenders']],
        ['key' => 'midfielders', 'label' => __('planner.midfielders'), 'group' => 'Midfielder', 'players' => $staying['midfielders']],
        ['key' => 'forwards', 'label' => __('planner.forwards'), 'group' => 'Forward', 'players' => $staying['forwards']],
    ];

    $secondaryItem = $game->isFilial()
        ? ['href' => route('game.squad.reserve', $game->id), 'label' => __('squad.reserve_team'), 'active' => false]
        : ['href' => route('game.squad.academy', $game->id), 'label' => __('squad.academy'), 'active' => false];

    $squadNavItems = [
        ['href' => route('game.squad', $game->id), 'label' => __('squad.first_team'), 'active' => false],
        ['href' => route('game.squad.planner', $game->id), 'label' => __('planner.planner'), 'active' => true],
        $secondaryItem,
        ['href' => route('game.squad.registration', $game->id), 'label' => __('squad.registration'), 'active' => false],
    ];

    $groupDisplay = [
        'Goalkeeper' => __('planner.goalkeepers'),
        'Defender' => __('planner.defenders'),
        'Midfielder' => __('planner.midfielders'),
        'Forward' => __('planner.forwards'),
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">

        {{-- Sub-navigation --}}
        <x-section-nav :items="$squadNavItems" />

        {{-- Page header + season toggle --}}
        <div class="mt-6 mb-2 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                    {{ __('planner.title') }}
                </h2>
                <p class="text-sm text-text-secondary mt-1">
                    @if($isNextSeason)
                        {{ __('planner.subtitle_next_season', ['year' => $nextSeasonStartYear]) }}
                    @else
                        {{ __('planner.subtitle_current_season', ['year' => $currentYear]) }}
                    @endif
                </p>
            </div>

            {{-- Season horizon toggle --}}
            @php
                $currentHref = route('game.squad.planner', ['gameId' => $game->id, 'season' => 'current', 'formation' => $formation->value]);
                $nextHref = route('game.squad.planner', ['gameId' => $game->id, 'season' => 'next', 'formation' => $formation->value]);
            @endphp
            <div class="inline-flex items-center bg-surface-700 rounded-lg p-0.5 self-start sm:self-end shrink-0">
                <a href="{{ $currentHref }}"
                   class="px-3 py-1.5 text-xs font-semibold rounded-md transition-colors whitespace-nowrap {{ ! $isNextSeason ? 'bg-surface-800 text-text-primary shadow-xs' : 'text-text-muted hover:text-text-body' }}">
                    {{ __('planner.toggle_current_season', ['year' => $currentYear]) }}
                </a>
                <a href="{{ $nextHref }}"
                   class="px-3 py-1.5 text-xs font-semibold rounded-md transition-colors whitespace-nowrap {{ $isNextSeason ? 'bg-accent-blue text-white shadow-xs' : 'text-text-muted hover:text-text-body' }}">
                    {{ __('planner.toggle_next_season', ['year' => $nextSeasonStartYear]) }}
                </a>
            </div>
        </div>

        {{-- Summary strip --}}
        <div class="flex gap-2.5 overflow-x-auto scrollbar-hide mt-4 pb-1">
            <x-summary-card :label="__('planner.section_staying')" :value="$counts['staying']" />
            <x-summary-card :label="__('planner.section_outgoing')" :value="$counts['outgoing']" :value-class="$counts['outgoing'] > 0 ? 'text-accent-red' : 'text-text-primary'" />
            <x-summary-card :label="__('planner.section_incoming')" :value="$counts['incoming']" :value-class="$counts['incoming'] > 0 ? 'text-accent-green' : 'text-text-primary'" />
        </div>

        {{-- ===== Layout: sidebar + main panel ===== --}}
        <div class="mt-6 grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6">

            {{-- ===== Sidebar ===== --}}
            <aside class="space-y-6">
                {{-- Tactics Hub --}}
                <x-section-card :title="__('planner.tactics_hub')">
                    <div class="p-4 space-y-4">
                        <div>
                            <label for="planner-formation" class="block text-[11px] font-semibold uppercase tracking-widest text-text-muted mb-2">
                                {{ __('planner.target_formation') }}
                            </label>
                            <div class="relative">
                                <select
                                    id="planner-formation"
                                    x-data
                                    x-on:change="window.location.href = `{{ route('game.squad.planner', ['gameId' => $game->id, 'season' => $horizon]) }}&formation=${encodeURIComponent($event.target.value)}`"
                                    class="block w-full appearance-none bg-surface-700 border border-border-strong rounded-lg pl-3 pr-9 py-2 text-sm font-semibold text-text-primary focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
                                    @foreach(\App\Modules\Lineup\Enums\Formation::cases() as $f)
                                        <option value="{{ $f->value }}" @selected($f === $formation)>{{ $f->label() }}</option>
                                    @endforeach
                                </select>
                                <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted mb-2">
                                {{ __('planner.projected_xi_fit') }}
                            </p>
                            <ul class="space-y-1.5">
                                @foreach($formationFit as $group => $fit)
                                    @php
                                        $tone = match (true) {
                                            $fit['delta'] >= 1 => 'text-accent-green',
                                            $fit['delta'] === 0 => 'text-text-secondary',
                                            default => 'text-accent-red',
                                        };
                                    @endphp
                                    <li class="flex items-center justify-between text-[12px]">
                                        <span class="text-text-muted">{{ $groupDisplay[$group] ?? $group }}</span>
                                        <span class="tabular-nums {{ $tone }}">
                                            {{ __('planner.fit_summary', ['need' => $fit['need'], 'have' => $fit['have']]) }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </x-section-card>

                {{-- Transfer Recommendations --}}
                <x-section-card :title="__('planner.transfer_recommendations')">
                    <x-advisory-list :advisories="$advisories" />
                </x-section-card>
            </aside>

            {{-- ===== Main column ===== --}}
            <div>
                {{-- ===== Staying ===== --}}
                <div>
                    <h3 class="font-heading text-sm font-semibold uppercase tracking-widest text-text-secondary mb-3">
                        {{ __('planner.section_staying') }}
                        <span class="text-text-faint font-normal normal-case tracking-normal ml-1">· {{ $counts['staying'] }}</span>
                    </h3>

                    @if($counts['staying'] === 0)
                        <div class="bg-surface-800 border border-border-default rounded-xl px-5 py-8 text-center text-sm text-text-muted">
                            {{ __('planner.empty_staying') }}
                        </div>
                    @else
                        <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">
                            @foreach($positionGroups as $group)
                                @if($group['players']->isNotEmpty())
                                    <div class="px-4 py-2 bg-surface-700/30 border-b border-border-default">
                                        <div class="flex items-center justify-between">
                                            <span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted">{{ $group['label'] }}</span>
                                            <span class="text-[10px] text-text-faint">{{ $group['players']->count() }}</span>
                                        </div>
                                    </div>

                                    @foreach($group['players'] as $gp)
                                        @include('partials.squad-planner.player-row', ['gp' => $gp, 'group' => $group['group'], 'game' => $game])
                                    @endforeach
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ===== Incoming ===== --}}
                <div class="mt-8">
                    <h3 class="font-heading text-sm font-semibold uppercase tracking-widest text-text-secondary mb-3">
                        {{ __('planner.section_incoming') }}
                        <span class="text-text-faint font-normal normal-case tracking-normal ml-1">· {{ $counts['incoming'] }}</span>
                    </h3>

                    @if($incoming->isEmpty())
                        <div class="bg-surface-800 border border-border-default rounded-xl px-5 py-8 text-center text-sm text-text-muted">
                            {{ __('planner.empty_incoming') }}
                        </div>
                    @else
                        <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">
                            @foreach($incoming as $gp)
                                @include('partials.squad-planner.player-row', ['gp' => $gp, 'group' => $gp->position_group, 'game' => $game])
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ===== Outgoing ===== --}}
                <div class="mt-8">
                    <h3 class="font-heading text-sm font-semibold uppercase tracking-widest text-text-secondary mb-3">
                        {{ __('planner.section_outgoing') }}
                        <span class="text-text-faint font-normal normal-case tracking-normal ml-1">· {{ $counts['outgoing'] }}</span>
                    </h3>

                    @if($outgoing->isEmpty())
                        <div class="bg-surface-800 border border-border-default rounded-xl px-5 py-8 text-center text-sm text-text-muted">
                            {{ __('planner.empty_outgoing') }}
                        </div>
                    @else
                        <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">
                            @foreach($outgoing as $gp)
                                @include('partials.squad-planner.player-row', ['gp' => $gp, 'group' => $gp->position_group, 'game' => $game])
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
