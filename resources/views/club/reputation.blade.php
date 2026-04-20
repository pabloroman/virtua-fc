@php
/** @var App\Models\Game $game */
/** @var array $summary */

$currentLevel = $summary['current_level'];
$currentPoints = $summary['current_points'];
$baseLevel = $summary['base_level'];
$tierFloor = $summary['tier_floor'];
$tierIndex = $summary['tier_index'];
$baseTierIndex = $summary['base_tier_index'];
$pointsInTier = $summary['points_in_tier'];
$tierSpan = $summary['tier_span'];
$pointsToNextTier = $summary['points_to_next_tier'];
$thresholds = $summary['tier_thresholds'];
$direction = $summary['direction'];
$detail = $summary['direction_detail'];

$directionConfig = match ($direction) {
    'rising' => ['icon' => '&#9650;', 'color' => 'text-accent-green', 'label' => __('season.reputation_rising')],
    'declining' => ['icon' => '&#9660;', 'color' => 'text-accent-red', 'label' => __('season.reputation_declining')],
    default => ['icon' => '&#9654;', 'color' => 'text-text-secondary', 'label' => __('season.reputation_stable')],
};

$tierProgressPercent = $tierSpan > 0 ? (int) round(min(100, ($pointsInTier / $tierSpan) * 100)) : 0;

$allTiers = \App\Models\ClubProfile::REPUTATION_TIERS;

$loyaltyPoints = $summary['loyalty_points'];
$baseLoyalty = $summary['base_loyalty'];
$loyaltyDirection = $summary['loyalty_direction'];

