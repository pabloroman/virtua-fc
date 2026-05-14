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
$currentAnnualRevenueCents     = $upgrade['current_annual_revenue_cents'];
$currentCapacity               = $upgrade['current_capacity'];
$bindingConstraint             = $upgrade['binding_constraint'];
$nextReputationTier            = $upgrade['next_reputation_tier'];
$revenueRequiredCents          = $upgrade['revenue_required_cents'];

$supplementaryHeadroom         = $upgrade['supplementary_headroom'];
$supplementaryMax              = $upgrade['supplementary_effective_max'];
$supplementaryPerSeat          = $upgrade['supplementary_per_seat_cents'];
$supplementaryMin              = $upgrade['supplementary_min'];
$supplementaryMinTotalCents    = $upgrade['supplementary_min_total_cents'];
$supplementaryStep             = $upgrade['supplementary_step'];
$supplementaryAffordable       = $upgrade['supplementary_affordable'];
$supplementaryNaturalMax       = $upgrade['supplementary_natural_max'];
$supplementaryState            = $upgrade['supplementary_state'];

$standExpansionPerSeat         = $upgrade['stand_expansion_per_seat_cents'];
$standExpansionMinSeats        = $upgrade['stand_expansion_min_seats'];
$standExpansionMinTotalCents   = $upgrade['stand_expansion_min_total_cents'];
$standExpansionMaxSeats        = $upgrade['stand_expansion_max_seats'];
$standExpansionCashMax         = $upgrade['stand_expansion_cash_max'];
$standExpansionLoanMax         = $upgrade['stand_expansion_loan_max'];
$standExpansionStep            = $upgrade['stand_expansion_step'];
$standExpansionCashAffordable  = $upgrade['stand_expansion_cash_affordable'];
$standExpansionLoanAffordable  = $upgrade['stand_expansion_loan_affordable'];
$standExpansionAvailable       = $upgrade['stand_expansion_available'];
$standExpansionState           = $upgrade['stand_expansion_state'];

