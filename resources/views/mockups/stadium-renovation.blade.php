{{--
    MOCKUP: Stadium & facilities renovation hub.
    Standalone preview at /mockups/stadium-renovation. Static data only —
    no controller, no services. Iterates on the cramped infrastructure
    block buried in club/finances.blade.php by giving renovations their
    own page with a visual "campus" of upgradeable buildings.
--}}
@php
    use App\Support\Money;

    // --- Mock state (cents) ---------------------------------------------------
    $renovationBudget = 1_850_000_00;    // €18.5M available for renovations
    $currentlyInvested = 4_200_000_00;   // €42M already sunk into infrastructure
    $totalProjects = 5;

    // --- Buildings ------------------------------------------------------------
    // status: 'idle' | 'in_progress' | 'max'
    $buildings = [
        [
            'key' => 'stadium',
            'name' => __('mockups.renovation.buildings.stadium.name'),
            'tagline' => __('mockups.renovation.buildings.stadium.tagline'),
            'icon' => 'stadium',
            'tier' => 2,
            'current_effect' => __('mockups.renovation.buildings.stadium.tier.2'),
            'next_effect' => __('mockups.renovation.buildings.stadium.tier.3'),
            'next_delta' => '+8.500 ' . __('mockups.renovation.delta.seats'),
            'cost' => 9_500_000_00,
            'duration_weeks' => 16,
            'status' => 'idle',
        ],
        [
            'key' => 'facilities',
            'name' => __('mockups.renovation.buildings.facilities.name'),
            'tagline' => __('mockups.renovation.buildings.facilities.tagline'),
            'icon' => 'facilities',
            'tier' => 3,
            'current_effect' => __('mockups.renovation.buildings.facilities.tier.3'),
            'next_effect' => __('mockups.renovation.buildings.facilities.tier.4'),
            'next_delta' => '×1.35 → ×1.60 ' . __('mockups.renovation.delta.matchday'),
            'cost' => 15_000_000_00,
            'duration_weeks' => 12,
            'status' => 'idle',
        ],
        [
            'key' => 'youth_academy',
            'name' => __('mockups.renovation.buildings.youth_academy.name'),
            'tagline' => __('mockups.renovation.buildings.youth_academy.tagline'),
            'icon' => 'academy',
            'tier' => 2,
            'current_effect' => __('mockups.renovation.buildings.youth_academy.tier.2'),
            'next_effect' => __('mockups.renovation.buildings.youth_academy.tier.3'),
            'next_delta' => __('mockups.renovation.delta.youth'),
            'cost' => 6_000_000_00,
            'duration_weeks' => 10,
            'status' => 'in_progress',
            'progress_pct' => 62,
            'weeks_remaining' => 4,
            'target_tier' => 3,
        ],
        [
            'key' => 'medical',
            'name' => __('mockups.renovation.buildings.medical.name'),
            'tagline' => __('mockups.renovation.buildings.medical.tagline'),
            'icon' => 'medical',
            'tier' => 3,
            'current_effect' => __('mockups.renovation.buildings.medical.tier.3'),
            'next_effect' => __('mockups.renovation.buildings.medical.tier.4'),
            'next_delta' => __('mockups.renovation.delta.medical'),
            'cost' => 5_000_000_00,
            'duration_weeks' => 8,
            'status' => 'idle',
        ],
        [
            'key' => 'scouting',
            'name' => __('mockups.renovation.buildings.scouting.name'),
            'tagline' => __('mockups.renovation.buildings.scouting.tagline'),
            'icon' => 'scouting',
            'tier' => 4,
            'current_effect' => __('mockups.renovation.buildings.scouting.tier.4'),
            'next_effect' => null,
            'next_delta' => null,
            'cost' => null,
            'duration_weeks' => null,
            'status' => 'max',
        ],
    ];

    $activeProject = collect($buildings)->firstWhere('status', 'in_progress');

    // Helper renders a small inline SVG for each building.
    $icon = function (string $key): string {
        $paths = [
            'stadium' => '<path d="M3 14c0-2.8 4-5 9-5s9 2.2 9 5v3H3v-3z"/><path d="M5 17v3M19 17v3M9 9V5l3-2 3 2v4"/><path d="M3 14h18"/>',
            'facilities' => '<path d="M4 21V8l8-4 8 4v13"/><path d="M9 21v-6h6v6"/><path d="M4 13h16"/>',
            'academy' => '<path d="M3 10l9-5 9 5-9 5-9-5z"/><path d="M7 12v4c0 1.5 2.5 3 5 3s5-1.5 5-3v-4"/><path d="M21 10v4"/>',
            'medical' => '<rect x="4" y="6" width="16" height="14" rx="2"/><path d="M12 10v6M9 13h6"/><path d="M9 3h6v3H9z"/>',
            'scouting' => '<circle cx="11" cy="11" r="6"/><path d="M11 8v3l2 1"/><path d="M15.5 15.5 20 20"/>',
        ];
        return $paths[$key] ?? '';
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0B1120">
    <title>{{ __('mockups.renovation.page_title') }} — VirtuaFC</title>

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <script>(function(){var t=localStorage.getItem('virtua-theme');if(t==='light'){document.documentElement.classList.add('light');document.querySelector('meta[name=theme-color]')?.setAttribute('content','#ffffff');}})()</script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-surface-900 text-text-primary min-h-screen">

    {{-- Slim mockup top bar --}}
    <header class="border-b border-border-default bg-surface-900/95 backdrop-blur-md sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <x-application-logo class="w-7 h-7 fill-current text-text-secondary shrink-0" />
                <div class="min-w-0">
                    <div class="text-[10px] text-text-muted uppercase tracking-widest leading-none">{{ __('mockups.renovation.breadcrumb') }}</div>
                    <div class="text-sm font-semibold text-text-primary truncate">{{ __('mockups.renovation.page_title') }}</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="hidden sm:inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-accent-gold/10 text-accent-gold text-[10px] font-bold uppercase tracking-widest">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" /></svg>
                    {{ __('mockups.renovation.mockup_badge') }}
                </span>
                <x-theme-toggle />
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 pb-12">

        {{-- Hub title + subnav, mirroring the real club hub --}}
        <div class="mt-6 mb-4 flex items-end justify-between gap-4">
            <div>
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('club.hub_title') }}</h2>
                <p class="text-xs text-text-muted mt-1 max-w-xl">{{ __('mockups.renovation.intro') }}</p>
            </div>
        </div>

        {{-- Sub-tabs (visual: Stadium is active, matches existing club section nav) --}}
        <div>
            <div class="hidden md:flex items-end border-b border-border-strong">
                <div class="flex">
                    <span class="px-4 py-2.5 text-sm font-medium border-b-2 border-transparent text-text-muted">{{ __('club.nav.finances') }}</span>
                    <span class="px-4 py-2.5 text-sm font-medium border-b-2 border-accent-blue text-text-primary">{{ __('club.nav.stadium') }}</span>
                    <span class="px-4 py-2.5 text-sm font-medium border-b-2 border-transparent text-text-muted">{{ __('club.nav.reputation') }}</span>
                </div>
            </div>
        </div>

        {{-- Budget summary strip --}}
        <div class="flex flex-wrap gap-3 mt-6">
            <x-summary-card :label="__('mockups.renovation.summary.budget')" :value="Money::format($renovationBudget)" value-class="text-accent-blue" />
            <x-summary-card :label="__('mockups.renovation.summary.invested')" :value="Money::format($currentlyInvested)" />
            <x-summary-card :label="__('mockups.renovation.summary.projects')" :value="$totalProjects" />
            <x-summary-card :label="__('mockups.renovation.summary.in_progress')" value-class="text-accent-gold">
                <div class="flex items-center gap-2 mt-1">
                    <span class="font-heading text-xl font-bold text-accent-gold">1</span>
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent-gold opacity-60"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-accent-gold"></span>
                    </span>
                </div>
            </x-summary-card>
        </div>

        {{-- Active-project banner (only when something is under construction) --}}
        @if($activeProject)
            <div class="mt-6 rounded-xl border border-accent-gold/40 bg-accent-gold/10 overflow-hidden">
                <div class="px-5 py-4 flex flex-col md:flex-row md:items-center gap-4">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="shrink-0 w-10 h-10 rounded-lg bg-accent-gold/20 text-accent-gold flex items-center justify-center">
                            <svg class="w-5 h-5 animate-spin-slow" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] text-accent-gold uppercase tracking-widest font-semibold">{{ __('mockups.renovation.active.eyebrow') }}</div>
                            <div class="font-heading text-base font-bold text-text-primary truncate">
                                {{ $activeProject['name'] }} · {{ __('mockups.renovation.tier_to', ['from' => $activeProject['tier'], 'to' => $activeProject['target_tier']]) }}
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0 md:max-w-xs">
                        <div class="flex items-baseline justify-between text-[11px] mb-1.5">
                            <span class="text-text-muted">{{ __('mockups.renovation.active.progress') }}</span>
                            <span class="text-text-body font-semibold tabular-nums">{{ $activeProject['progress_pct'] }}%</span>
                        </div>
                        <div class="h-2 bg-surface-900/60 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-accent-gold transition-[width] duration-500" style="width: {{ $activeProject['progress_pct'] }}%"></div>
                        </div>
                        <div class="text-[11px] text-text-muted mt-1.5">
                            {{ __('mockups.renovation.active.eta', ['weeks' => $activeProject['weeks_remaining']]) }}
                        </div>
                    </div>
                    <div class="shrink-0">
                        <x-ghost-button color="slate" size="xs" class="gap-1">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            {{ __('mockups.renovation.active.cancel') }}
                        </x-ghost-button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Buildings grid --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($buildings as $b)
                @php
                    $isInProgress = $b['status'] === 'in_progress';
                    $isMax = $b['status'] === 'max';
                    $canAfford = $b['cost'] !== null && $b['cost'] <= $renovationBudget;
                @endphp
                <div x-data="{ open: false }"
                     class="rounded-xl border border-border-default bg-surface-800 overflow-hidden flex flex-col transition-colors {{ $isInProgress ? 'ring-1 ring-accent-gold/40' : '' }}">

                    {{-- Card header --}}
                    <div class="px-5 py-4 flex items-start gap-3 border-b border-border-default">
                        <div class="shrink-0 w-12 h-12 rounded-lg bg-surface-700 border border-border-default flex items-center justify-center text-text-secondary">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                {!! $icon($b['icon']) !!}
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-heading text-base font-bold text-text-primary leading-tight">{{ $b['name'] }}</h3>
                                @if($isMax)
                                    <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-accent-green/15 text-accent-green uppercase tracking-wider">{{ __('mockups.renovation.badge.max') }}</span>
                                @elseif($isInProgress)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-accent-gold/15 text-accent-gold uppercase tracking-wider">
                                        <span class="w-1.5 h-1.5 rounded-full bg-accent-gold animate-pulse"></span>
                                        {{ __('mockups.renovation.badge.building') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-[11px] text-text-muted mt-0.5">{{ $b['tagline'] }}</p>
                        </div>
                    </div>

                    {{-- Tier track --}}
                    <div class="px-5 pt-4">
                        <div class="flex items-center gap-2">
                            @for($t = 1; $t <= 4; $t++)
                                @php
                                    $filled = $t <= $b['tier'];
                                    $isTarget = $isInProgress && isset($b['target_tier']) && $t === $b['target_tier'];
                                    $isNext = !$isInProgress && !$isMax && $t === $b['tier'] + 1;
                                @endphp
                                <div class="flex-1 flex flex-col items-center gap-1">
                                    <div class="relative w-full flex items-center">
                                        @if($t > 1)
                                            <div class="absolute left-0 right-0 -translate-x-1/2 top-1/2 -translate-y-1/2 h-0.5 {{ $t <= $b['tier'] ? 'bg-accent-green' : 'bg-surface-600' }}"></div>
                                        @endif
                                        <div class="relative mx-auto w-5 h-5 rounded-full border-2 flex items-center justify-center
                                            {{ $filled ? 'bg-accent-green border-accent-green' : ($isTarget ? 'bg-accent-gold/20 border-accent-gold' : ($isNext ? 'bg-surface-800 border-accent-blue' : 'bg-surface-800 border-surface-600')) }}">
                                            @if($filled)
                                                <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                            @elseif($isTarget)
                                                <div class="w-1.5 h-1.5 rounded-full bg-accent-gold animate-pulse"></div>
                                            @endif
                                        </div>
                                    </div>
                                    <span class="text-[10px] uppercase tracking-widest font-semibold
                                        {{ $filled ? 'text-accent-green' : ($isTarget ? 'text-accent-gold' : ($isNext ? 'text-accent-blue' : 'text-text-faint')) }}">
                                        {{ __('mockups.renovation.tier_label', ['num' => $t]) }}
                                    </span>
                                </div>
                            @endfor
                        </div>
                    </div>

                    {{-- Current effect --}}
                    <div class="px-5 py-4 flex-1">
                        <div class="text-[10px] text-text-muted uppercase tracking-widest mb-1">{{ __('mockups.renovation.current_effect') }}</div>
                        <p class="text-sm text-text-body leading-snug">{{ $b['current_effect'] }}</p>
                    </div>

                    {{-- Footer: action or in-progress strip --}}
                    @if($isInProgress)
                        <div class="px-5 py-3 border-t border-border-default bg-accent-gold/5">
                            <div class="flex items-baseline justify-between text-[11px] mb-1.5">
                                <span class="text-text-muted">{{ __('mockups.renovation.active.progress') }}</span>
                                <span class="text-text-body font-semibold tabular-nums">{{ $b['progress_pct'] }}% · {{ __('mockups.renovation.weeks_left', ['num' => $b['weeks_remaining']]) }}</span>
                            </div>
                            <div class="h-1.5 bg-surface-900/60 rounded-full overflow-hidden">
                                <div class="h-full rounded-full bg-accent-gold" style="width: {{ $b['progress_pct'] }}%"></div>
                            </div>
                        </div>
                    @elseif($isMax)
                        <div class="px-5 py-3 border-t border-border-default bg-surface-700/30 text-center">
                            <p class="text-[11px] text-text-muted">{{ __('mockups.renovation.max_help') }}</p>
                        </div>
                    @else
                        {{-- Idle: collapsible renovate preview --}}
                        <div class="border-t border-border-default">
                            <button @click="open = !open"
                                    class="w-full px-5 py-3 flex items-center justify-between gap-3 hover:bg-surface-700/40 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="shrink-0 w-7 h-7 rounded-md bg-accent-blue/15 text-accent-blue flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18"/></svg>
                                    </div>
                                    <div class="min-w-0 text-left">
                                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('mockups.renovation.next_tier') }}</div>
                                        <div class="text-sm font-semibold text-text-primary truncate">{{ __('mockups.renovation.upgrade_to', ['num' => $b['tier'] + 1]) }} · {{ Money::format($b['cost']) }}</div>
                                    </div>
                                </div>
                                <svg class="w-4 h-4 text-text-muted shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div x-show="open" x-collapse x-cloak class="px-5 pb-5 pt-1 border-t border-border-default bg-surface-700/20">
                                {{-- Benefit delta --}}
                                <div class="rounded-lg border border-border-default bg-surface-800 px-3.5 py-3 mt-3">
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest mb-2">{{ __('mockups.renovation.what_changes') }}</div>
                                    <div class="flex items-start gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-[10px] text-text-faint uppercase tracking-widest">{{ __('mockups.renovation.from') }}</div>
                                            <p class="text-xs text-text-muted leading-snug line-through decoration-text-faint">{{ $b['current_effect'] }}</p>
                                        </div>
                                        <svg class="w-4 h-4 text-text-faint shrink-0 mt-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-[10px] text-accent-green uppercase tracking-widest font-semibold">{{ __('mockups.renovation.to') }}</div>
                                            <p class="text-xs text-text-body leading-snug font-medium">{{ $b['next_effect'] }}</p>
                                        </div>
                                    </div>
                                    @if($b['next_delta'])
                                        <div class="mt-2.5 pt-2.5 border-t border-border-default flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-accent-green" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 18.75 6-6 4.5 4.5 8.25-9"/></svg>
                                            <span class="text-xs text-accent-green font-semibold">{{ $b['next_delta'] }}</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Cost / duration / budget impact --}}
                                <div class="mt-3 grid grid-cols-3 gap-2">
                                    <div class="rounded-lg bg-surface-800 border border-border-default px-3 py-2">
                                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('mockups.renovation.cost') }}</div>
                                        <div class="font-heading text-sm font-bold text-text-primary tabular-nums">{{ Money::format($b['cost']) }}</div>
                                    </div>
                                    <div class="rounded-lg bg-surface-800 border border-border-default px-3 py-2">
                                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('mockups.renovation.duration') }}</div>
                                        <div class="font-heading text-sm font-bold text-text-primary">{{ __('mockups.renovation.weeks', ['num' => $b['duration_weeks']]) }}</div>
                                    </div>
                                    <div class="rounded-lg bg-surface-800 border border-border-default px-3 py-2">
                                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('mockups.renovation.budget_after') }}</div>
                                        <div class="font-heading text-sm font-bold tabular-nums {{ $canAfford ? 'text-accent-green' : 'text-accent-red' }}">{{ Money::format(max(0, $renovationBudget - $b['cost'])) }}</div>
                                    </div>
                                </div>

                                {{-- CTA --}}
                                <div class="mt-3 flex flex-col sm:flex-row sm:items-center gap-2">
                                    <x-primary-button color="green" :disabled="!$canAfford" class="gap-1.5 flex-1 sm:flex-none">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                        {{ __('mockups.renovation.start_works') }}
                                    </x-primary-button>
                                    @if(!$canAfford)
                                        <span class="text-[11px] text-accent-red">{{ __('mockups.renovation.insufficient_budget') }}</span>
                                    @else
                                        <span class="text-[11px] text-text-muted">{{ __('mockups.renovation.confirm_hint') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Footnote --}}
        <p class="text-[11px] text-text-faint mt-8 text-center">{{ __('mockups.renovation.footnote') }}</p>

    </main>

    <style>
        @keyframes spin-slow { to { transform: rotate(360deg); } }
        .animate-spin-slow { animation: spin-slow 4s linear infinite; }
        [x-cloak] { display: none !important; }
    </style>
</body>
</html>
