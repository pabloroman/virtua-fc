@php
/**
 * @var App\Models\Game $game
 * @var array $upgrade Computed by ShowClubStadium — assignments only, no logic.
 */

use App\Modules\Stadium\Enums\UefaUpgradeBlocker;
use App\Support\Money;

$stadium                       = $upgrade['stadium'];
$activeProject                 = $upgrade['active_project'];
$activeLoan                    = $upgrade['active_loan'];
$loanCapCents                  = $upgrade['loan_cap_cents'];
$availableBudgetCents          = $upgrade['available_budget_cents'];
$currentCapacity               = $upgrade['current_capacity'];
$bindingConstraint             = $upgrade['binding_constraint'];
$nextReputationTier            = $upgrade['next_reputation_tier'];
$revenueRequiredCents          = $upgrade['revenue_required_cents'];

$supplementaryHeadroom         = $upgrade['supplementary_headroom'];
$supplementaryMax              = $upgrade['supplementary_effective_max'];
$supplementaryPerSeat          = $upgrade['supplementary_per_seat_cents'];
$supplementaryMin              = $upgrade['supplementary_min'];
$supplementaryStep             = $upgrade['supplementary_step'];
$supplementaryAffordable       = $upgrade['supplementary_affordable'];
$supplementaryNaturalMax       = $upgrade['supplementary_natural_max'];

$standExpansionPerSeat         = $upgrade['stand_expansion_per_seat_cents'];
$standExpansionMinSeats        = $upgrade['stand_expansion_min_seats'];
$standExpansionMaxSeats        = $upgrade['stand_expansion_max_seats'];
$standExpansionCashMax         = $upgrade['stand_expansion_cash_max'];
$standExpansionLoanMax         = $upgrade['stand_expansion_loan_max'];
$standExpansionStep            = $upgrade['stand_expansion_step'];
$standExpansionCashAffordable  = $upgrade['stand_expansion_cash_affordable'];
$standExpansionLoanAffordable  = $upgrade['stand_expansion_loan_affordable'];
$standExpansionAvailable       = $upgrade['stand_expansion_available'];

$rebuildBands                  = $upgrade['rebuild_cost_bands'];
$rebuildEntryPerSeat           = $upgrade['rebuild_entry_per_seat_cents'];
$rebuildMaxCash                = $upgrade['rebuild_max_capacity_cash'];
$canRebuild                    = $upgrade['can_rebuild'];
$rebuildMaxCapacity            = $upgrade['rebuild_max_capacity'];
$rebuildMin                    = $upgrade['rebuild_min'];
$rebuildStep                   = $upgrade['rebuild_step'];
$rebuildCashAffordable         = $upgrade['rebuild_cash_affordable'];
$rebuildAvailable              = $upgrade['rebuild_available'];

$uefaCurrentLevel              = $upgrade['uefa_current_level'];
$uefaNextLevel                 = $upgrade['uefa_next_level'];
$uefaUpgradeCost               = $upgrade['uefa_upgrade_cost_cents'];
$uefaCapacityFloor             = $upgrade['uefa_capacity_floor'];
$uefaBlocker                   = $upgrade['uefa_blocker'];
$uefaCashAffordable            = $upgrade['uefa_cash_affordable'];
$uefaLoanAffordable            = $upgrade['uefa_loan_affordable'];
$uefaAvailable                 = $upgrade['uefa_available'];
@endphp

