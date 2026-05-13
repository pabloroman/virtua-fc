@php
/**
 * @var App\Models\Game                          $game
 * @var App\Models\GameStadium                   $stadium
 * @var ?App\Models\GameStadiumProject           $activeProject
 * @var ?App\Models\StadiumLoan                  $activeLoan
 * @var int                                      $supplementaryHeadroom
 * @var int                                      $supplementaryPerSeat
 * @var int                                      $rebuildPerSeat
 * @var bool                                     $canRebuild
 * @var int                                      $rebuildMaxCapacity
 * @var int                                      $loanCapCents
 * @var string                                   $reputationLevel
 */

use App\Models\GameStadiumProject;
use App\Support\Money;

$stadium = $upgrade['stadium'];
$activeProject = $upgrade['active_project'];
$activeLoan = $upgrade['active_loan'];
$supplementaryHeadroom = $upgrade['supplementary_headroom'];
$supplementaryPerSeat = $upgrade['supplementary_per_seat_cents'];
$rebuildPerSeat = $upgrade['rebuild_per_seat_cents'];
$canRebuild = $upgrade['can_rebuild'];
$rebuildMaxCapacity = $upgrade['rebuild_max_capacity'];
$loanCapCents = $upgrade['loan_cap_cents'];
$reputationLevel = $upgrade['reputation_level'];

$currentCapacity = $stadium->effective_capacity;
@endphp

