{{-- Shared header for Salidas / Fichajes tabs --}}
<div class="mb-6">
    {{-- Stats Banner --}}
    <div class="bg-slate-50 border rounded-lg px-5 py-3.5 flex flex-col md:flex-row md:items-center md:justify-between gap-3 md:gap-6">
        {{-- Transfer Budget --}}
        @if($game->currentInvestment)
        <div class="flex items-center gap-2">
            <span class="text-sm text-slate-500">{{ __('transfers.budget') }}</span>
            <span class="text-sm font-bold text-slate-900">{{ $game->currentInvestment->formatted_transfer_budget }}</span>
        </div>
        @endif

        {{-- Window Status --}}
        <div class="flex items-center gap-2">
            @if($isTransferWindow)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                    {{ __('transfers.window_open', ['window' => $currentWindow]) }}
                </span>
            @else
                <span class="text-sm text-slate-500">{{ __('transfers.window') }}:</span>
                <span class="text-sm font-semibold text-slate-900">{{ __('app.window_closed') }}</span>
            @endif
            @if(isset($windowCountdown) && $windowCountdown)
                <span class="text-xs text-slate-400">
                    @if($windowCountdown['action'] === 'closes')
                        {{ __('transfers.window_closes_in', ['window' => $windowCountdown['window'], 'matchdays' => $windowCountdown['matchdays']]) }}
                    @else
                        {{ __('transfers.window_opens_in', ['window' => $windowCountdown['window'], 'matchdays' => $windowCountdown['matchdays']]) }}
                    @endif
                </span>
            @endif
        </div>

        {{-- Wage Bill --}}
        <div class="flex items-center gap-2">
            <span class="text-sm text-slate-500">{{ __('transfers.wage_bill') }}</span>
            <span class="text-sm font-semibold text-slate-900">{{ \App\Support\Money::format($totalWageBill) }}{{ __('squad.per_year') }}</span>
        </div>
    </div>
</div>
