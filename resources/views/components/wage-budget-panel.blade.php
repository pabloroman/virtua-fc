@props([
    'currentSeason',
    'nextSeason' => null,
    'compact' => false,
    'route' => null,
])

@php
    $seasons = array_values(array_filter([$currentSeason, $nextSeason]));
    $eitherOverCap = collect($seasons)->contains(fn ($h) => $h->headroom() < 0);
@endphp

<div class="bg-surface-700/50 border border-border-default rounded-lg overflow-hidden">
    <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-border-default">
        <h3 class="font-heading text-xs font-semibold uppercase tracking-widest text-text-secondary">
            {{ __('finances.wage_budget_panel_title') }}
        </h3>
        @if($route)
            <a href="{{ $route }}" class="text-[11px] text-accent-blue hover:underline">
                {{ __('finances.finances') }} →
            </a>
        @endif
    </div>

    @if($eitherOverCap)
        <div class="px-4 py-2 bg-accent-red/10 border-b border-accent-red/20 text-xs text-accent-red">
            {{ __('finances.wage_budget_panel_blocked') }}
        </div>
    @endif

    <div class="grid {{ $nextSeason ? 'grid-cols-1 md:grid-cols-2' : 'grid-cols-1' }} divide-y md:divide-y-0 md:divide-x divide-border-default">
        @foreach($seasons as $headroom)
            @php
                $over = $headroom->headroom() < 0;
                $pct = max(0, min(100, $headroom->utilisation() * 100));
                $barColor = match(true) {
                    $over => 'bg-accent-red',
                    $pct >= 90 => 'bg-accent-gold',
                    default => 'bg-accent-green',
                };
                $isNext = $headroom === $nextSeason;
            @endphp
            <div class="p-4 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-text-muted">
                        {{ $isNext ? __('finances.wage_budget_next_season') : __('finances.wage_budget_this_season') }}
                    </span>
                    <span class="text-[10px] tabular-nums text-text-muted">
                        {{ __('finances.wage_budget_utilisation', ['percent' => (int) round($pct)]) }}
                    </span>
                </div>

                <div class="text-[11px] text-text-muted flex justify-between">
                    <span>{{ __('finances.wage_budget_revenue') }}</span>
                    <span class="tabular-nums text-text-secondary">{{ \App\Support\Money::format($headroom->projectedRevenue) }}</span>
                </div>

                @if($isNext)
                    <div class="text-[11px] text-text-muted flex justify-between">
                        <span>{{ __('finances.wage_budget_carrying_wages') }}</span>
                        <span class="tabular-nums text-text-secondary">−{{ \App\Support\Money::format($headroom->currentSquadWages) }}</span>
                    </div>
                    <div class="text-[11px] text-text-muted flex justify-between">
                        <span>{{ __('finances.wage_budget_pre_contracts') }}</span>
                        <span class="tabular-nums text-text-secondary">−{{ \App\Support\Money::format($headroom->pendingPreContractWages) }}</span>
                    </div>
                @else
                    <div class="text-[11px] text-text-muted flex justify-between">
                        <span>{{ __('finances.wage_budget_current_wages') }}</span>
                        <span class="tabular-nums text-text-secondary">−{{ \App\Support\Money::format($headroom->currentSquadWages) }}</span>
                    </div>
                @endif

                <div class="pt-2 border-t border-border-default flex items-center justify-between">
                    <span class="text-xs font-semibold {{ $over ? 'text-accent-red' : 'text-text-primary' }}">
                        {{ $over ? __('finances.wage_budget_over_cap') : __('finances.wage_budget_headroom') }}
                    </span>
                    <span class="font-heading text-base font-bold tabular-nums {{ $over ? 'text-accent-red' : 'text-accent-green' }}">
                        {{ \App\Support\Money::format($headroom->headroom()) }}
                    </span>
                </div>

                <div class="w-full h-1.5 bg-surface-800 rounded-full overflow-hidden">
                    <div class="h-1.5 rounded-full {{ $barColor }}" style="width: {{ $pct }}%"></div>
                </div>
            </div>
        @endforeach
    </div>
</div>
