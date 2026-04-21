@php
/** @var App\Models\Game $game */
/** @var array $summary */
/** @var array $career */

$currentLevel = $summary['current_level'];
$tierIndex = $summary['tier_index'];
$pointsInTier = $summary['points_in_tier'];
$tierSpan = $summary['tier_span'];

$tierProgressPercent = $tierSpan > 0 ? (int) round(min(100, ($pointsInTier / $tierSpan) * 100)) : 0;

$allTiers = \App\Models\ClubProfile::REPUTATION_TIERS;

$loyaltyPoints = $summary['loyalty_points'];
$qualitativeDistance = $summary['qualitative_distance'] ?? null;
$outcomeLadder = $summary['outcome_ladder'] ?? [];
$tierMaintenanceApplies = $summary['tier_maintenance_applies'] ?? false;
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
        <div x-data="{ helpOpen: false }">
            <x-club-section-nav :game="$game" active="reputation">
                <x-ghost-button color="slate" @click="helpOpen = !helpOpen" class="gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-text-secondary shrink-0">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
                    </svg>
                    <span class="hidden md:inline">{{ __('club.reputation.tiers_help_toggle') }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 transition-transform hidden md:block" :class="helpOpen ? 'rotate-180' : ''">
                        <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </x-ghost-button>
            </x-club-section-nav>

            <div x-show="helpOpen" x-transition x-cloak class="mt-3 bg-surface-800 border border-border-default rounded-xl p-4 text-sm">
                <p class="text-text-secondary mb-3">{{ __('club.reputation.ladder_help') }}</p>
                <ol class="space-y-1.5">
                    @foreach(array_reverse($allTiers) as $tier)
                        @php
                            $isCurrent = $tier === $currentLevel;
                        @endphp
                        <li class="flex items-baseline gap-2 {{ $isCurrent ? 'text-text-primary font-semibold' : 'text-text-body' }}">
                            <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $isCurrent ? 'bg-accent-blue' : 'bg-surface-600' }}"></span>
                            <span class="uppercase tracking-wider text-xs">{{ __('finances.reputation.' . $tier) }}</span>
                            <span class="text-text-secondary text-xs">— {{ __('club.reputation.tier_descriptors.' . $tier) }}</span>
                            @if($isCurrent)
                                <span class="text-[10px] font-semibold text-accent-blue uppercase tracking-widest">· {{ __('club.reputation.current') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        {{-- Status header --}}
        <div class="mt-6 mb-4 rounded-lg border border-border-default bg-surface-700/40 px-5 py-4">
            <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.reputation.current_tier') }}</div>
            <div class="font-heading text-2xl lg:text-3xl font-bold text-text-primary leading-tight">{{ __('finances.reputation.' . $currentLevel) }}</div>
            <div class="text-sm text-text-body mt-1">{{ __('club.reputation.tier_descriptors.' . $currentLevel) }}</div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- LEFT (2/3) — path to next tier (primary) --}}
            <div class="lg:col-span-2 space-y-6">
                <x-section-card :title="__('club.reputation.path_title')">
                    <div class="px-5 py-4">
                        @if(isset($allTiers[$tierIndex + 1]) && $qualitativeDistance !== null)
                            <p class="text-sm text-text-body mb-3 leading-relaxed">
                                {{ __('club.reputation.qualitative_distance.' . $qualitativeDistance, ['tier' => __('finances.reputation.' . $allTiers[$tierIndex + 1])]) }}
                            </p>
                        @endif

                        {{-- Compact outcome bar: each segment is one league-finish band. --}}
                        <div class="flex h-7 rounded-md overflow-hidden border border-border-default">
                            @foreach($outcomeLadder as $band)
                                @php
                                    $segmentBg = match ($band['impact_key']) {
                                        'major_leap' => 'bg-accent-green',
                                        'solid_step' => 'bg-accent-green/70',
                                        'small_step' => 'bg-accent-green/35',
                                        'stalls' => 'bg-surface-600',
                                        'setback' => 'bg-accent-red/70',
                                        default => 'bg-surface-600',
                                    };
                                    $segmentText = in_array($band['impact_key'], ['major_leap', 'solid_step', 'setback'], true)
                                        ? 'text-white'
                                        : 'text-text-primary';
                                @endphp
                                <div class="relative flex items-center justify-center text-[11px] font-heading font-bold {{ $segmentBg }} {{ $segmentText }} {{ $band['is_current'] ? 'ring-2 ring-accent-blue ring-inset z-10' : '' }}"
                                     style="flex: {{ $band['size'] }} 1 0;"
                                     title="{{ __('club.reputation.impact.' . $band['impact_key']) }}">
                                    {{ $band['position_range'] }}
                                </div>
                            @endforeach
                        </div>

                        {{-- Legend --}}
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 text-[11px] text-text-muted">
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-accent-green"></span>{{ __('club.reputation.legend.forward') }}</span>
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-surface-600"></span>{{ __('club.reputation.legend.flat') }}</span>
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-accent-red/70"></span>{{ __('club.reputation.legend.setback') }}</span>
                        </div>

                        <p class="text-xs text-text-muted mt-3 leading-relaxed">{{ __('club.reputation.path_also') }}</p>
                        @if($tierMaintenanceApplies)
                            <p class="text-xs text-text-muted mt-1 leading-relaxed">{{ __('club.reputation.maintenance_note') }}</p>
                        @endif
                    </div>
                </x-section-card>

                {{-- What reputation means for your club --}}
                <x-section-card :title="__('club.reputation.impact_title')">
                    <div class="px-5 py-4 space-y-4">
                        <div>
                            <p class="font-semibold text-text-body text-sm mb-1">{{ __('club.reputation.impact_signings_title') }}</p>
                            <p class="text-sm text-text-secondary leading-relaxed">{{ __('club.reputation.impact_signings_body') }}</p>
                        </div>
                        <div>
                            <p class="font-semibold text-text-body text-sm mb-1">{{ __('club.reputation.impact_retain_title') }}</p>
                            <p class="text-sm text-text-secondary leading-relaxed">{{ __('club.reputation.impact_retain_body') }}</p>
                        </div>
                        <div>
                            <p class="font-semibold text-text-body text-sm mb-1">{{ __('club.reputation.impact_economy_title') }}</p>
                            <p class="text-sm text-text-secondary leading-relaxed">{{ __('club.reputation.impact_economy_body') }}</p>
                        </div>
                    </div>
                </x-section-card>
            </div>

            {{-- RIGHT (1/3) — career so far + fan base --}}
            <div class="space-y-6">
                {{-- Career so far --}}
                <x-section-card :title="__('club.reputation.career.title')">
                    <div class="px-5 py-4 space-y-2.5">
                        <div class="flex items-baseline justify-between">
                            <span class="text-xs text-text-muted">{{ __('club.reputation.career.seasons_managed') }}</span>
                            <span class="font-heading text-base font-bold text-text-primary">{{ $career['seasons_managed'] }}</span>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <span class="text-xs text-text-muted">{{ __('club.reputation.career.matches_managed') }}</span>
                            <span class="font-heading text-base font-bold text-text-primary">{{ number_format($career['matches_played']) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <span class="text-xs text-text-muted">{{ __('club.reputation.career.starting_tier') }}</span>
                            <span class="font-heading text-xs font-bold text-text-primary uppercase tracking-wider">{{ __('finances.reputation.' . $career['starting_tier']) }}</span>
                        </div>
                        @if($career['trophies'] > 0)
                            <div class="flex items-baseline justify-between">
                                <span class="text-xs text-text-muted">{{ __('club.reputation.career.trophies') }}</span>
                                <span class="font-heading text-base font-bold text-text-primary">{{ $career['trophies'] }}</span>
                            </div>
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
                        <div class="w-full h-2 bg-surface-600 rounded-full overflow-hidden mt-1.5">
                            <div class="h-full rounded-full bg-accent-blue" style="width: {{ max(0, min(100, $loyaltyPoints)) }}%"></div>
                        </div>
                        <p class="text-xs text-text-muted mt-4 leading-relaxed">{{ __('club.stadium.fan_base_help') }}</p>
                    </div>
                </x-section-card>

            </div>
        </div>
    </div>
</x-app-layout>
