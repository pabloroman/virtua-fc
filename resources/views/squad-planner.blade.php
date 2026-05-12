@php
    /** @var App\Models\Game $game */
    /** @var array $projection */
    /** @var array<int, \App\Modules\Squad\DTOs\Advisory> $advisories */
    $staying = $projection['staying'];
    $outgoing = $projection['outgoing'];
    $incoming = $projection['incoming'];
    $counts = $projection['counts'];

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
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">

        {{-- Sub-navigation --}}
        <x-section-nav :items="$squadNavItems" />

        {{-- Page header --}}
        <div class="mt-6 mb-2">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                {{ __('planner.title') }}
            </h2>
        </div>

        {{-- Summary strip --}}
        <div class="flex gap-2.5 overflow-x-auto scrollbar-hide mt-4 pb-1">
            <x-summary-card :label="__('planner.section_staying')" :value="$counts['staying'] > 0 ? $counts['staying'] : '—'" />
            <x-summary-card :label="__('planner.section_outgoing')" :value="$counts['outgoing'] > 0 ? $counts['outgoing'] : '—'" :value-class="$counts['outgoing'] > 0 ? 'text-accent-red' : 'text-text-faint'" />
            <x-summary-card :label="__('planner.section_incoming')" :value="$counts['incoming'] > 0 ? $counts['incoming'] : '—'" :value-class="$counts['incoming'] > 0 ? 'text-accent-green' : 'text-text-faint'" />
        </div>

        {{-- ===== Layout: sidebar + main panel ===== --}}
        <div class="mt-6 grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6">

            {{-- ===== Sidebar ===== --}}
            <aside class="space-y-6">
                {{-- Transfer Recommendations --}}
                <x-section-card :title="__('planner.transfer_recommendations')">
                    <div class="p-4">
                        <x-tip-list
                            :tips="array_map(fn($a) => $a->toTip(), $advisories)"
                            :empty-message="__('planner.advisory_empty')" />
                    </div>
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
                            @include('partials.squad-planner.column-header')
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
                        <span class="text-text-faint font-normal normal-case tracking-normal ml-1">· {{ $incoming->isEmpty() ? '—' : $counts['incoming'] }}</span>
                    </h3>

                    @if($incoming->isNotEmpty())
                        <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">
                            @include('partials.squad-planner.column-header')
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
                        <span class="text-text-faint font-normal normal-case tracking-normal ml-1">· {{ $outgoing->isEmpty() ? '—' : $counts['outgoing'] }}</span>
                    </h3>

                    @if($outgoing->isNotEmpty())
                        <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">
                            @include('partials.squad-planner.column-header')
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
