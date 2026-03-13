@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('squad.squad') }}</h2>
        </div>

                    <x-section-nav :items="[
                        ['href' => route('game.squad', $game->id), 'label' => __('squad.first_team'), 'active' => false],
                        ['href' => route('game.squad.academy', $game->id), 'label' => __('squad.academy'), 'active' => true],
                    ]" />

                    <div class="mt-6"></div>

                    {{-- Academy tier + capacity info + help toggle --}}
                    <div x-data="{ open: false }" class="mb-6">
                        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-text-muted">{{ __('squad.academy_tier') }}:</span>
                                <span class="text-sm font-semibold @if($tier >= 3) text-accent-green @elseif($tier >= 1) text-accent-blue @else text-text-secondary @endif">
                                    {{ $tierDescription }}
                                </span>
                            </div>

                            @if($capacity > 0)
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-text-muted">{{ __('squad.academy_capacity') }}:</span>
                                    <span class="text-sm font-semibold {{ $academyCount > $capacity ? 'text-accent-red' : 'text-text-body' }}">
                                        {{ $academyCount }}/{{ $capacity }}
                                    </span>
                                    <div class="w-16 h-1.5 bg-bar-track rounded-full overflow-hidden">
                                        <div class="h-full rounded-full {{ $academyCount > $capacity ? 'bg-accent-red' : ($academyCount >= $capacity - 1 ? 'bg-accent-gold' : 'bg-emerald-500') }}"
                                             style="width: {{ min(100, ($academyCount / max($capacity, 1)) * 100) }}%"></div>
                                    </div>
                                </div>
                            @endif

                            {{-- Reveal phase indicator --}}
                            <div class="flex items-center gap-2">
                                @if($revealPhase === 0)
                                    <span class="text-xs bg-surface-700 text-text-muted px-2 py-1 rounded-full">{{ __('squad.academy_phase_unknown') }}</span>
                                @elseif($revealPhase === 1)
                                    <span class="text-xs bg-accent-blue/10 text-accent-blue px-2 py-1 rounded-full">{{ __('squad.academy_phase_glimpse') }}</span>
                                @else
                                    <span class="text-xs bg-accent-green/10 text-accent-green px-2 py-1 rounded-full">{{ __('squad.academy_phase_verdict') }}</span>
                                @endif
                            </div>

                            {{-- How it works toggle --}}
                            <x-ghost-button color="slate" @click="open = !open" class="ml-auto gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-text-secondary shrink-0">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
                                </svg>
                                <span>{{ __('squad.academy_help_toggle') }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''">
                                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </x-ghost-button>
                        </div>

                        <div x-show="open" x-transition class="mt-3 bg-surface-700/50 border border-border-strong rounded-lg p-4 text-sm">
                            <p class="text-text-secondary mb-4">{{ __('squad.academy_help_development') }}</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {{-- Reveal phases --}}
                            <div>
                                <p class="font-semibold text-text-body mb-2">{{ __('squad.academy_help_phases_title') }}</p>
                                <ul class="space-y-2">
                                    <li class="flex gap-2">
                                        <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-surface-600 text-text-secondary text-xs font-bold">0</span>
                                        <span class="text-text-secondary">{{ __('squad.academy_help_phase_0') }}</span>
                                    </li>
                                    <li class="flex gap-2">
                                        <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent-blue/20 text-accent-blue text-xs font-bold">1</span>
                                        <span class="text-text-secondary">{{ __('squad.academy_help_phase_1') }}</span>
                                    </li>
                                    <li class="flex gap-2">
                                        <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent-green/20 text-accent-green text-xs font-bold">2</span>
                                        <span class="text-text-secondary">{{ __('squad.academy_help_phase_2') }}</span>
                                    </li>
                                </ul>
                            </div>

                            {{-- Evaluations --}}
                            <div>
                                <p class="font-semibold text-text-body mb-2">{{ __('squad.academy_help_evaluations_title') }}</p>
                                <p class="text-text-muted mb-2">{{ __('squad.academy_help_evaluation_desc') }}</p>
                                <ul class="space-y-1 text-text-secondary">
                                    <li class="flex gap-2"><span class="text-accent-green shrink-0">↑</span> {{ __('squad.academy_help_promote') }}</li>
                                    <li class="flex gap-2"><span class="text-accent-blue shrink-0">⇄</span> {{ __('squad.academy_help_loan') }}</li>
                                    <li class="flex gap-2"><span class="text-text-secondary shrink-0">✓</span> {{ __('squad.academy_help_keep') }}</li>
                                    <li class="flex gap-2"><span class="text-accent-red shrink-0">✕</span> {{ __('squad.academy_help_dismiss') }}</li>
                                </ul>
                                <p class="mt-3 text-xs text-text-secondary">{{ __('squad.academy_help_age_rule') }} {{ __('squad.academy_help_capacity_rule') }}</p>
                            </div>
                            </div>{{-- grid --}}
                        </div>
                    </div>

                    @if($academyCount === 0 && $loanedPlayers->isEmpty())
                        <div class="text-center py-16">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-surface-700 rounded-full mb-4">
                                <svg class="w-8 h-8 fill-surface-600" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M48 195.8l209.2 86.1c9.8 4 20.2 6.1 30.8 6.1s21-2.1 30.8-6.1l242.4-99.8c9-3.7 14.8-12.4 14.8-22.1s-5.8-18.4-14.8-22.1L318.8 38.1C309 34.1 298.6 32 288 32s-21 2.1-30.8 6.1L14.8 137.9C5.8 141.6 0 150.3 0 160L0 456c0 13.3 10.7 24 24 24s24-10.7 24-24l0-260.2zm48 71.7L96 384c0 53 86 96 192 96s192-43 192-96l0-116.6-142.9 58.9c-15.6 6.4-32.2 9.7-49.1 9.7s-33.5-3.3-49.1-9.7L96 267.4z"/></svg>
                            </div>
                            <p class="text-text-muted text-sm">{{ __('squad.no_academy_prospects') }}</p>
                            <p class="text-text-secondary text-xs mt-2">{{ __('squad.academy_explanation') }}</p>
                        </div>
                    @else
                        {{-- Active academy players --}}
                        @if($academyCount > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-left border-b">
                                        <tr>
                                            <th class="font-semibold py-2 w-10"></th>
                                            <th class="font-semibold py-2">{{ __('app.name') }}</th>
                                            <th class="font-semibold py-2 text-center w-12 hidden md:table-cell">{{ __('app.country') }}</th>
                                            <th class="font-semibold py-2 text-center w-12 hidden md:table-cell">{{ __('app.age') }}</th>
                                            <th class="font-semibold py-2 pl-3 text-center w-10 hidden md:table-cell">{{ __('squad.technical') }}</th>
                                            <th class="font-semibold py-2 text-center w-10 hidden md:table-cell">{{ __('squad.physical') }}</th>
                                            <th class="font-semibold py-2 text-center w-16 hidden md:table-cell">{{ __('squad.pot') }}</th>
                                            <th class="font-semibold py-2 text-center w-10">{{ __('squad.overall') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach([
                                            ['name' => __('squad.goalkeepers'), 'players' => $goalkeepers],
                                            ['name' => __('squad.defenders'), 'players' => $defenders],
                                            ['name' => __('squad.midfielders'), 'players' => $midfielders],
                                            ['name' => __('squad.forwards'), 'players' => $forwards],
                                        ] as $group)
                                            @if($group['players']->isNotEmpty())
                                                <tr class="bg-surface-600">
                                                    <td colspan="8" class="py-2 px-2 text-xs font-semibold text-text-secondary uppercase tracking-wide">
                                                        {{ $group['name'] }}
                                                    </td>
                                                </tr>
                                                @foreach($group['players'] as $prospect)
                                                    @php $playerReveal = $prospect->seasons_in_academy > 1 ? 2 : $revealPhase; @endphp
                                                    <tr class="border-b border-border-strong hover:bg-surface-700/50">
                                                        {{-- Position --}}
                                                        <td class="py-2 text-center">
                                                            <x-position-badge :position="$prospect->position" :tooltip="\App\Support\PositionMapper::toDisplayName($prospect->position)" class="cursor-help" />
                                                        </td>
                                                        {{-- Name --}}
                                                        <td class="py-2">
                                                            <div class="flex items-center space-x-2">
                                                                <x-icon-button size="sm" x-data @click="$dispatch('show-player-detail', '{{ route('game.academy.detail', [$game->id, $prospect->id]) }}')">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="w-5 h-5">
                                                                        <path fill-rule="evenodd" d="M19.5 21a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-5.379a.75.75 0 0 1-.53-.22L11.47 3.66A2.25 2.25 0 0 0 9.879 3H4.5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h15Zm-6.75-10.5a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25v2.25a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V10.5Z" clip-rule="evenodd" />
                                                                    </svg>
                                                                </x-icon-button>
                                                                <div>
                                                                    <div class="font-medium text-text-primary">{{ $prospect->name }}</div>
                                                                    <div class="text-xs text-text-secondary">{{ trans_choice('squad.academy_seasons', $prospect->seasons_in_academy, ['count' => $prospect->seasons_in_academy]) }}</div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        {{-- Nationality --}}
                                                        <td class="py-2 text-center hidden md:table-cell">
                                                            @if($prospect->nationality_flag)
                                                                <img src="/flags/{{ $prospect->nationality_flag['code'] }}.svg" class="w-5 h-4 mx-auto rounded-sm shadow-xs" title="{{ $prospect->nationality_flag['name'] }}">
                                                            @endif
                                                        </td>
                                                        {{-- Age --}}
                                                        <td class="py-2 text-center hidden md:table-cell">{{ $prospect->age }}</td>
                                                        {{-- Technical --}}
                                                        <td class="border-l border-border-strong py-2 pl-3 text-center hidden md:table-cell">
                                                            @if($playerReveal >= 1)
                                                                <x-ability-bar :value="$prospect->technical_ability" size="sm" class="text-xs font-medium justify-center @if($prospect->technical_ability >= 80) text-accent-green @elseif($prospect->technical_ability >= 70) text-lime-500 @elseif($prospect->technical_ability < 60) text-text-secondary @endif" />
                                                            @else
                                                                <span class="text-text-body">?</span>
                                                            @endif
                                                        </td>
                                                        {{-- Physical --}}
                                                        <td class="py-2 text-center hidden md:table-cell">
                                                            @if($playerReveal >= 1)
                                                                <x-ability-bar :value="$prospect->physical_ability" size="sm" class="text-xs font-medium justify-center @if($prospect->physical_ability >= 80) text-accent-green @elseif($prospect->physical_ability >= 70) text-lime-500 @elseif($prospect->physical_ability < 60) text-text-secondary @endif" />
                                                            @else
                                                                <span class="text-text-body">?</span>
                                                            @endif
                                                        </td>
                                                        {{-- Potential range --}}
                                                        <td class="py-2 text-center text-xs hidden md:table-cell {{ $playerReveal >= 2 ? 'text-text-muted' : 'text-text-body' }}">
                                                            {{ $playerReveal >= 2 ? $prospect->potential_range : '?' }}
                                                        </td>
                                                        {{-- Overall --}}
                                                        <td class="py-2 text-center">
                                                            @if($playerReveal >= 1)
                                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-semibold
                                                                    @if($prospect->overall >= 80) bg-emerald-500 text-white
                                                                    @elseif($prospect->overall >= 70) bg-lime-500 text-white
                                                                    @elseif($prospect->overall >= 60) bg-accent-gold text-white
                                                                    @else bg-surface-600 text-text-body
                                                                    @endif">
                                                                    {{ $prospect->overall }}
                                                                </span>
                                                            @else
                                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-semibold bg-surface-600 text-text-secondary">?</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- Loaned players section --}}
                        @if($loanedPlayers->isNotEmpty())
                            <div class="mt-8">
                                <h4 class="text-sm font-semibold text-text-secondary uppercase tracking-wide mb-3">
                                    {{ __('squad.academy_on_loan') }} ({{ $loanedPlayers->count() }})
                                </h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <tbody>
                                            @foreach($loanedPlayers as $prospect)
                                                <tr class="border-b border-border-strong">
                                                    <td class="py-2 text-center w-10">
                                                        <x-position-badge :position="$prospect->position" :tooltip="\App\Support\PositionMapper::toDisplayName($prospect->position)" class="cursor-help" />
                                                    </td>
                                                    <td class="py-2">
                                                        <div class="flex items-center space-x-2">
                                                            <x-icon-button size="sm" x-data @click="$dispatch('show-player-detail', '{{ route('game.academy.detail', [$game->id, $prospect->id]) }}')">
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="w-5 h-5">
                                                                    <path fill-rule="evenodd" d="M19.5 21a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-5.379a.75.75 0 0 1-.53-.22L11.47 3.66A2.25 2.25 0 0 0 9.879 3H4.5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h15Zm-6.75-10.5a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25v2.25a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V10.5Z" clip-rule="evenodd" />
                                                                </svg>
                                                            </x-icon-button>
                                                            <div>
                                                                <div class="flex items-center gap-2">
                                                                    <span class="font-medium text-text-primary">{{ $prospect->name }}</span>
                                                                    <span class="text-xs bg-violet-500/10 text-violet-400 px-1.5 py-0.5 rounded-sm font-medium">{{ __('squad.academy_on_loan') }}</span>
                                                                </div>
                                                                <div class="text-xs text-text-secondary">{{ trans_choice('squad.academy_seasons', $prospect->seasons_in_academy, ['count' => $prospect->seasons_in_academy]) }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="py-2 text-center hidden md:table-cell">
                                                        @if($prospect->nationality_flag)
                                                            <img src="/flags/{{ $prospect->nationality_flag['code'] }}.svg" class="w-5 h-4 mx-auto rounded-sm shadow-xs" title="{{ $prospect->nationality_flag['name'] }}">
                                                        @endif
                                                    </td>
                                                    <td class="py-2 text-center text-text-secondary hidden md:table-cell">{{ $prospect->age }}</td>
                                                    <td class="border-l border-border-strong py-2 text-center text-text-body hidden md:table-cell">—</td>
                                                    <td class="py-2 text-center text-text-body hidden md:table-cell">—</td>
                                                    <td class="py-2 text-center text-text-body hidden md:table-cell">—</td>
                                                    <td class="py-2 text-center">
                                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-semibold bg-surface-600 text-text-secondary">—</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    @endif

    </div>

    <x-player-detail-modal />
</x-app-layout>
