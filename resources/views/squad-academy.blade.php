@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 md:p-8">
                    <x-section-nav :items="[
                        ['href' => route('game.squad', $game->id), 'label' => __('squad.squad'), 'active' => false],
                        ['href' => route('game.squad.development', $game->id), 'label' => __('squad.development'), 'active' => false],
                        ['href' => route('game.squad.stats', $game->id), 'label' => __('squad.stats'), 'active' => false],
                        ['href' => route('game.squad.academy', $game->id), 'label' => __('squad.academy'), 'active' => true, 'badge' => $academyCount > 0 ? $academyCount : null],
                    ]" />

                    <div class="mt-6"></div>

                    {{-- Academy tier + capacity info + help toggle --}}
                    <div x-data="{ open: false }" class="mb-6">
                        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-slate-500">{{ __('squad.academy_tier') }}:</span>
                                <span class="text-sm font-semibold @if($tier >= 3) text-green-600 @elseif($tier >= 1) text-sky-600 @else text-slate-400 @endif">
                                    {{ $tierDescription }}
                                </span>
                            </div>

                            @if($capacity > 0)
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-slate-500">{{ __('squad.academy_capacity') }}:</span>
                                    <span class="text-sm font-semibold {{ $academyCount > $capacity ? 'text-red-600' : 'text-slate-700' }}">
                                        {{ $academyCount }}/{{ $capacity }}
                                    </span>
                                    <div class="w-16 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full {{ $academyCount > $capacity ? 'bg-red-500' : ($academyCount >= $capacity - 1 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                             style="width: {{ min(100, ($academyCount / max($capacity, 1)) * 100) }}%"></div>
                                    </div>
                                </div>
                            @endif

                            {{-- Reveal phase indicator --}}
                            <div class="flex items-center gap-2">
                                @if($revealPhase === 0)
                                    <span class="text-xs bg-slate-100 text-slate-500 px-2 py-1 rounded-full">{{ __('squad.academy_phase_unknown') }}</span>
                                @elseif($revealPhase === 1)
                                    <span class="text-xs bg-sky-100 text-sky-600 px-2 py-1 rounded-full">{{ __('squad.academy_phase_glimpse') }}</span>
                                @else
                                    <span class="text-xs bg-emerald-100 text-emerald-600 px-2 py-1 rounded-full">{{ __('squad.academy_phase_verdict') }}</span>
                                @endif
                            </div>

                            {{-- How it works toggle --}}
                            <button @click="open = !open" class="ml-auto flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-slate-400 shrink-0">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
                                </svg>
                                <span>{{ __('squad.academy_help_toggle') }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''">
                                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>

                        <div x-show="open" x-transition class="mt-3 bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm">
                            <p class="text-slate-600 mb-4">{{ __('squad.academy_help_development') }}</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {{-- Reveal phases --}}
                            <div>
                                <p class="font-semibold text-slate-700 mb-2">{{ __('squad.academy_help_phases_title') }}</p>
                                <ul class="space-y-2">
                                    <li class="flex gap-2">
                                        <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-300 text-slate-600 text-xs font-bold">0</span>
                                        <span class="text-slate-600">{{ __('squad.academy_help_phase_0') }}</span>
                                    </li>
                                    <li class="flex gap-2">
                                        <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-sky-200 text-sky-700 text-xs font-bold">1</span>
                                        <span class="text-slate-600">{{ __('squad.academy_help_phase_1') }}</span>
                                    </li>
                                    <li class="flex gap-2">
                                        <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-200 text-emerald-700 text-xs font-bold">2</span>
                                        <span class="text-slate-600">{{ __('squad.academy_help_phase_2') }}</span>
                                    </li>
                                </ul>
                            </div>

                            {{-- Evaluations --}}
                            <div>
                                <p class="font-semibold text-slate-700 mb-2">{{ __('squad.academy_help_evaluations_title') }}</p>
                                <p class="text-slate-500 mb-2">{{ __('squad.academy_help_evaluation_desc') }}</p>
                                <ul class="space-y-1 text-slate-600">
                                    <li class="flex gap-2"><span class="text-emerald-500 shrink-0">↑</span> {{ __('squad.academy_help_promote') }}</li>
                                    <li class="flex gap-2"><span class="text-sky-500 shrink-0">⇄</span> {{ __('squad.academy_help_loan') }}</li>
                                    <li class="flex gap-2"><span class="text-slate-400 shrink-0">✓</span> {{ __('squad.academy_help_keep') }}</li>
                                    <li class="flex gap-2"><span class="text-red-400 shrink-0">✕</span> {{ __('squad.academy_help_dismiss') }}</li>
                                </ul>
                                <p class="mt-3 text-xs text-slate-400">{{ __('squad.academy_help_age_rule') }} {{ __('squad.academy_help_capacity_rule') }}</p>
                            </div>
                            </div>{{-- grid --}}
                        </div>
                    </div>

                    @if($academyCount === 0 && $loanedPlayers->isEmpty())
                        <div class="text-center py-16">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 rounded-full mb-4">
                                <svg class="w-8 h-8 fill-slate-300" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M48 195.8l209.2 86.1c9.8 4 20.2 6.1 30.8 6.1s21-2.1 30.8-6.1l242.4-99.8c9-3.7 14.8-12.4 14.8-22.1s-5.8-18.4-14.8-22.1L318.8 38.1C309 34.1 298.6 32 288 32s-21 2.1-30.8 6.1L14.8 137.9C5.8 141.6 0 150.3 0 160L0 456c0 13.3 10.7 24 24 24s24-10.7 24-24l0-260.2zm48 71.7L96 384c0 53 86 96 192 96s192-43 192-96l0-116.6-142.9 58.9c-15.6 6.4-32.2 9.7-49.1 9.7s-33.5-3.3-49.1-9.7L96 267.4z"/></svg>
                            </div>
                            <p class="text-slate-500 text-sm">{{ __('squad.no_academy_prospects') }}</p>
                            <p class="text-slate-400 text-xs mt-2">{{ __('squad.academy_explanation') }}</p>
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
                                                <tr class="bg-slate-200">
                                                    <td colspan="8" class="py-2 px-2 text-xs font-semibold text-slate-600 uppercase tracking-wide">
                                                        {{ $group['name'] }}
                                                    </td>
                                                </tr>
                                                @foreach($group['players'] as $prospect)
                                                    @php $playerReveal = $prospect->seasons_in_academy > 1 ? 2 : $revealPhase; @endphp
                                                    <tr class="border-b border-slate-200 hover:bg-slate-50">
                                                        {{-- Position --}}
                                                        <td class="py-2 text-center">
                                                            <x-position-badge :position="$prospect->position" :tooltip="\App\Support\PositionMapper::toDisplayName($prospect->position)" class="cursor-help" />
                                                        </td>
                                                        {{-- Name --}}
                                                        <td class="py-2">
                                                            <div class="flex items-center space-x-2">
                                                                <button x-data @click="$dispatch('show-player-detail', '{{ route('game.academy.detail', [$game->id, $prospect->id]) }}')" class="p-1.5 text-slate-300 rounded hover:text-slate-400">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="w-5 h-5">
                                                                        <path fill-rule="evenodd" d="M19.5 21a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-5.379a.75.75 0 0 1-.53-.22L11.47 3.66A2.25 2.25 0 0 0 9.879 3H4.5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h15Zm-6.75-10.5a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25v2.25a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V10.5Z" clip-rule="evenodd" />
                                                                    </svg>
                                                                </button>
                                                                <div>
                                                                    <div class="font-medium text-slate-900">{{ $prospect->name }}</div>
                                                                    <div class="text-xs text-slate-400">{{ trans_choice('squad.academy_seasons', $prospect->seasons_in_academy, ['count' => $prospect->seasons_in_academy]) }}</div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        {{-- Nationality --}}
                                                        <td class="py-2 text-center hidden md:table-cell">
                                                            @if($prospect->nationality_flag)
                                                                <img src="/flags/{{ $prospect->nationality_flag['code'] }}.svg" class="w-5 h-4 mx-auto rounded shadow-sm" title="{{ $prospect->nationality_flag['name'] }}">
                                                            @endif
                                                        </td>
                                                        {{-- Age --}}
                                                        <td class="py-2 text-center hidden md:table-cell">{{ $prospect->age }}</td>
                                                        {{-- Technical --}}
                                                        <td class="border-l border-slate-200 py-2 pl-3 text-center hidden md:table-cell">
                                                            @if($playerReveal >= 1)
                                                                <x-ability-bar :value="$prospect->technical_ability" size="sm" class="text-xs font-medium justify-center @if($prospect->technical_ability >= 80) text-green-600 @elseif($prospect->technical_ability >= 70) text-lime-600 @elseif($prospect->technical_ability < 60) text-slate-400 @endif" />
                                                            @else
                                                                <span class="text-slate-300">?</span>
                                                            @endif
                                                        </td>
                                                        {{-- Physical --}}
                                                        <td class="py-2 text-center hidden md:table-cell">
                                                            @if($playerReveal >= 1)
                                                                <x-ability-bar :value="$prospect->physical_ability" size="sm" class="text-xs font-medium justify-center @if($prospect->physical_ability >= 80) text-green-600 @elseif($prospect->physical_ability >= 70) text-lime-600 @elseif($prospect->physical_ability < 60) text-slate-400 @endif" />
                                                            @else
                                                                <span class="text-slate-300">?</span>
                                                            @endif
                                                        </td>
                                                        {{-- Potential range --}}
                                                        <td class="py-2 text-center text-xs hidden md:table-cell {{ $playerReveal >= 2 ? 'text-slate-500' : 'text-slate-300' }}">
                                                            {{ $playerReveal >= 2 ? $prospect->potential_range : '?' }}
                                                        </td>
                                                        {{-- Overall --}}
                                                        <td class="py-2 text-center">
                                                            @if($playerReveal >= 1)
                                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold
                                                                    @if($prospect->overall >= 80) bg-emerald-500 text-white
                                                                    @elseif($prospect->overall >= 70) bg-lime-500 text-white
                                                                    @elseif($prospect->overall >= 60) bg-amber-500 text-white
                                                                    @else bg-slate-300 text-slate-700
                                                                    @endif">
                                                                    {{ $prospect->overall }}
                                                                </span>
                                                            @else
                                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold bg-slate-200 text-slate-400">?</span>
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
                                <h4 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">
                                    {{ __('squad.academy_on_loan') }} ({{ $loanedPlayers->count() }})
                                </h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <tbody>
                                            @foreach($loanedPlayers as $prospect)
                                                <tr class="border-b border-slate-200">
                                                    <td class="py-2 text-center w-10">
                                                        <x-position-badge :position="$prospect->position" :tooltip="\App\Support\PositionMapper::toDisplayName($prospect->position)" class="cursor-help" />
                                                    </td>
                                                    <td class="py-2">
                                                        <div class="flex items-center space-x-2">
                                                            <button x-data @click="$dispatch('show-player-detail', '{{ route('game.academy.detail', [$game->id, $prospect->id]) }}')" class="p-1.5 text-slate-300 rounded hover:text-slate-400">
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="w-5 h-5">
                                                                    <path fill-rule="evenodd" d="M19.5 21a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-5.379a.75.75 0 0 1-.53-.22L11.47 3.66A2.25 2.25 0 0 0 9.879 3H4.5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h15Zm-6.75-10.5a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25v2.25a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V10.5Z" clip-rule="evenodd" />
                                                                </svg>
                                                            </button>
                                                            <div>
                                                                <div class="flex items-center gap-2">
                                                                    <span class="font-medium text-slate-900">{{ $prospect->name }}</span>
                                                                    <span class="text-xs bg-indigo-100 text-indigo-600 px-1.5 py-0.5 rounded font-medium">{{ __('squad.academy_on_loan') }}</span>
                                                                </div>
                                                                <div class="text-xs text-slate-400">{{ trans_choice('squad.academy_seasons', $prospect->seasons_in_academy, ['count' => $prospect->seasons_in_academy]) }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="py-2 text-center hidden md:table-cell">
                                                        @if($prospect->nationality_flag)
                                                            <img src="/flags/{{ $prospect->nationality_flag['code'] }}.svg" class="w-5 h-4 mx-auto rounded shadow-sm" title="{{ $prospect->nationality_flag['name'] }}">
                                                        @endif
                                                    </td>
                                                    <td class="py-2 text-center text-slate-400 hidden md:table-cell">{{ $prospect->age }}</td>
                                                    <td class="border-l border-slate-200 py-2 text-center text-slate-300 hidden md:table-cell">—</td>
                                                    <td class="py-2 text-center text-slate-300 hidden md:table-cell">—</td>
                                                    <td class="py-2 text-center text-slate-300 hidden md:table-cell">—</td>
                                                    <td class="py-2 text-center">
                                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold bg-slate-200 text-slate-400">—</span>
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
            </div>
        </div>
    </div>

    <x-player-detail-modal />
</x-app-layout>