$rebuildBands                  = $upgrade['rebuild_cost_bands'];
$rebuildEntryPerSeat           = $upgrade['rebuild_entry_per_seat_cents'];
$rebuildMaxCash                = $upgrade['rebuild_max_capacity_cash'];
$canRebuild                    = $upgrade['can_rebuild'];
$rebuildMaxCapacity            = $upgrade['rebuild_max_capacity'];
$rebuildMin                    = $upgrade['rebuild_min'];
$rebuildMinTotalCents          = $upgrade['rebuild_min_total_cents'];
$rebuildStep                   = $upgrade['rebuild_step'];
$rebuildCashAffordable         = $upgrade['rebuild_cash_affordable'];
$rebuildAvailable              = $upgrade['rebuild_available'];
$rebuildState                  = $upgrade['rebuild_state'];

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
    <div class="px-5 py-4">

        @if($activeProject)
            {{-- A project is already in flight. Hide the upgrade options
                 entirely — they're all unactionable, and the history card
                 below already shows what's being built. A single muted
                 status line keeps the section explained without repeating
                 the in-progress state on four disabled rows. --}}
            <div class="text-sm text-text-muted">
                {{ __('club.stadium.upgrades.cta_disabled_by_active_project') }}
            </div>
        @else
            {{-- Upgrade list. Four options rendered as a single column of
                 list-style rows: three capacity tiers (supplementary →
                 stand expansion → full rebuild) and the UEFA facilities
                 upgrade. The state-driven left border encodes financing
                 reachability (green = cash, blue = loan, gold = locked).

                 The x-data wrapper is required: Alpine only processes
                 directives inside an x-data subtree, so without it the
                 $dispatch('open-modal', ...) calls would never fire. --}}
            <div class="flex flex-col gap-3" x-data>

                {{-- Tier 1 · Gradas supletorias --}}
                <x-stadium-upgrade-row
                    :state="$supplementaryState"
                    :actionable="$supplementaryState === 'available_cash'"
                    modal="stadium-supplementary"
                    :label="__('club.stadium.upgrades.cta_supplementary_label')"
                    :title="__('club.stadium.upgrades.cta_supplementary_title')"
                    :cost-label="$supplementaryPerSeat > 0 ? __('club.stadium.upgrades.from_total', ['total' => Money::format($supplementaryMinTotalCents)]) : null"
                    :duration-label="__('club.stadium.upgrades.time_days_inline', ['days' => 30])"
                    :locked-reason="$supplementaryState === 'locked' && $supplementaryHeadroom <= 0
                        ? __('club.stadium.upgrades.cta_supplementary_full_short')
                        : ($supplementaryState === 'locked' ? __('club.stadium.upgrades.cta_supplementary_no_budget_short', ['budget' => Money::format($availableBudgetCents)]) : null)" />

                {{-- Tier 2 · Ampliación de grada --}}
                <x-stadium-upgrade-row
                    :state="$standExpansionState"
                    :actionable="in_array($standExpansionState, ['available_cash', 'available_loan'], true)"
                    modal="stadium-stand-expansion"
                    :label="__('club.stadium.upgrades.cta_stand_expansion_label')"
                    :title="__('club.stadium.upgrades.cta_stand_expansion_title')"
                    :cost-label="$standExpansionPerSeat > 0 ? __('club.stadium.upgrades.from_total', ['total' => Money::format($standExpansionMinTotalCents)]) : null"
                    :duration-label="trans_choice('club.stadium.upgrades.time_seasons_inline', 1, ['count' => 1])"
                    :locked-reason="$standExpansionState === 'locked' ? __('club.stadium.upgrades.cta_stand_expansion_no_budget_short', ['budget' => Money::format($availableBudgetCents)]) : null" />

                {{-- Tier 3 · Reconstruir el estadio --}}
                @php
                    $rebuildClickable = in_array($rebuildState, ['available_cash', 'available_loan'], true);
                    $rebuildLockedReason = match ($rebuildState) {
                        'locked_affordability' => __('club.stadium.upgrades.unlock_with_revenue', ['revenue' => Money::format($revenueRequiredCents)]),
                        'locked_reputation'    => __('club.stadium.upgrades.unlock_with_reputation', [
                            'tier' => __('club.stadium.reputation_tiers.'.($nextReputationTier ?? 'modest')),
                        ]),
                        default => null,
                    };
                @endphp
                <x-stadium-upgrade-row
                    :state="$rebuildState"
                    :actionable="$rebuildClickable"
                    modal="stadium-rebuild"
                    :label="__('club.stadium.upgrades.cta_rebuild_label')"
                    :title="__('club.stadium.upgrades.cta_rebuild_title')"
                    :cost-label="$rebuildEntryPerSeat > 0 ? __('club.stadium.upgrades.from_total', ['total' => Money::format($rebuildMinTotalCents)]) : null"
                    :duration-label="trans_choice('club.stadium.upgrades.time_seasons_inline', 2, ['count' => 2])"
                    :locked-reason="$rebuildLockedReason" />

                {{-- UEFA category upgrade. Capacity-agnostic facility tier
                     upgrade (one step at a time): floodlights, dressing
                     rooms, media facilities. Shares the same row layout
                     as the capacity tiers so all options read as a list. --}}
                @php
                    $uefaActionable = $uefaAvailable;
                    $uefaState = $uefaActionable
                        ? ($uefaCashAffordable ? 'available_cash' : 'available_loan')
                        : 'locked';
                    $uefaLockedReason = match (true) {
                        $uefaBlocker === UefaUpgradeBlocker::NoBaseLevel    => __('club.stadium.upgrades.cta_uefa_no_base_level'),
                        $uefaBlocker === UefaUpgradeBlocker::AlreadyMax     => __('club.stadium.upgrades.cta_uefa_already_max'),
                        $uefaBlocker === UefaUpgradeBlocker::CapacityFloor  => __('club.stadium.upgrades.cta_uefa_capacity_floor', [
                            'target'  => $uefaNextLevel,
                            'min_cap' => number_format($uefaCapacityFloor),
                        ]),
                        ! $uefaAvailable => __('club.stadium.upgrades.cta_uefa_no_budget', [
                            'cost'   => Money::format($uefaUpgradeCost),
                            'budget' => Money::format($availableBudgetCents),
                        ]),
                        default => null,
                    };
                @endphp
                <x-stadium-upgrade-row
                    :state="$uefaState"
                    :actionable="$uefaActionable"
                    modal="stadium-uefa-upgrade"
                    :label="__('club.stadium.upgrades.cta_uefa_label')"
                    :title="$uefaCurrentLevel !== null && $uefaNextLevel !== null
                        ? __('club.stadium.upgrades.cta_uefa_title', ['from' => $uefaCurrentLevel, 'to' => $uefaNextLevel])
                        : __('club.stadium.upgrades.cta_uefa_title_generic')"
                    :cost-label="$uefaActionable ? Money::format($uefaUpgradeCost) : null"
                    :duration-label="$uefaActionable ? trans_choice('club.stadium.upgrades.time_seasons_inline', 1, ['count' => 1]) : null"
                    :locked-reason="$uefaLockedReason" />
            </div>
        @endif

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
                    <span x-text="seats.toLocaleString('es-ES')" class="font-heading text-base font-semibold text-text-primary ml-2"></span>
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
                    <span x-text="seats.toLocaleString('es-ES')" class="font-heading text-base font-semibold text-text-primary ml-2"></span>
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
