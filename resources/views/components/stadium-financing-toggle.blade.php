@props([
    // Alpine expressions, evaluated in the parent x-data scope. The
    // parent form holds the `financing` reactive prop, so the radios
    // bind to it via x-model.
    'cashAffordable' => 'true',
    'loanAffordable' => 'true',
    'loanCapCents' => 0,
    'budgetCents' => 0,
])

<div>
    <label class="block text-[10px] text-text-muted uppercase tracking-widest mb-2">{{ __('club.stadium.upgrades.financing') }}</label>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <label class="flex items-center gap-2 p-3 rounded-lg border"
               :class="{
                   'border-accent-blue bg-accent-blue/10': financing === 'cash',
                   'border-border-strong bg-surface-700': financing !== 'cash',
                   'opacity-50 cursor-not-allowed': !({{ $cashAffordable }}),
                   'cursor-pointer': {{ $cashAffordable }}
               }">
            <input type="radio" name="financing" value="cash" x-model="financing"
                   :disabled="!({{ $cashAffordable }})" class="text-accent-blue">
            <span class="text-sm font-medium text-text-primary">{{ __('club.stadium.upgrades.financing_cash') }}</span>
        </label>
        <label class="flex items-center gap-2 p-3 rounded-lg border"
               :class="{
                   'border-accent-blue bg-accent-blue/10': financing === 'loan',
                   'border-border-strong bg-surface-700': financing !== 'loan',
                   'opacity-50 cursor-not-allowed': !({{ $loanAffordable }}),
                   'cursor-pointer': {{ $loanAffordable }}
               }">
            <input type="radio" name="financing" value="loan" x-model="financing"
                   :disabled="!({{ $loanAffordable }})" class="text-accent-blue">
            <span class="text-sm font-medium text-text-primary">{{ __('club.stadium.upgrades.financing_loan') }}</span>
        </label>
    </div>
    <div class="text-xs text-text-muted mt-2">
        <template x-if="financing === 'loan'">
            <span>{{ __('club.stadium.upgrades.financing_loan_hint', ['cap' => \App\Support\Money::format($loanCapCents)]) }}</span>
        </template>
        <template x-if="financing === 'cash'">
            <span>{{ __('club.stadium.upgrades.financing_cash_hint_budget', ['budget' => \App\Support\Money::format($budgetCents)]) }}</span>
        </template>
    </div>
</div>
