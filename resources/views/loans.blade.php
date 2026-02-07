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
                <div class="p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="font-semibold text-xl text-slate-900">{{ __('transfers.title') }}</h3>
                        <div class="flex items-center gap-6 text-sm">
                            <div class="text-slate-600">
                                @if($isTransferWindow)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                        {{ __('transfers.window_open', ['window' => $currentWindow]) }}
                                    </span>
                                @else
                                    {{ __('transfers.window') }}: <span class="font-semibold text-slate-900">{{ __('transfers.closed') }}</span>
                                @endif
                            </div>
                            @if($game->currentInvestment)
                            <div class="text-slate-600">
                                {{ __('transfers.budget') }}: <span class="font-semibold text-slate-900">{{ $game->currentInvestment->formatted_transfer_budget }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tab Navigation --}}
                    <x-transfers-nav :game="$game" active="loans" />

                    {{-- Loans In --}}
                    <div class="mt-6 mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                            {{ __('transfers.active_loans_in') }}
                            @if($loansIn->isNotEmpty())
                                <span class="text-sm font-normal text-slate-500">({{ $loansIn->count() }})</span>
                            @endif
                        </h4>
                        @if($loansIn->isEmpty())
                            <div class="text-center py-6 text-slate-500 border rounded-lg bg-slate-50 text-sm">
                                {{ __('transfers.no_loans_in') }}
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($loansIn as $loan)
                                <div class="border border-green-200 bg-green-50 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-4">
                                            <img src="{{ $loan->parentTeam->image }}" class="w-8 h-8">
                                            <div>
                                                <div class="font-semibold text-slate-900">{{ $loan->gamePlayer->name }}</div>
                                                <div class="text-sm text-slate-600">
                                                    {{ $loan->gamePlayer->position }} &middot; {{ $loan->gamePlayer->age }} {{ __('transfers.years') }}
                                                    &middot; {{ __('transfers.loaned_from', ['team' => $loan->parentTeam->name]) }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right text-sm text-slate-600">
                                            {{ __('transfers.returns') }}: <span class="font-medium">{{ $loan->return_at->format('M j, Y') }}</span>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Loans Out --}}
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
                            {{ __('transfers.active_loans_out') }}
                            @if($loansOut->isNotEmpty())
                                <span class="text-sm font-normal text-slate-500">({{ $loansOut->count() }})</span>
                            @endif
                        </h4>
                        @if($loansOut->isEmpty())
                            <div class="text-center py-6 text-slate-500 border rounded-lg bg-slate-50 text-sm">
                                {{ __('transfers.no_loans_out') }}
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($loansOut as $loan)
                                <div class="border border-amber-200 bg-amber-50 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-4">
                                            <img src="{{ $loan->loanTeam->image }}" class="w-8 h-8">
                                            <div>
                                                <div class="font-semibold text-slate-900">{{ $loan->gamePlayer->name }}</div>
                                                <div class="text-sm text-slate-600">
                                                    {{ $loan->gamePlayer->position }} &middot; {{ $loan->gamePlayer->age }} {{ __('transfers.years') }}
                                                    &middot; {{ __('transfers.loaned_to', ['team' => $loan->loanTeam->name]) }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right text-sm text-slate-600">
                                            {{ __('transfers.returns') }}: <span class="font-medium">{{ $loan->return_at->format('M j, Y') }}</span>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
