@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    <h3 class="font-semibold text-xl text-slate-900 mb-6">{{ __('squad.title', ['team' => $game->team->name]) }}</h3>

                    <x-section-nav :items="[
                        ['href' => route('game.squad', $game->id), 'label' => __('squad.squad'), 'active' => false],
                        ['href' => route('game.squad.contracts', $game->id), 'label' => __('squad.contracts'), 'active' => false, 'badge' => $expiringContractsCount > 0 ? $expiringContractsCount : null],
                        ['href' => route('game.squad.development', $game->id), 'label' => __('squad.development'), 'active' => false],
                        ['href' => route('game.squad.stats', $game->id), 'label' => __('squad.stats'), 'active' => false],
                        ['href' => route('game.squad.academy', $game->id), 'label' => __('squad.academy'), 'active' => true, 'badge' => $academyCount > 0 ? $academyCount : null],
                    ]" />

                    <div class="mt-6"></div>

                    {{-- Academy tier info --}}
                    <div class="mb-6 flex items-center gap-3">
                        <span class="text-sm text-slate-500">{{ __('squad.academy_tier') }}:</span>
                        <span class="text-sm font-semibold @if($tier >= 3) text-green-600 @elseif($tier >= 1) text-sky-600 @else text-slate-400 @endif">
                            {{ $tierDescription }}
                        </span>
                    </div>

                    @if($academyCount === 0)
                        <div class="text-center py-16">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 rounded-full mb-4">
                                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                                </svg>
                            </div>
                            <p class="text-slate-500 text-sm">{{ __('squad.no_academy_prospects') }}</p>
                            <p class="text-slate-400 text-xs mt-2">{{ __('squad.academy_explanation') }}</p>
                        </div>
                    @else
                        <table class="w-full text-sm">
                            <thead class="text-left border-b">
                                <tr>
                                    <th class="font-semibold py-2 w-10"></th>
                                    <th class="font-semibold py-2">{{ __('app.name') }}</th>
                                    <th class="font-semibold py-2 text-center w-12">{{ __('app.country') }}</th>
                                    <th class="font-semibold py-2 text-center w-12">{{ __('app.age') }}</th>
                                    <th class="font-semibold py-2 text-center w-10">{{ __('squad.technical') }}</th>
                                    <th class="font-semibold py-2 text-center w-10">{{ __('squad.physical') }}</th>
                                    <th class="font-semibold py-2 text-center w-16">{{ __('squad.pot') }}</th>
                                    <th class="font-semibold py-2 text-center w-10">{{ __('squad.overall') }}</th>
                                    <th class="font-semibold py-2 text-right w-8"></th>
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
                                            <td colspan="9" class="py-2 px-2 text-xs font-semibold text-slate-600 uppercase tracking-wide">
                                                {{ $group['name'] }}
                                            </td>
                                        </tr>
                                        @foreach($group['players'] as $prospect)
                                            <tr class="border-b border-slate-200 hover:bg-slate-50">
                                                {{-- Position --}}
                                                <td class="py-2 text-center">
                                                    <x-position-badge :position="$prospect->position" :tooltip="$prospect->position" class="cursor-help" />
                                                </td>
                                                {{-- Name --}}
                                                <td class="py-2">
                                                    <div class="font-medium text-slate-900">{{ $prospect->name }}</div>
                                                    <div class="text-xs text-slate-400">{{ $prospect->appeared_at->format('d M Y') }}</div>
                                                </td>
                                                {{-- Nationality --}}
                                                <td class="py-2 text-center">
                                                    @if($prospect->nationality_flag)
                                                        <img src="/flags/{{ $prospect->nationality_flag['code'] }}.svg" class="w-5 h-4 mx-auto rounded shadow-sm" title="{{ $prospect->nationality_flag['name'] }}">
                                                    @endif
                                                </td>
                                                {{-- Age --}}
                                                <td class="py-2 text-center">{{ $prospect->age }}</td>
                                                {{-- Technical --}}
                                                <td class="border-l border-slate-200 py-2 text-center @if($prospect->technical_ability >= 80) text-green-600 @elseif($prospect->technical_ability >= 70) text-lime-600 @elseif($prospect->technical_ability < 60) text-slate-400 @endif">
                                                    {{ $prospect->technical_ability }}
                                                </td>
                                                {{-- Physical --}}
                                                <td class="py-2 text-center @if($prospect->physical_ability >= 80) text-green-600 @elseif($prospect->physical_ability >= 70) text-lime-600 @elseif($prospect->physical_ability < 60) text-slate-400 @endif">
                                                    {{ $prospect->physical_ability }}
                                                </td>
                                                {{-- Potential range --}}
                                                <td class="py-2 text-center text-xs text-slate-500">
                                                    {{ $prospect->potential_range }}
                                                </td>
                                                {{-- Overall --}}
                                                <td class="py-2 text-center">
                                                    <span class="font-bold @if($prospect->overall >= 80) text-green-600 @elseif($prospect->overall >= 70) text-lime-600 @elseif($prospect->overall >= 60) text-yellow-600 @else text-slate-500 @endif">
                                                        {{ $prospect->overall }}
                                                    </span>
                                                </td>
                                                {{-- Actions --}}
                                                <td class="py-2 text-right">
                                                    <div x-data="{ open: false }" class="relative inline-block">
                                                        <button @click="open = !open" class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-100">
                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/></svg>
                                                        </button>
                                                        <div x-show="open" @click.away="open = false" x-transition
                                                             class="absolute right-0 mt-1 w-48 bg-white rounded-lg shadow-lg border border-slate-200 py-1 z-10">
                                                            <form method="post" action="{{ route('game.academy.promote', [$game->id, $prospect->id]) }}">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-3 py-1.5 text-xs text-sky-600 hover:bg-sky-50">
                                                                    {{ __('squad.promote_to_first_team') }}
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
