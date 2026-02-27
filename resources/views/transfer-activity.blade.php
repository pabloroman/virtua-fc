@php
/** @var App\Models\Game $game */
/** @var array $transfers */
/** @var array $freeAgentSignings */
/** @var string $window */
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 md:p-8">

                    {{-- Header --}}
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
                        <div>
                            <h2 class="text-lg md:text-xl font-bold text-slate-900">
                                {{ __('transfers.transfer_activity_title', ['window' => __('transfers.transfer_activity_' . $window)]) }}
                            </h2>
                            <p class="text-sm text-slate-500 mt-1">
                                {{ __('notifications.ai_transfer_message', ['count' => count($transfers) + count($freeAgentSignings)]) }}
                            </p>
                        </div>
                        <a href="{{ route('show-game', $game->id) }}"
                           class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 min-h-[44px]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            {{ __('app.back') }}
                        </a>
                    </div>

                    {{-- Transfers Section --}}
                    <div class="mb-8">
                        <h3 class="text-base font-semibold text-slate-800 mb-3 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            {{ __('transfers.transfer_activity_transfers') }}
                            <span class="text-sm font-normal text-slate-400">({{ count($transfers) }})</span>
                        </h3>

                        @if(count($transfers) > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-200 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                            <th class="py-2 pr-3">{{ __('transfers.transfer_activity_player') }}</th>
                                            <th class="py-2 pr-3 hidden md:table-cell">{{ __('transfers.transfer_activity_position') }}</th>
                                            <th class="py-2 pr-3">{{ __('transfers.transfer_activity_from') }}</th>
                                            <th class="py-2 pr-3">{{ __('transfers.transfer_activity_to') }}</th>
                                            <th class="py-2 pr-3 text-right">{{ __('transfers.transfer_activity_fee') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($transfers as $transfer)
                                            <tr class="hover:bg-slate-50">
                                                <td class="py-2.5 pr-3 font-medium text-slate-900 truncate max-w-[140px] md:max-w-none">
                                                    {{ $transfer['playerName'] }}
                                                </td>
                                                <td class="py-2.5 pr-3 text-slate-500 hidden md:table-cell">
                                                    {{ $transfer['position'] ?? '' }}
                                                </td>
                                                <td class="py-2.5 pr-3 text-slate-600 truncate max-w-[100px] md:max-w-none">
                                                    {{ $transfer['fromTeamName'] }}
                                                </td>
                                                <td class="py-2.5 pr-3 truncate max-w-[100px] md:max-w-none {{ ($transfer['type'] ?? '') === 'foreign' ? 'text-amber-600 italic' : 'text-slate-600' }}">
                                                    {{ $transfer['toTeamName'] }}
                                                    @if(($transfer['type'] ?? '') === 'foreign')
                                                        <span class="hidden md:inline text-xs text-amber-500 ml-1">({{ __('transfers.transfer_activity_foreign') }})</span>
                                                    @endif
                                                </td>
                                                <td class="py-2.5 pr-3 text-right text-slate-700 whitespace-nowrap">
                                                    {{ $transfer['formattedFee'] }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-slate-400 italic py-3">{{ __('transfers.transfer_activity_no_transfers') }}</p>
                        @endif
                    </div>

                    {{-- Free Agent Signings Section --}}
                    <div>
                        <h3 class="text-base font-semibold text-slate-800 mb-3 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            {{ __('transfers.transfer_activity_free_agents') }}
                            <span class="text-sm font-normal text-slate-400">({{ count($freeAgentSignings) }})</span>
                        </h3>

                        @if(count($freeAgentSignings) > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-200 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                            <th class="py-2 pr-3">{{ __('transfers.transfer_activity_player') }}</th>
                                            <th class="py-2 pr-3 hidden md:table-cell">{{ __('transfers.transfer_activity_position') }}</th>
                                            <th class="py-2 pr-3 hidden md:table-cell">{{ __('transfers.transfer_activity_age') }}</th>
                                            <th class="py-2 pr-3">{{ __('transfers.transfer_activity_to') }}</th>
                                            <th class="py-2 pr-3 text-right">{{ __('transfers.transfer_activity_fee') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($freeAgentSignings as $signing)
                                            <tr class="hover:bg-slate-50">
                                                <td class="py-2.5 pr-3 font-medium text-slate-900 truncate max-w-[140px] md:max-w-none">
                                                    {{ $signing['playerName'] }}
                                                </td>
                                                <td class="py-2.5 pr-3 text-slate-500 hidden md:table-cell">
                                                    {{ $signing['position'] ?? '' }}
                                                </td>
                                                <td class="py-2.5 pr-3 text-slate-500 hidden md:table-cell">
                                                    {{ $signing['age'] ?? '' }}
                                                </td>
                                                <td class="py-2.5 pr-3 text-slate-600 truncate max-w-[120px] md:max-w-none">
                                                    {{ $signing['toTeamName'] }}
                                                </td>
                                                <td class="py-2.5 pr-3 text-right text-green-600 whitespace-nowrap">
                                                    {{ $signing['formattedFee'] }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-slate-400 italic py-3">{{ __('transfers.transfer_activity_no_free_agents') }}</p>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
