{{-- Shared header for Salidas / Fichajes tabs --}}
<div class="mb-6">
    {{-- Stats Banner --}}
    <div class="bg-surface-700/50 border rounded-lg px-5 py-3.5 flex flex-col md:flex-row md:items-center md:justify-between gap-3 md:gap-6">
        {{-- Transfer Budget --}}
        @if($game->currentInvestment)
        @php $committedBudget = \App\Models\TransferOffer::committedBudget($game->id); @endphp
        <div class="flex items-center gap-2">
            <span class="text-sm text-text-muted">{{ __('transfers.budget') }}</span>
            <span class="text-sm font-semibold text-text-primary">{{ $game->currentInvestment->formatted_transfer_budget }}</span>
            @if($committedBudget > 0)
            <span class="text-xs text-amber-600">({{ \App\Support\Money::format($committedBudget) }} {{ __('transfers.budget_committed') }})</span>
            @endif
        </div>
        @endif

        {{-- Window Status --}}
        <div class="flex items-center gap-2">
            @if($isTransferWindow)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-accent-green/10 text-green-800">
                    <span class="w-1.5 h-1.5 bg-accent-green rounded-full animate-pulse"></span>
                    {{ __('transfers.window_open', ['window' => $currentWindow]) }}
                </span>
            @else
                <span class="text-sm text-text-muted">{{ __('transfers.window') }}:</span>
                <span class="text-sm font-semibold text-text-primary">{{ __('app.window_closed') }}</span>
            @endif
            @if(isset($windowCountdown) && $windowCountdown)
                <span class="text-xs text-text-secondary">
                    @if($windowCountdown['action'] === 'closes')
                        {{ __('transfers.window_closes_in', ['date' => $windowCountdown['date']->locale(app()->getLocale())->translatedFormat('d M Y')]) }}
                    @else
                        {{ __('transfers.window_opens_in', ['date' => $windowCountdown['date']->locale(app()->getLocale())->translatedFormat('d M Y')]) }}
                    @endif
                </span>
            @endif
        </div>

        {{-- Wage Bill --}}
        <div class="flex items-center gap-2">
            <span class="text-sm text-text-muted">{{ __('transfers.wage_bill') }}</span>
            <span class="text-sm font-semibold text-text-primary">{{ \App\Support\Money::format($totalWageBill) }}{{ __('squad.per_year') }}</span>
        </div>
    </div>
</div>
