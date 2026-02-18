@props([
    'game',
    'gamePlayer',
    'renewalDemand',
    'renewalMidpoint',
    'renewalMood',
])

<div x-data="{ open: false }" {{ $attributes->merge(['class' => '']) }}>
    <button @click="open = true" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-emerald-200 text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-colors min-h-[44px]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
        {{ __('squad.renew') }}
    </button>

    {{-- Sub-modal teleported to body so it sits above the player modal --}}
    <template x-teleport="body">
        <div x-show="open" class="fixed inset-0 z-[60] overflow-y-auto px-4 py-6 sm:px-0" style="display:none">
            {{-- Backdrop --}}
            <div x-show="open" @click="open = false"
                class="fixed inset-0 transition-opacity"
                x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="absolute inset-0 bg-slate-900 opacity-60"></div>
            </div>
            {{-- Dialog --}}
            <div x-show="open"
                class="relative mb-6 bg-white rounded-xl shadow-2xl sm:w-full sm:max-w-md sm:mx-auto"
                x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                <div class="p-5 md:p-6">
                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 pb-4 border-b border-slate-200 mb-4">
                        <div>
                            <h3 class="font-semibold text-slate-900">{{ $gamePlayer->name }}</h3>
                            <p class="text-sm text-slate-500 mt-0.5">{{ __('squad.renew') }}</p>
                        </div>
                        <button @click="open = false" class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-100 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    {{-- Mood + wage context --}}
                    <div class="flex items-center justify-between text-sm mb-4">
                        <span class="font-medium
                            @if($renewalMood['color'] === 'green') text-green-600
                            @elseif($renewalMood['color'] === 'amber') text-amber-600
                            @else text-red-500
                            @endif">
                            <span class="inline-block w-2 h-2 rounded-full mr-1.5
                                @if($renewalMood['color'] === 'green') bg-green-500
                                @elseif($renewalMood['color'] === 'amber') bg-amber-500
                                @else bg-red-500
                                @endif"></span>{{ $renewalMood['label'] }}
                        </span>
                        <span class="text-slate-500">{{ __('transfers.player_demand') }}: <span class="font-semibold text-slate-700">{{ $renewalDemand['formattedWage'] }}{{ __('squad.per_year') }}</span></span>
                    </div>
                    {{-- Form --}}
                    <form method="POST" action="{{ route('game.transfers.renew', [$game->id, $gamePlayer->id]) }}">
                        @csrf
                        <div class="flex items-center justify-between text-xs text-slate-400 mb-3">
                            <span>{{ __('transfers.current_wage') }}: {{ $gamePlayer->formatted_wage }}{{ __('squad.per_year') }}</span>
                        </div>
                        <div class="grid grid-cols-2 space-x-4 mb-4">
                            <div>
                                <label class="text-xs text-slate-500 block mb-1">{{ __('transfers.your_offer') }}</label>
                                <x-money-input name="offer_wage" :value="$renewalMidpoint" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-500 block mb-1">{{ __('transfers.contract_duration') }}</label>
                                <x-select-input name="offered_years" class="w-full focus:border-emerald-500 focus:ring-emerald-500">
                                    @foreach(range(1, 5) as $years)
                                        <option value="{{ $years }}" {{ $years === $renewalDemand['contractYears'] ? 'selected' : '' }}>
                                            {{ trans_choice('transfers.years', $years, ['count' => $years]) }}
                                        </option>
                                    @endforeach
                                </x-select-input>
                            </div>
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors min-h-[44px]">
                            {{ __('transfers.negotiate') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
