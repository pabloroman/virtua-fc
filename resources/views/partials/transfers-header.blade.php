{{-- Shared header for Salidas / Fichajes tabs --}}
<div class="flex gap-2.5 overflow-x-auto scrollbar-hide pb-1 mb-6">
    {{-- Transfer Budget --}}
    @if($game->currentInvestment)
    @php $committedBudget = \App\Models\TransferOffer::committedBudget($game->id); @endphp
    <x-summary-card :label="__('transfers.budget')">
        <div class="font-heading text-xl font-bold text-text-primary mt-0.5">{{ $game->currentInvestment->formatted_transfer_budget }}</div>
        @if($committedBudget > 0)
        <div class="text-[10px] text-accent-gold">{{ \App\Support\Money::format($committedBudget) }} {{ __('transfers.budget_committed') }}</div>
        @endif
    </x-summary-card>
    @endif

    {{-- Window Status --}}
    <x-summary-card :label="__('transfers.window')">
        <div class="mt-0.5">
            @if($isTransferWindow)
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-accent-green/10 text-accent-green">
                    <span class="w-1.5 h-1.5 bg-accent-green rounded-full animate-pulse"></span>
                    {{ $currentWindow }}
                </span>
            @else
                <div class="font-heading text-xl font-bold text-text-primary">{{ __('app.window_closed') }}</div>
            @endif
        </div>
        @if(isset($windowCountdown) && $windowCountdown)
        <div class="text-[10px] text-text-muted mt-0.5">
            @if($windowCountdown['action'] === 'closes')
                {{ __('transfers.window_closes_in', ['date' => $windowCountdown['date']->locale(app()->getLocale())->translatedFormat('d M Y')]) }}
            @else
                {{ __('transfers.window_opens_in', ['date' => $windowCountdown['date']->locale(app()->getLocale())->translatedFormat('d M Y')]) }}
            @endif
        </div>
        @endif
    </x-summary-card>

    {{-- Wage Bill --}}
    <x-summary-card :label="__('transfers.wage_bill')" :value="\App\Support\Money::format($totalWageBill) . __('squad.per_year')" />
</div>