<x-section-card :title="__('club.stadium.upgrades.title')">
    <div class="px-5 py-4 space-y-4">

        {{-- Capacity breakdown --}}
        <div class="grid grid-cols-3 gap-3">
            <div>
                <div class="text-[10px] text-text-muted uppercase tracking-widest mb-1">{{ __('club.stadium.upgrades.base_capacity') }}</div>
                <div class="font-heading text-lg font-bold text-text-primary">{{ number_format($stadium->rebuilt_capacity ?? $stadium->base_capacity) }}</div>
            </div>
            <div>
                <div class="text-[10px] text-text-muted uppercase tracking-widest mb-1">{{ __('club.stadium.upgrades.supplementary') }}</div>
                <div class="font-heading text-lg font-bold text-text-primary">+{{ number_format($stadium->supplementary_seats) }}</div>
            </div>
            <div>
                <div class="text-[10px] text-text-muted uppercase tracking-widest mb-1">{{ __('club.stadium.upgrades.total') }}</div>
                <div class="font-heading text-lg font-bold text-accent-blue">{{ number_format($currentCapacity) }}</div>
            </div>
        </div>

        @if($activeProject)
            {{-- In-progress project card --}}
            <div class="border border-border-strong rounded-lg p-4 bg-surface-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-[10px] text-text-muted uppercase tracking-widest mb-1">{{ __("club.stadium.upgrades.project_{$activeProject->type}") }}</div>
                        <div class="font-heading text-lg font-bold text-text-primary">
                            @if($activeProject->isSupplementary())
                                +{{ number_format($activeProject->target_capacity) }} {{ __('club.stadium.upgrades.seats') }}
                            @else
                                {{ number_format($activeProject->target_capacity) }} {{ __('club.stadium.upgrades.seats_total') }}
                            @endif
                        </div>
                        <div class="text-xs text-text-muted mt-2">
                            @if($activeProject->isSupplementary() && $activeProject->completion_date)
                                {{ __('club.stadium.upgrades.ready_on', ['date' => $activeProject->completion_date->isoFormat('LL')]) }}
                            @elseif($activeProject->isRebuild())
                                {{ __('club.stadium.upgrades.ready_in_season', ['season' => $activeProject->completion_season]) }}
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] text-text-muted uppercase tracking-widest mb-1">{{ __('club.stadium.upgrades.financing_'.$activeProject->financing) }}</div>
                        <div class="font-heading text-lg font-bold text-text-primary">{{ $activeProject->formatted_total_cost }}</div>
                        @if($activeLoan)
                            <div class="text-xs text-text-muted mt-2">
                                {{ __('club.stadium.upgrades.loan_remaining', ['amount' => $activeLoan->formatted_remaining_principal]) }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            {{-- CTAs --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                {{-- Gradas supletorias --}}
                <button
                    type="button"
                    @if($supplementaryHeadroom <= 0) disabled @endif
                    x-on:click="$dispatch('open-modal', 'stadium-supplementary')"
                    class="w-full text-left p-4 rounded-lg border border-border-strong bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <div class="text-[10px] text-text-muted uppercase tracking-widest mb-1">{{ __('club.stadium.upgrades.cta_supplementary_label') }}</div>
                    <div class="font-heading text-base font-bold text-text-primary">{{ __('club.stadium.upgrades.cta_supplementary_title') }}</div>
                    <div class="text-xs text-text-muted mt-2">
                        @if($supplementaryHeadroom > 0)
                            {{ __('club.stadium.upgrades.cta_supplementary_hint', [
                                'max' => number_format($supplementaryHeadroom),
                                'cost' => Money::format($supplementaryPerSeat),
                            ]) }}
                        @else
                            {{ __('club.stadium.upgrades.cta_supplementary_full') }}
                        @endif
                    </div>
                </button>

                {{-- Rebuild --}}
                <button
                    type="button"
                    @if(! $canRebuild || $rebuildMaxCapacity <= $currentCapacity) disabled @endif
                    x-on:click="$dispatch('open-modal', 'stadium-rebuild')"
                    class="w-full text-left p-4 rounded-lg border border-border-strong bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <div class="text-[10px] text-text-muted uppercase tracking-widest mb-1">{{ __('club.stadium.upgrades.cta_rebuild_label') }}</div>
                    <div class="font-heading text-base font-bold text-text-primary">{{ __('club.stadium.upgrades.cta_rebuild_title') }}</div>
                    <div class="text-xs text-text-muted mt-2">
                        @if(! $canRebuild)
                            {{ __('club.stadium.upgrades.cta_rebuild_reputation_lock') }}
                        @elseif($rebuildMaxCapacity <= $currentCapacity)
                            {{ __('club.stadium.upgrades.cta_rebuild_no_headroom') }}
                        @else
                            {{ __('club.stadium.upgrades.cta_rebuild_hint', [
                                'max' => number_format($rebuildMaxCapacity),
                                'cost' => Money::format($rebuildPerSeat),
                            ]) }}
                        @endif
                    </div>
                </button>
            </div>
        @endif

    </div>
</x-section-card>

@if(! $activeProject)
    {{-- Supplementary stands modal --}}
    <x-modal name="stadium-supplementary" maxWidth="lg">
        <form method="POST" action="{{ route('game.club.stadium.supplementary', $game->id) }}"
              x-data="{ seats: {{ min(1000, $supplementaryHeadroom) }} }"
              class="p-6 space-y-4">
            @csrf

            <h3 class="font-heading text-lg font-bold text-text-primary">{{ __('club.stadium.upgrades.modal_supplementary_title') }}</h3>
            <p class="text-sm text-text-muted">{{ __('club.stadium.upgrades.modal_supplementary_description') }}</p>

            <div>
                <label class="block text-[10px] text-text-muted uppercase tracking-widest mb-2">
                    {{ __('club.stadium.upgrades.seats_to_add') }}
                    <span x-text="seats.toLocaleString('es-ES')" class="font-heading text-base text-text-primary ml-2"></span>
                </label>
                <input type="range" name="seats" min="500" max="{{ $supplementaryHeadroom }}" step="100"
                       x-model.number="seats"
                       class="w-full">
                <div class="flex justify-between text-xs text-text-faint mt-1">
                    <span>500</span>
                    <span>{{ number_format($supplementaryHeadroom) }}</span>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-border-strong">
                <span class="text-sm text-text-muted">{{ __('club.stadium.upgrades.total_cost') }}</span>
                <span class="font-heading text-lg font-bold text-text-primary"
                      x-text="'€ ' + ((seats * {{ $supplementaryPerSeat }}) / 100_000_000).toFixed(1) + 'M'"></span>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" x-on:click="$dispatch('close')" class="px-4 py-2 text-sm text-text-muted hover:text-text-primary">
                    {{ __('app.cancel') }}
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-lg bg-accent-blue hover:bg-accent-blue/90 text-white">
                    {{ __('club.stadium.upgrades.commit_supplementary') }}
                </button>
            </div>
        </form>
    </x-modal>

    {{-- Rebuild modal --}}
    @if($canRebuild && $rebuildMaxCapacity > $currentCapacity)
    <x-modal name="stadium-rebuild" maxWidth="xl">
        <form method="POST" action="{{ route('game.club.stadium.rebuild', $game->id) }}"
              x-data="{
                  capacity: {{ min($currentCapacity + 10000, $rebuildMaxCapacity) }},
                  financing: 'cash',
                  costCents() { return this.capacity * {{ $rebuildPerSeat }}; },
                  costLabel() { return '€ ' + (this.costCents() / 100_000_000).toFixed(1) + 'M'; }
              }"
              class="p-6 space-y-4">
            @csrf

            <h3 class="font-heading text-lg font-bold text-text-primary">{{ __('club.stadium.upgrades.modal_rebuild_title') }}</h3>
            <p class="text-sm text-text-muted">{{ __('club.stadium.upgrades.modal_rebuild_description') }}</p>

            <div>
                <label class="block text-[10px] text-text-muted uppercase tracking-widest mb-2">
                    {{ __('club.stadium.upgrades.target_capacity') }}
                    <span x-text="capacity.toLocaleString('es-ES')" class="font-heading text-base text-text-primary ml-2"></span>
                </label>
                <input type="range" name="capacity" min="{{ $currentCapacity + 1000 }}" max="{{ $rebuildMaxCapacity }}" step="1000"
                       x-model.number="capacity"
                       class="w-full">
                <div class="flex justify-between text-xs text-text-faint mt-1">
                    <span>{{ number_format($currentCapacity + 1000) }}</span>
                    <span>{{ number_format($rebuildMaxCapacity) }}</span>
                </div>
            </div>

            <div>
                <label class="block text-[10px] text-text-muted uppercase tracking-widest mb-2">{{ __('club.stadium.upgrades.financing') }}</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer"
                           :class="financing === 'cash' ? 'border-accent-blue bg-accent-blue/10' : 'border-border-strong bg-surface-700'">
                        <input type="radio" name="financing" value="cash" x-model="financing" class="text-accent-blue">
                        <span class="text-sm font-medium text-text-primary">{{ __('club.stadium.upgrades.financing_cash') }}</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer"
                           :class="financing === 'loan' ? 'border-accent-blue bg-accent-blue/10' : 'border-border-strong bg-surface-700'">
                        <input type="radio" name="financing" value="loan" x-model="financing" class="text-accent-blue">
                        <span class="text-sm font-medium text-text-primary">{{ __('club.stadium.upgrades.financing_loan') }}</span>
                    </label>
                </div>
                <div class="text-xs text-text-muted mt-2"
                     x-text="financing === 'loan'
                         ? '{{ __('club.stadium.upgrades.financing_loan_hint', ['cap' => Money::format($loanCapCents)]) }}'
                         : '{{ __('club.stadium.upgrades.financing_cash_hint') }}'"></div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-border-strong">
                <span class="text-sm text-text-muted">{{ __('club.stadium.upgrades.total_cost') }}</span>
                <span class="font-heading text-lg font-bold text-text-primary" x-text="costLabel()"></span>
            </div>

            <div class="rounded-lg bg-surface-700 border border-border-strong p-3 text-xs text-text-muted">
                {{ __('club.stadium.upgrades.rebuild_disruption_warning') }}
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" x-on:click="$dispatch('close')" class="px-4 py-2 text-sm text-text-muted hover:text-text-primary">
                    {{ __('app.cancel') }}
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-lg bg-accent-blue hover:bg-accent-blue/90 text-white">
                    {{ __('club.stadium.upgrades.commit_rebuild') }}
                </button>
            </div>
        </form>
    </x-modal>
    @endif
@endif
