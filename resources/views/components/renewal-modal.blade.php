@props([
    'game',
    'gamePlayer',
    'renewalDemand',
    'renewalMidpoint',
    'renewalMood',
])

<div x-data="{ open: false }" {{ $attributes->merge(['class' => '']) }}>
    <x-action-button color="green" type="button" @click="open = true">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
        {{ __('squad.renew') }}
    </x-action-button>

    {{-- Sub-modal teleported to body so it sits above the player modal --}}
    <template x-teleport="body">
        <div x-show="open" class="fixed inset-0 z-60 overflow-y-auto px-4 py-6 sm:px-0" style="display:none">
            {{-- Backdrop --}}
            <div x-show="open" @click="open = false"
                class="fixed inset-0 transition-opacity"
                x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="absolute inset-0 bg-surface-900 opacity-60"></div>
            </div>
            {{-- Dialog --}}
            <div x-show="open"
                class="relative mb-6 bg-surface-800 rounded-xl shadow-2xl sm:w-full sm:max-w-md sm:mx-auto"
                x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                <div class="p-5 md:p-6">
                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 pb-4 border-b border-border-strong mb-4">
                        <div>
                            <h3 class="font-semibold text-text-primary">{{ $gamePlayer->name }}</h3>
                            <p class="text-sm text-text-muted mt-0.5">{{ __('squad.renew') }}</p>
                        </div>
                        <x-icon-button size="sm" @click="open = false">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </x-icon-button>
                    </div>
                    {{-- Mood + wage context --}}
                    <div class="flex items-center justify-between text-sm mb-4">
                        <span class="font-medium
                            @if($renewalMood['color'] === 'green') text-accent-green
                            @elseif($renewalMood['color'] === 'amber') text-amber-600
                            @else text-red-500
                            @endif">
                            <span class="inline-block w-2 h-2 rounded-full mr-1.5
                                @if($renewalMood['color'] === 'green') bg-accent-green
                                @elseif($renewalMood['color'] === 'amber') bg-accent-gold
                                @else bg-accent-red
                                @endif"></span>{{ $renewalMood['label'] }}
                        </span>
                        <span class="text-text-muted">{{ __('transfers.player_demand') }}: <span class="font-semibold text-text-body">{{ $renewalDemand['formattedWage'] }}{{ __('squad.per_year') }}</span></span>
                    </div>
                    {{-- Form --}}
                    <form method="POST" action="{{ route('game.transfers.renew', [$game->id, $gamePlayer->id]) }}">
                        @csrf
                        <div class="flex items-center justify-between text-xs text-text-secondary mb-3">
                            <span>{{ __('transfers.current_wage') }}: {{ $gamePlayer->formatted_wage }}{{ __('squad.per_year') }}</span>
                        </div>
                        <div class="grid grid-cols-2 space-x-4 mb-4">
                            <div>
                                <label class="text-xs text-text-muted block mb-1">{{ __('transfers.your_offer') }}</label>
                                <x-money-input name="offer_wage" :value="$renewalMidpoint" />
                            </div>
                            <div>
                                <label class="text-xs text-text-muted block mb-1">{{ __('transfers.contract_duration') }}</label>
                                <x-select-input name="offered_years" class="w-full focus:border-accent-green focus:ring-accent-green">
                                    @foreach(range(1, 5) as $years)
                                        <option value="{{ $years }}" {{ $years === $renewalDemand['contractYears'] ? 'selected' : '' }}>
                                            {{ trans_choice('transfers.years', $years, ['count' => $years]) }}
                                        </option>
                                    @endforeach
                                </x-select-input>
                            </div>
                        </div>
                        <x-primary-button color="green">
                            {{ __('transfers.negotiate') }}
                        </x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
