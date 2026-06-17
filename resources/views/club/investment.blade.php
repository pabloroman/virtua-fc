@php
/** @var App\Models\Game $game */
/** @var App\Models\GameFinances $finances */
/** @var App\Models\GameInvestment|null $investment */
/** @var int $availableSurplus */
/** @var array $tiers */
/** @var array $tierThresholds */
/** @var int $minimumTier */
/** @var bool $isPreSeason */
/** @var int $availableBudget */
/** @var array $areaData */
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match ?? null"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">

        {{-- Club hub title + subnav --}}
        <div class="mt-6 mb-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('club.hub_title') }}</h2>
        </div>
        <x-club-section-nav :game="$game" active="investment" />

        <div class="mt-6"></div>

        <x-flash-message type="error" :message="session('error')" class="mb-4" />
        <x-flash-message type="success" :message="session('success')" class="mb-4" />

        {{-- State banner: explains how free editing is right now --}}
        @if($isPreSeason)
        <div class="mb-6 border-l-4 border-l-accent-green bg-accent-green/10 rounded-r-lg px-4 py-3">
            <div class="font-heading text-xs font-semibold text-accent-green uppercase tracking-widest mb-0.5">{{ __('finances.investment_state_preseason') }}</div>
            <p class="text-sm text-text-secondary">{{ __('finances.investment_preseason_hint') }}</p>
        </div>
        @else
        <div class="mb-6 border-l-4 border-l-accent-blue bg-accent-blue/10 rounded-r-lg px-4 py-3">
            <div class="font-heading text-xs font-semibold text-accent-blue uppercase tracking-widest mb-0.5">{{ __('finances.investment_state_locked') }}</div>
            <p class="text-sm text-text-secondary">{{ __('finances.investment_locked_hint') }}</p>
        </div>
        @endif

        @if($isPreSeason)
            {{-- Pre-season: free two-way editing, live provisional transfer budget --}}
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-2 mb-5">
                <div>
                    <h3 class="font-heading text-sm font-semibold text-text-secondary uppercase tracking-widest">{{ __('finances.season_budget', ['season' => $game->formatted_season]) }}</h3>
                    <p class="text-sm text-text-muted mt-0.5">{{ __('game.allocate_budget_hint') }}</p>
                </div>
                <div class="md:text-right">
                    <div class="font-heading text-2xl font-bold text-text-primary">{{ \App\Support\Money::format($availableSurplus) }}</div>
                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('game.available') }}</div>
                </div>
            </div>

            <x-budget-allocation
                :available-surplus="$availableSurplus"
                :tiers="$tiers"
                :tier-thresholds="$tierThresholds"
                :minimum-tier="$minimumTier"
                :form-action="route('game.club.investment.save', $game->id)"
                :submit-label="__('finances.save_plan')"
            />
        @elseif($investment)
            {{-- Locked: read-only tiers, upgrade any time (full cost), reductions staged for next season --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
                <x-summary-card :label="__('finances.transfer_budget')" :value="$investment->formatted_transfer_budget" value-class="text-accent-blue" />
                <x-summary-card :label="__('finances.total_infrastructure')" :value="$investment->formatted_total_infrastructure" />
                <x-summary-card :label="__('finances.available_for_upgrades')" :value="\App\Support\Money::format(max(0, $availableBudget))" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($areaData as $area)
                <div class="bg-surface-800 border border-border-default rounded-xl p-4" x-data="{ showUpgrade: false, showReduce: false }">
                    {{-- Header --}}
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-heading text-sm font-semibold text-text-primary">{{ __('finances.' . $area['key']) }}</h4>
                        <span class="text-xs text-text-muted">{{ $area['amount'] }}</span>
                    </div>

                    {{-- Tier dots + actions --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            @for($i = 1; $i <= 4; $i++)
                                <span class="w-2.5 h-2.5 rounded-full {{ $i <= $area['tier'] ? 'bg-accent-green' : 'bg-surface-600' }}"></span>
                            @endfor
                            <span class="text-[10px] text-text-muted ml-1">{{ __('finances.tier', ['level' => $area['tier']]) }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            @if($area['tier'] < 4)
                                <x-ghost-button color="green" size="xs" x-show="!showUpgrade" @click="showUpgrade = true; showReduce = false" class="gap-1 font-semibold px-2.5">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" /></svg>
                                    {{ __('finances.upgrade') }}
                                </x-ghost-button>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full bg-accent-green/10 text-accent-green uppercase tracking-wider">MAX</span>
                            @endif
                            @if(!empty($area['downgrades']))
                                <x-ghost-button color="slate" size="xs" x-show="!showReduce" @click="showReduce = true; showUpgrade = false" class="font-semibold px-2.5">
                                    {{ __('finances.reduce') }}
                                </x-ghost-button>
                            @endif
                        </div>
                    </div>

                    {{-- Current tier description --}}
                    <div class="text-[10px] text-text-muted mt-1">{{ __('finances.' . $area['key'] . '_tier_' . $area['tier']) }}</div>

                    {{-- Staged downgrade indicator --}}
                    @if($area['staged'] !== null)
                    <div class="mt-3 flex items-center justify-between gap-2 p-2 rounded-lg bg-accent-gold/10 border border-accent-gold/20">
                        <span class="text-xs text-accent-gold font-medium">{{ __('finances.staged_next_season', ['tier' => $area['staged']]) }}</span>
                        <form method="POST" action="{{ route('game.club.investment.stage-downgrade', $game->id) }}">
                            @csrf
                            <input type="hidden" name="area" value="{{ $area['key'] }}">
                            <input type="hidden" name="target_tier" value="">
                            <x-ghost-button color="slate" size="xs" type="submit" class="font-semibold px-2.5">{{ __('finances.staged_cancel') }}</x-ghost-button>
                        </form>
                    </div>
                    @endif

                    {{-- Upgrade options (full cost, applies immediately) --}}
                    @if($area['tier'] < 4)
                    <div x-show="showUpgrade" x-collapse x-cloak class="mt-3 pt-3 border-t border-border-default space-y-2">
                        @foreach($area['upgrades'] as $option)
                        <form method="POST" action="{{ route('game.infrastructure.upgrade', $game->id) }}" class="flex items-center justify-between gap-2 p-2 rounded-lg {{ $option['affordable'] ? 'bg-surface-700/50' : '' }}">
                            @csrf
                            <input type="hidden" name="area" value="{{ $area['key'] }}">
                            <input type="hidden" name="target_tier" value="{{ $option['tier'] }}">
                            <div class="min-w-0 flex items-center gap-2">
                                <div class="flex items-center gap-1">
                                    @for($dot = 1; $dot <= 4; $dot++)
                                        <span class="w-1.5 h-1.5 rounded-full {{ $dot <= $option['tier'] ? 'bg-accent-green' : 'bg-surface-600' }}"></span>
                                    @endfor
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-text-body">{{ __('finances.tier', ['level' => $option['tier']]) }}</div>
                                    <div class="text-[10px] text-text-muted truncate">{{ $option['cost'] }}</div>
                                </div>
                            </div>
                            <x-primary-button size="xs" class="shrink-0" :disabled="!$option['affordable']">
                                {{ __('finances.upgrade_confirm') }}
                            </x-primary-button>
                        </form>
                        @endforeach
                        @if($availableBudget <= 0)
                        <p class="text-[10px] text-accent-gold px-2">{{ __('finances.upgrade_insufficient_budget') }}</p>
                        @endif
                    </div>
                    @endif

                    {{-- Reduce options (staged for next season — no clawback) --}}
                    @if(!empty($area['downgrades']))
                    <div x-show="showReduce" x-collapse x-cloak class="mt-3 pt-3 border-t border-border-default space-y-2">
                        <p class="text-[10px] text-text-muted px-1">{{ __('finances.reduce_hint') }}</p>
                        @foreach($area['downgrades'] as $downTier)
                        <form method="POST" action="{{ route('game.club.investment.stage-downgrade', $game->id) }}" class="flex items-center justify-between gap-2 p-2 rounded-lg bg-surface-700/50">
                            @csrf
                            <input type="hidden" name="area" value="{{ $area['key'] }}">
                            <input type="hidden" name="target_tier" value="{{ $downTier }}">
                            <div class="min-w-0 flex items-center gap-2">
                                <div class="flex items-center gap-1">
                                    @for($dot = 1; $dot <= 4; $dot++)
                                        <span class="w-1.5 h-1.5 rounded-full {{ $dot <= $downTier ? 'bg-accent-gold' : 'bg-surface-600' }}"></span>
                                    @endfor
                                </div>
                                <div class="text-xs font-medium text-text-body">{{ __('finances.tier', ['level' => $downTier]) }}</div>
                            </div>
                            <x-ghost-button color="amber" size="xs" type="submit" class="shrink-0 font-semibold">
                                {{ __('finances.reduce_stage') }}
                            </x-ghost-button>
                        </form>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        @endif

    </div>
</x-app-layout>