<x-section-card :title="__('club.stadium.upgrades.title')">
    <div class="px-5 py-4 space-y-4">

            {{-- CTAs. The x-data wrapper is required: Alpine only processes
                 directives inside an x-data subtree, so without it the
                 $dispatch('open-modal', ...) calls would never fire.

                 Each CTA carries a left-border accent of increasing weight
                 (subtle → strong → gold) plus a chip row (cost rate +
                 time-to-ready) so the three options read as a progressive
                 commitment, not three identical cards.

                 When an active project is in flight, all three CTAs are
                 disabled with the same "ongoing project" hint — the
                 history card below is the single source of truth for what
                 is currently being built. --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3" x-data>
                {{-- Gradas supletorias --}}
                <button
                    type="button"
                    @if($activeProject || ! $supplementaryAffordable) disabled @endif
                    x-on:click="$dispatch('open-modal', 'stadium-supplementary')"
                    class="flex flex-col items-start w-full text-left p-4 rounded-lg border border-border-strong border-l-4 border-l-accent-blue/40 bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <div class="text-[10px] text-text-muted uppercase tracking-widest mb-1">{{ __('club.stadium.upgrades.cta_supplementary_label') }}</div>
                    <div class="font-heading text-base font-bold text-text-primary">{{ __('club.stadium.upgrades.cta_supplementary_title') }}</div>

                    @if(! $activeProject && $supplementaryAffordable && $supplementaryHeadroom > 0)
                        <div class="flex flex-wrap gap-1.5 mt-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-surface-600 text-[10px] font-medium text-text-body uppercase tracking-wider">{{ __('club.stadium.upgrades.chip_per_seat', ['cost' => Money::format($supplementaryPerSeat)]) }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-surface-600 text-[10px] font-medium text-text-body uppercase tracking-wider">{{ __('club.stadium.upgrades.chip_time_days', ['days' => 30]) }}</span>
                        </div>
                    @endif

                    <div class="text-xs text-text-muted mt-3">
                        @if($activeProject)
                            {{ __('club.stadium.upgrades.cta_disabled_by_active_project') }}
                        @elseif($supplementaryHeadroom <= 0)
                            {{ __('club.stadium.upgrades.cta_supplementary_full') }}
                        @elseif(! $supplementaryAffordable)
                            {{ __('club.stadium.upgrades.cta_supplementary_no_budget', [
                                'minimum' => Money::format($supplementaryMin * $supplementaryPerSeat),
                                'budget'  => Money::format($availableBudgetCents),
                            ]) }}
                        @else
                            {{ __('club.stadium.upgrades.cta_supplementary_tagline', [
                                'max' => number_format($supplementaryMax),
                            ]) }}
                        @endif
                    </div>
                </button>

                {{-- Stand expansion --}}
                <button
                    type="button"
                    @if($activeProject || ! $standExpansionAvailable) disabled @endif
                    x-on:click="$dispatch('open-modal', 'stadium-stand-expansion')"
                    class="flex flex-col items-start w-full text-left p-4 rounded-lg border border-border-strong border-l-4 border-l-accent-blue bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <div class="text-[10px] text-accent-blue uppercase tracking-widest mb-1">{{ __('club.stadium.upgrades.cta_stand_expansion_label') }}</div>
                    <div class="font-heading text-base font-bold text-text-primary">{{ __('club.stadium.upgrades.cta_stand_expansion_title') }}</div>

                    @if(! $activeProject && $standExpansionAvailable)
                        <div class="flex flex-wrap gap-1.5 mt-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-surface-600 text-[10px] font-medium text-text-body uppercase tracking-wider">{{ __('club.stadium.upgrades.chip_per_seat', ['cost' => Money::format($standExpansionPerSeat)]) }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-surface-600 text-[10px] font-medium text-text-body uppercase tracking-wider">{{ trans_choice('club.stadium.upgrades.chip_time_seasons', 1, ['count' => 1]) }}</span>
                        </div>
                    @endif

                    <div class="text-xs text-text-muted mt-3">
                        @if($activeProject)
                            {{ __('club.stadium.upgrades.cta_disabled_by_active_project') }}
                        @elseif(! $standExpansionAvailable)
                            {{ __('club.stadium.upgrades.cta_stand_expansion_no_budget', [
                                'minimum' => Money::format($standExpansionMinSeats * $standExpansionPerSeat),
                                'budget'  => Money::format($availableBudgetCents),
                            ]) }}
                        @else
                            {{ __('club.stadium.upgrades.cta_stand_expansion_tagline', [
                                'min' => number_format($standExpansionMinSeats),
                                'max' => number_format($standExpansionMaxSeats),
                            ]) }}
                        @endif
                    </div>
                </button>

                {{-- Rebuild --}}
                <button
                    type="button"
                    @if($activeProject || ! $rebuildAvailable) disabled @endif
                    x-on:click="$dispatch('open-modal', 'stadium-rebuild')"
                    class="flex flex-col items-start w-full text-left p-4 rounded-lg border border-border-strong border-l-4 border-l-accent-gold bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <div class="text-[10px] text-accent-gold uppercase tracking-widest mb-1">{{ __('club.stadium.upgrades.cta_rebuild_label') }}</div>
                    <div class="font-heading text-base font-bold text-text-primary">{{ __('club.stadium.upgrades.cta_rebuild_title') }}</div>

                    @if(! $activeProject && $rebuildAvailable)
                        <div class="flex flex-wrap gap-1.5 mt-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-surface-600 text-[10px] font-medium text-text-body uppercase tracking-wider">{{ __('club.stadium.upgrades.chip_per_seat_from', ['cost' => Money::format($rebuildEntryPerSeat)]) }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-surface-600 text-[10px] font-medium text-text-body uppercase tracking-wider">{{ trans_choice('club.stadium.upgrades.chip_time_seasons', 2, ['count' => 2]) }}</span>
                        </div>
                    @endif

                    <div class="text-xs text-text-muted mt-3">
                        @if($activeProject)
                            {{ __('club.stadium.upgrades.cta_disabled_by_active_project') }}
                        @elseif(! $canRebuild)
                            {{ __('club.stadium.upgrades.cta_rebuild_reputation_lock', [
                                'tier' => __('club.stadium.reputation_tiers.modest'),
                            ]) }}
                        @elseif($rebuildMaxCapacity <= $currentCapacity && $bindingConstraint === 'reputation')
                            @if($nextReputationTier)
                                {{ __('club.stadium.upgrades.cta_rebuild_locked_by_reputation', [
                                    'cap'  => Money::format($loanCapCents),
                                    'max'  => number_format($rebuildMaxCapacity),
                                    'tier' => __('club.stadium.reputation_tiers.'.$nextReputationTier),
                                ]) }}
                            @else
                                {{ __('club.stadium.upgrades.cta_rebuild_locked_at_elite', [
                                    'cap' => Money::format($loanCapCents),
                                    'max' => number_format($rebuildMaxCapacity),
                                ]) }}
                            @endif
                        @elseif($rebuildMaxCapacity <= $currentCapacity)
                            {{ __('club.stadium.upgrades.cta_rebuild_locked_by_affordability', [
                                'cap'     => Money::format($loanCapCents),
                                'max'     => number_format($rebuildMaxCapacity),
                                'revenue' => Money::format($revenueRequiredCents),
                            ]) }}
                        @else
                            {{ __('club.stadium.upgrades.cta_rebuild_tagline', [
                                'max' => number_format($rebuildMaxCapacity),
                            ]) }}
                        @endif
                    </div>
                </button>
            </div>

            {{-- UEFA category upgrade. Capacity-agnostic facility tier
                 upgrade (one step at a time): floodlights, dressing
                 rooms, media facilities. Renders as a compact horizontal
                 card so it reads as a distinct concern from the capacity
                 CTAs above. --}}
            <div x-data
                 class="flex flex-col md:flex-row md:items-center gap-3 p-4 rounded-lg border border-border-strong border-l-4 border-l-accent-green bg-surface-700">
                <div class="flex-1 min-w-0">
                    <div class="text-[10px] text-accent-green uppercase tracking-widest mb-1">{{ __('club.stadium.upgrades.cta_uefa_label') }}</div>
                    <div class="font-heading text-base font-bold text-text-primary">
                        @if($uefaCurrentLevel !== null && $uefaNextLevel !== null)
                            {{ __('club.stadium.upgrades.cta_uefa_title', ['from' => $uefaCurrentLevel, 'to' => $uefaNextLevel]) }}
                        @else
                            {{ __('club.stadium.upgrades.cta_uefa_title_generic') }}
                        @endif
                    </div>
                    <div class="text-xs text-text-muted mt-1.5">
                        @if($activeProject)
                            {{ __('club.stadium.upgrades.cta_disabled_by_active_project') }}
                        @elseif($uefaBlocker === UefaUpgradeBlocker::NoBaseLevel)
                            {{ __('club.stadium.upgrades.cta_uefa_no_base_level') }}
                        @elseif($uefaBlocker === UefaUpgradeBlocker::AlreadyMax)
                            {{ __('club.stadium.upgrades.cta_uefa_already_max') }}
                        @elseif($uefaBlocker === UefaUpgradeBlocker::CapacityFloor)
                            {{ __('club.stadium.upgrades.cta_uefa_capacity_floor', [
                                'target'   => $uefaNextLevel,
                                'min_cap'  => number_format($uefaCapacityFloor),
                            ]) }}
                        @elseif(! $uefaAvailable)
                            {{ __('club.stadium.upgrades.cta_uefa_no_budget', [
                                'cost'   => Money::format($uefaUpgradeCost),
                                'budget' => Money::format($availableBudgetCents),
                            ]) }}
                        @else
                            {{ __('club.stadium.upgrades.cta_uefa_tagline', [
                                'target' => $uefaNextLevel,
                                'cost'   => Money::format($uefaUpgradeCost),
                            ]) }}
                        @endif
                    </div>
                </div>
                <button
                    type="button"
                    @if($activeProject || ! $uefaAvailable) disabled @endif
                    x-on:click="$dispatch('open-modal', 'stadium-uefa-upgrade')"
                    class="shrink-0 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-accent-green/20 hover:bg-accent-green/30 border border-accent-green/40 text-accent-green text-xs font-semibold uppercase tracking-wider disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    {{ __('club.stadium.upgrades.cta_uefa_button') }}
                </button>
            </div>

    </div>
</x-section-card>

@if(! $activeProject)
    {{-- Supplementary stands modal --}}
    @if($supplementaryAffordable)
    <x-modal name="stadium-supplementary" maxWidth="lg">
        <x-modal-header modalName="stadium-supplementary">{{ __('club.stadium.upgrades.modal_supplementary_title') }}</x-modal-header>

        <form method="POST" action="{{ route('game.club.stadium.supplementary', $game->id) }}"
              x-data="{
                  seats: @js(min($supplementaryMin + 500, $supplementaryMax)),
                  min: @js($supplementaryMin),
                  max: @js($supplementaryMax),
                  perSeat: @js($supplementaryPerSeat),
                  fillPercent() {
                      if (this.max <= this.min) return 0;
                      return ((this.seats - this.min) / (this.max - this.min)) * 100;
                  },
                  costLabel() {
                      return '€ ' + ((this.seats * this.perSeat) / 100_000_000).toFixed(1) + 'M';
                  }
              }"
              class="p-6 space-y-4">
            @csrf

            <p class="text-sm text-text-muted">{{ __('club.stadium.upgrades.modal_supplementary_description') }}</p>

            <div>
                <label class="block text-[10px] text-text-muted uppercase tracking-widest mb-2">
                    {{ __('club.stadium.upgrades.seats_to_add') }}
                    <span x-text="seats.toLocaleString('es-ES')" class="font-heading text-base text-text-primary ml-2"></span>
                </label>
                <input type="range" name="seats"
                       min="{{ $supplementaryMin }}"
                       max="{{ $supplementaryMax }}"
                       step="{{ $supplementaryStep }}"
                       x-model.number="seats"
                       :style="`--fill: ${fillPercent()}%`"
                       class="season-ticket-slider w-full">
                <div class="flex justify-between text-xs text-text-faint mt-1">
                    <span>{{ number_format($supplementaryMin) }}</span>
                    <span>{{ number_format($supplementaryMax) }}</span>
                </div>
                @if($supplementaryMax < $supplementaryNaturalMax)
                    <div class="text-[11px] text-text-faint mt-2">
                        {{ __('club.stadium.upgrades.budget_caps_slider', [
                            'budget'  => Money::format($availableBudgetCents),
                            'natural' => number_format($supplementaryNaturalMax),
                        ]) }}
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-border-strong">
                <span class="text-sm text-text-muted">{{ __('club.stadium.upgrades.total_cost') }}</span>
                <span class="font-heading text-lg font-bold text-text-primary" x-text="costLabel()"></span>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'stadium-supplementary')">
                    {{ __('app.cancel') }}
                </x-secondary-button>
                <x-primary-button>
                    {{ __('club.stadium.upgrades.commit_supplementary') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
    @endif

    {{-- Stand expansion modal --}}
    @if($standExpansionAvailable)
    <x-modal name="stadium-stand-expansion" maxWidth="xl">
        <x-modal-header modalName="stadium-stand-expansion">{{ __('club.stadium.upgrades.modal_stand_expansion_title') }}</x-modal-header>

        <form method="POST" action="{{ route('game.club.stadium.expansion', $game->id) }}"
              x-data="{
                  seats: @js(min($standExpansionMinSeats + 1000, max($standExpansionCashMax, $standExpansionLoanMax))),
                  financing: @js($standExpansionCashAffordable ? 'cash' : 'loan'),
                  min: @js($standExpansionMinSeats),
                  designMax: @js($standExpansionMaxSeats),
                  cashMax: @js($standExpansionCashMax),
                  loanMax: @js($standExpansionLoanMax),
                  perSeat: @js($standExpansionPerSeat),
                  effectiveMax() {
                      return this.financing === 'cash' ? this.cashMax : this.loanMax;
                  },
                  fillPercent() {
                      const max = this.effectiveMax();
                      if (max <= this.min) return 0;
                      return ((this.seats - this.min) / (max - this.min)) * 100;
                  },
                  costCents() { return this.seats * this.perSeat; },
                  costLabel() { return '€ ' + (this.costCents() / 100_000_000).toFixed(1) + 'M'; },
                  cashAffordable() { return this.cashMax >= this.min; },
                  loanAffordable() { return this.loanMax >= this.min; }
              }"
              x-effect="if (seats > effectiveMax()) seats = effectiveMax(); if (seats < min) seats = min"
              class="p-6 space-y-4">
            @csrf

            <p class="text-sm text-text-muted">{{ __('club.stadium.upgrades.modal_stand_expansion_description') }}</p>

            <x-stadium-financing-toggle
                cash-affordable="cashAffordable()"
                loan-affordable="loanAffordable()"
                :loan-cap-cents="$loanCapCents"
                :budget-cents="$availableBudgetCents" />

            <div>
                <label class="block text-[10px] text-text-muted uppercase tracking-widest mb-2">
                    {{ __('club.stadium.upgrades.seats_to_add') }}
                    <span x-text="seats.toLocaleString('es-ES')" class="font-heading text-base text-text-primary ml-2"></span>
                </label>
                <input type="range" name="seats"
                       :min="min"
                       :max="effectiveMax()"
                       step="{{ $standExpansionStep }}"
                       x-model.number="seats"
                       :style="`--fill: ${fillPercent()}%`"
                       class="season-ticket-slider w-full">
                <div class="flex justify-between text-xs text-text-faint mt-1">
                    <span x-text="min.toLocaleString('es-ES')"></span>
                    <span x-text="effectiveMax().toLocaleString('es-ES')"></span>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-border-strong">
                <span class="text-sm text-text-muted">{{ __('club.stadium.upgrades.total_cost') }}</span>
                <span class="font-heading text-lg font-bold text-text-primary" x-text="costLabel()"></span>
            </div>

            <x-status-banner color="blue" :description="__('club.stadium.upgrades.stand_expansion_disruption_note')" />

            <div class="flex justify-end gap-3 pt-4">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'stadium-stand-expansion')">
                    {{ __('app.cancel') }}
                </x-secondary-button>
                <x-primary-button>
                    {{ __('club.stadium.upgrades.commit_stand_expansion') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
    @endif

    {{-- UEFA category upgrade modal --}}
    @if($uefaAvailable)
    <x-modal name="stadium-uefa-upgrade" maxWidth="lg">
        <x-modal-header modalName="stadium-uefa-upgrade">{{ __('club.stadium.upgrades.modal_uefa_title', ['from' => $uefaCurrentLevel, 'to' => $uefaNextLevel]) }}</x-modal-header>

        <form method="POST" action="{{ route('game.club.stadium.uefa-upgrade', $game->id) }}"
              x-data="{
                  financing: @js($uefaCashAffordable ? 'cash' : 'loan'),
                  cashAffordable: @js($uefaCashAffordable),
                  loanAffordable: @js($uefaLoanAffordable)
              }"
              class="p-6 space-y-4">
            @csrf

            <p class="text-sm text-text-muted">{{ __('club.stadium.upgrades.modal_uefa_description') }}</p>

            <div class="flex items-center justify-between p-3 rounded-lg bg-surface-700 border border-border-default">
                <div>
                    <div class="text-[10px] text-text-muted uppercase tracking-widest mb-0.5">{{ __('club.stadium.upgrades.uefa_transition_label') }}</div>
                    <div class="font-heading text-base font-bold text-text-primary">
                        <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-md bg-surface-600 text-text-body tabular-nums">{{ $uefaCurrentLevel }}</span>
                        <span class="mx-1 text-text-faint">→</span>
                        <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-md bg-accent-green/20 text-accent-green tabular-nums">{{ $uefaNextLevel }}</span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] text-text-muted uppercase tracking-widest mb-0.5">{{ __('club.stadium.upgrades.total_cost') }}</div>
                    <div class="font-heading text-base font-bold text-text-primary">{{ Money::format($uefaUpgradeCost) }}</div>
                </div>
            </div>

            <x-stadium-financing-toggle
                cash-affordable="cashAffordable"
                loan-affordable="loanAffordable"
                :loan-cap-cents="$loanCapCents"
                :budget-cents="$availableBudgetCents" />

            <x-status-banner color="blue" :description="__('club.stadium.upgrades.uefa_no_capacity_change_note')" />

            <div class="flex justify-end gap-3 pt-4">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'stadium-uefa-upgrade')">
                    {{ __('app.cancel') }}
                </x-secondary-button>
                <x-primary-button>
                    {{ __('club.stadium.upgrades.commit_uefa_upgrade') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
    @endif

    {{-- Rebuild modal --}}
    @if($rebuildAvailable)
    <x-modal name="stadium-rebuild" maxWidth="xl">
        <x-modal-header modalName="stadium-rebuild">{{ __('club.stadium.upgrades.modal_rebuild_title') }}</x-modal-header>

        <form method="POST" action="{{ route('game.club.stadium.rebuild', $game->id) }}"
              x-data="{
                  capacity: @js(min($rebuildMin + 5000, $rebuildMaxCapacity)),
                  financing: @js($rebuildCashAffordable ? 'cash' : 'loan'),
                  min: @js($rebuildMin),
                  maxLoan: @js($rebuildMaxCapacity),
                  maxCash: @js($rebuildMaxCash),
                  bands: @js($rebuildBands),
                  effectiveMax() {
                      return this.financing === 'cash'
                          ? Math.min(this.maxLoan, this.maxCash)
                          : this.maxLoan;
                  },
                  fillPercent() {
                      const max = this.effectiveMax();
                      if (max <= this.min) return 0;
                      return ((this.capacity - this.min) / (max - this.min)) * 100;
                  },
                  // Cumulative bracket pricing: walk the bands in order, paying
                  // the band's rate for each seat that falls within it.
                  costCents() {
                      let remaining = this.capacity;
                      let cost = 0;
                      let prevCap = 0;
                      for (const band of this.bands) {
                          const upTo = band.up_to;
                          const rate = band.per_seat_cents;
                          const bandSeats = upTo === null ? remaining : Math.min(remaining, upTo - prevCap);
                          if (bandSeats <= 0) {
                              if (upTo !== null) prevCap = upTo;
                              continue;
                          }
                          cost += bandSeats * rate;
                          remaining -= bandSeats;
                          prevCap = upTo === null ? prevCap : upTo;
                          if (remaining <= 0) break;
                      }
                      return cost;
                  },
                  costLabel() { return '€ ' + (this.costCents() / 100_000_000).toFixed(1) + 'M'; },
                  // Marginal per-seat rate at the current capacity — surfaces
                  // when crossing a band boundary so the user can see why the
                  // slope just got steeper.
                  marginalPerSeat() {
                      let prevCap = 0;
                      for (const band of this.bands) {
                          if (band.up_to === null || this.capacity <= band.up_to) {
                              return band.per_seat_cents;
                          }
                          prevCap = band.up_to;
                      }
                      return this.bands[this.bands.length - 1].per_seat_cents;
                  },
                  marginalLabel() { return '€ ' + (this.marginalPerSeat() / 100).toLocaleString('es-ES', {maximumFractionDigits: 0}); },
                  cashAffordable() { return this.maxCash >= this.min; }
              }"
              x-effect="if (capacity > effectiveMax()) capacity = effectiveMax()"
              class="p-6 space-y-4">
            @csrf

            <p class="text-sm text-text-muted">{{ __('club.stadium.upgrades.modal_rebuild_description') }}</p>

            {{-- Loan is the always-reachable path here (the CTA itself is
                 gated on rebuildAvailable, which proxies loan reachability). --}}
            <x-stadium-financing-toggle
                cash-affordable="cashAffordable()"
                loan-affordable="true"
                :loan-cap-cents="$loanCapCents"
                :budget-cents="$availableBudgetCents" />

            <div>
                <label class="block text-[10px] text-text-muted uppercase tracking-widest mb-2">
                    {{ __('club.stadium.upgrades.target_capacity') }}
                    <span x-text="capacity.toLocaleString('es-ES')" class="font-heading text-base text-text-primary ml-2"></span>
                </label>
                <input type="range" name="capacity"
                       :min="min"
                       :max="effectiveMax()"
                       step="{{ $rebuildStep }}"
                       x-model.number="capacity"
                       :style="`--fill: ${fillPercent()}%`"
                       class="season-ticket-slider w-full">
                <div class="flex justify-between text-xs text-text-faint mt-1">
                    <span x-text="min.toLocaleString('es-ES')"></span>
                    <span x-text="effectiveMax().toLocaleString('es-ES')"></span>
                </div>
                <div class="text-[11px] text-text-faint mt-2">
                    {{ __('club.stadium.upgrades.rebuild_marginal_rate_prefix') }} <span x-text="marginalLabel()"></span> {{ __('club.stadium.upgrades.rebuild_marginal_rate_suffix') }}
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-border-strong">
                <span class="text-sm text-text-muted">{{ __('club.stadium.upgrades.total_cost') }}</span>
                <span class="font-heading text-lg font-bold text-text-primary" x-text="costLabel()"></span>
            </div>

            <x-status-banner color="gold" :description="__('club.stadium.upgrades.rebuild_disruption_warning')" />

            <div class="flex justify-end gap-3 pt-4">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'stadium-rebuild')">
                    {{ __('app.cancel') }}
                </x-secondary-button>
                <x-primary-button>
                    {{ __('club.stadium.upgrades.commit_rebuild') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
    @endif
@endif