$loyaltyDirectionConfig = match ($loyaltyDirection) {
    'rising' => ['icon' => '&#9650;', 'color' => 'text-accent-green', 'label' => __('club.stadium.loyalty_rising')],
    'declining' => ['icon' => '&#9660;', 'color' => 'text-accent-red', 'label' => __('club.stadium.loyalty_declining')],
    default => ['icon' => '&#9654;', 'color' => 'text-text-secondary', 'label' => __('club.stadium.loyalty_stable')],
};
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">

        {{-- Club hub title + subnav --}}
        <div class="mt-6 mb-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('club.hub_title') }}</h2>
        </div>
        <x-club-section-nav :game="$game" active="reputation" />

        {{-- KPI strip --}}
        <div class="flex flex-wrap gap-3 mt-6 mb-6">
            <x-summary-card :label="__('club.reputation.current_tier')">
                <div class="font-heading text-xl font-bold text-text-primary mt-0.5">{{ __('finances.reputation.' . $currentLevel) }}</div>
            </x-summary-card>
            <x-summary-card
                :label="__('club.reputation.points')"
                :value="number_format($currentPoints)"
            />
            <x-summary-card :label="__('club.reputation.trend')">
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="font-heading text-lg font-bold {{ $directionConfig['color'] }}">{!! $directionConfig['icon'] !!}</span>
                    <span class="text-xs font-semibold {{ $directionConfig['color'] }} uppercase tracking-wider">{{ $directionConfig['label'] }}</span>
                </div>
            </x-summary-card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- LEFT (2/3) — tier milestones ladder --}}
            <div class="lg:col-span-2 space-y-6">
                <x-section-card :title="__('club.reputation.tiers')">
                    <div class="px-5 py-4">
                        <div class="space-y-3">
                            @foreach(array_reverse($allTiers) as $tier)
                                @php
                                    $threshold = $thresholds[$tier] ?? 0;
                                    $tierIdx = \App\Models\ClubProfile::getReputationTierIndex($tier);
                                    $isCurrent = $tier === $currentLevel;
                                    $isBase = $tier === $baseLevel;
                                    $isFloor = $tier === $tierFloor;
                                    $isReached = $currentPoints >= $threshold;
                                @endphp
                                <div class="flex items-center gap-4 rounded-lg px-3 py-3 border {{ $isCurrent ? 'bg-accent-blue/10 border-accent-blue/40' : 'bg-surface-700/40 border-border-default' }}">
                                    <div class="w-2 h-10 rounded-full {{ $isCurrent ? 'bg-accent-blue' : ($isReached ? 'bg-accent-green/50' : 'bg-surface-600') }}"></div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-heading text-sm font-bold {{ $isCurrent ? 'text-text-primary' : 'text-text-body' }} uppercase tracking-wider">{{ __('finances.reputation.' . $tier) }}</span>
                                            @if($isCurrent)
                                                <span class="text-[10px] font-semibold bg-accent-blue/20 text-accent-blue px-1.5 py-0.5 rounded-sm uppercase tracking-widest">{{ __('club.reputation.current') }}</span>
                                            @endif
                                            @if($isBase)
                                                <span class="text-[10px] font-semibold bg-surface-600 text-text-body px-1.5 py-0.5 rounded-sm uppercase tracking-widest">{{ __('club.reputation.anchor') }}</span>
                                            @endif
                                            @if($isFloor && !$isBase)
                                                <span class="text-[10px] font-semibold bg-surface-600 text-text-muted px-1.5 py-0.5 rounded-sm uppercase tracking-widest">{{ __('club.reputation.floor') }}</span>
                                            @endif
                                        </div>
                                        <div class="text-[10px] text-text-muted uppercase tracking-widest mt-0.5">{{ __('club.reputation.threshold') }}: {{ number_format($threshold) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-text-muted mt-4 leading-relaxed">{{ __('club.reputation.ladder_help') }}</p>
                    </div>
                </x-section-card>
            </div>

            {{-- RIGHT (1/3) — tier progress + season projection --}}
            <div class="space-y-6">
                {{-- Progress within current tier --}}
                <x-section-card :title="__('club.reputation.progress')">
                    <div class="px-5 py-4">
                        <div class="flex items-baseline justify-between">
                            <span class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('finances.reputation.' . $currentLevel) }}</span>
                            <span class="font-heading text-lg font-bold text-text-primary">{{ number_format($currentPoints) }}</span>
                        </div>
                        <div class="w-full h-2 bg-surface-600 rounded-full overflow-hidden mt-1.5">
                            <div class="h-full rounded-full bg-accent-blue" style="width: {{ $tierProgressPercent }}%"></div>
                        </div>
                        @if($pointsToNextTier !== null && isset($allTiers[$tierIndex + 1]))
                            <p class="text-xs text-text-muted mt-2">
                                {{ __('club.reputation.points_to_next', [
                                    'points' => number_format($pointsToNextTier),
                                    'tier' => __('finances.reputation.' . $allTiers[$tierIndex + 1]),
                                ]) }}
                            </p>
                        @else
                            <p class="text-xs text-text-muted mt-2">{{ __('club.reputation.at_top_tier') }}</p>
                        @endif
                    </div>
                </x-section-card>

                {{-- Fan base panel --}}
                <x-section-card :title="__('club.stadium.fan_base')">
                    <div class="px-5 py-4">
                        <div class="flex items-baseline justify-between">
                            <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.current_loyalty') }}</div>
                            <span class="font-heading text-lg font-bold text-text-primary">{{ $loyaltyPoints }}<span class="text-xs font-normal text-text-muted"> / 100</span></span>
                        </div>
                        <div class="w-full h-2 bg-surface-600 rounded-full overflow-hidden mt-1.5 relative">
                            <div class="h-full rounded-full bg-accent-blue" style="width: {{ max(0, min(100, $loyaltyPoints)) }}%"></div>
                            @if($baseLoyalty > 0)
                                <div class="absolute top-0 bottom-0 w-px bg-text-primary/60" style="left: {{ max(0, min(100, $baseLoyalty)) }}%;"></div>
                            @endif
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <span class="text-[10px] text-text-muted">{{ __('club.stadium.anchor') }}: {{ $baseLoyalty }}</span>
                            <span class="text-[10px] font-semibold {{ $loyaltyDirectionConfig['color'] }}">{!! $loyaltyDirectionConfig['icon'] !!} {{ $loyaltyDirectionConfig['label'] }}</span>
                        </div>
                        <p class="text-xs text-text-muted mt-4 leading-relaxed">{{ __('club.stadium.fan_base_help') }}</p>
                    </div>
                </x-section-card>

                {{-- Season-end projection --}}
                <x-section-card :title="__('club.reputation.season_projection')">
                    <div class="px-5 py-4">
                        @if($detail['position'] === null)
                            <p class="text-sm text-text-muted">{{ __('club.reputation.no_standing_yet') }}</p>
                        @else
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-text-muted">{{ __('club.reputation.current_position') }}</span>
                                    <span class="font-heading font-bold text-text-primary">{{ $detail['position'] }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-text-muted">{{ __('club.reputation.position_points') }}</span>
                                    <span class="font-heading font-bold {{ $detail['points_delta'] > 0 ? 'text-accent-green' : ($detail['points_delta'] < 0 ? 'text-accent-red' : 'text-text-body') }}">{{ ($detail['points_delta'] > 0 ? '+' : '') . $detail['points_delta'] }}</span>
                                </div>
                                @if($detail['gravity'] > 0)
                                    <div class="flex items-center justify-between">
                                        <span class="text-text-muted">{{ __('club.reputation.gravity') }}</span>
                                        <span class="font-heading font-bold text-accent-red">−{{ $detail['gravity'] }}</span>
                                    </div>
                                @endif
                                <div class="flex items-center justify-between pt-2 border-t border-border-default">
                                    <span class="text-text-muted">{{ __('club.reputation.net_change') }}</span>
                                    <span class="font-heading font-bold {{ $detail['net'] > 0 ? 'text-accent-green' : ($detail['net'] < 0 ? 'text-accent-red' : 'text-text-body') }}">{{ ($detail['net'] > 0 ? '+' : '') . $detail['net'] }}</span>
                                </div>
                            </div>
                            <p class="text-xs text-text-muted mt-4 leading-relaxed">{{ __('club.reputation.projection_help') }}</p>
                        @endif
                    </div>
                </x-section-card>
            </div>
        </div>
    </div>
</x-app-layout>
