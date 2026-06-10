@php
/** @var App\Models\Game $game */
/** @var array $summary */
/** @var array $career */
/** @var array $trophyCabinet */
/** @var array $history */

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
        <x-help-disclosure>
            <x-slot name="trigger">
                <x-club-section-nav :game="$game" active="reputation">
                    <x-help-toggle :label="__('club.reputation.tiers_help_toggle')" />
                </x-club-section-nav>
            </x-slot>

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
        </x-help-disclosure>

        {{-- Status header --}}
        <div class="mt-6 mb-4 rounded-lg border border-border-default bg-surface-700/40 px-5 py-4 flex items-center gap-4">
            <x-team-crest :team="$game->team" class="w-14 h-14 lg:w-16 lg:h-16 shrink-0" />
            <div class="min-w-0">
                <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.reputation.current_tier') }}</div>
                <div class="font-heading text-2xl lg:text-3xl font-bold text-text-primary leading-tight">{{ __('finances.reputation.' . $currentLevel) }}</div>
                <div class="text-sm text-text-body mt-1">{{ __('club.reputation.tier_descriptors.' . $currentLevel) }}</div>
            </div>
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

                {{-- Performance history: final league position per season, with tier bands
                     so promotions and relegations are visually distinct. --}}
                <x-section-card :title="__('club.reputation.history.title')">
                    <div class="px-5 py-4">
                        @if (empty($history['seasons']))
                            <p class="text-sm text-text-muted leading-relaxed">{{ __('club.reputation.history.empty') }}</p>
                        @else
                            <x-performance-history-chart
                                :seasons="$history['seasons']"
                                :tiers-present="$history['tiers_present']"
                            />

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 text-[11px] text-text-muted">
                                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-accent-blue"></span>{{ __('club.reputation.history.legend.same_tier') }}</span>
                                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-accent-green"></span>{{ __('club.reputation.history.promoted') }}</span>
                                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-accent-red"></span>{{ __('club.reputation.history.relegated') }}</span>
                            </div>
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

                {{-- Trophy cabinet: one row per competition the user has won,
                     grouped and ordered by prestige tier. --}}
                <x-section-card :title="__('club.reputation.trophy_cabinet.title')">
                    @if(empty($trophyCabinet))
                        <div class="px-5 py-4">
                            <p class="text-sm text-text-muted leading-relaxed">{{ __('club.reputation.trophy_cabinet.empty') }}</p>
                        </div>
                    @else
                        <div class="divide-y divide-border-default">
                            @foreach($trophyCabinet as $entry)
                                @php
                                    $typeConfig = match($entry['trophy_type']) {
                                        'league' => ['color' => 'text-accent-gold', 'bg' => 'bg-accent-gold/15'],
                                        'cup' => ['color' => 'text-accent-blue', 'bg' => 'bg-accent-blue/15'],
                                        'european' => ['color' => 'text-accent-green', 'bg' => 'bg-accent-green/15'],
                                        'supercup' => ['color' => 'text-accent-orange', 'bg' => 'bg-accent-orange/15'],
                                        default => ['color' => 'text-text-muted', 'bg' => 'bg-surface-700'],
                                    };
                                @endphp
                                <div class="px-5 py-3 flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg {{ $typeConfig['bg'] }} flex items-center justify-center shrink-0 mt-0.5">
                                        <svg class="w-4 h-4 {{ $typeConfig['color'] }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.166 2.621v.858c-1.035.148-2.059.33-3.071.543a.75.75 0 0 0-.584.859 6.753 6.753 0 0 0 6.138 5.6 6.73 6.73 0 0 0 2.743 1.346A6.707 6.707 0 0 1 9.279 15H8.54c-1.036 0-1.875.84-1.875 1.875V19.5h-.75a.75.75 0 0 0 0 1.5h12.17a.75.75 0 0 0 0-1.5h-.75v-2.625c0-1.036-.84-1.875-1.875-1.875h-.739a6.707 6.707 0 0 1-1.112-3.173 6.73 6.73 0 0 0 2.743-1.347 6.753 6.753 0 0 0 6.139-5.6.75.75 0 0 0-.585-.858 47.077 47.077 0 0 0-3.07-.543V2.62a.75.75 0 0 0-.658-.744 49.22 49.22 0 0 0-6.093-.377c-2.063 0-4.096.128-6.093.377a.75.75 0 0 0-.657.744Zm0 2.629c0 3.246 2.632 5.88 5.834 5.88 3.203 0 5.834-2.634 5.834-5.88V3.357a47.62 47.62 0 0 0-5.834-.357c-1.993 0-3.948.119-5.834.357v1.893Z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline justify-between gap-2">
                                            <p class="text-sm font-medium text-text-primary truncate">{{ __($entry['competition_name']) }}</p>
                                            <span class="text-xs font-heading font-bold text-text-muted shrink-0">×{{ $entry['count'] }}</span>
                                        </div>
                                        <p class="text-xs text-text-muted leading-relaxed">{{ implode(', ', $entry['seasons']) }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-section-card>

                {{-- Fan base panel --}}
                <x-fan-support-card :loyalty="$loyaltyPoints" />

            </div>
        </div>
    </div>
</x-app-layout>
