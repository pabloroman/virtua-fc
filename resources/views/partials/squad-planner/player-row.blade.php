@php
    /** @var App\Models\GamePlayer $gp */
    /** @var App\Models\Game $game */
    /** @var string $group */
    $posAbbrev = \App\Support\PositionMapper::toAbbreviation($gp->position);
    $reason = $gp->next_season_reason;
    $status = $gp->next_season_status;
    $nextAge = $gp->next_season_age;

    $reasonTone = match ($status) {
        \App\Modules\Squad\Services\NextSeasonProjectionService::STATUS_OUTGOING => 'bg-accent-red/10 text-accent-red border-accent-red/20',
        \App\Modules\Squad\Services\NextSeasonProjectionService::STATUS_INCOMING => 'bg-accent-green/10 text-accent-green border-accent-green/20',
        default => match ($reason) {
            \App\Modules\Squad\Services\NextSeasonProjectionService::REASON_STILL_ON_LOAN,
            \App\Modules\Squad\Services\NextSeasonProjectionService::REASON_RETURNING_FROM_LOAN => 'bg-accent-blue/10 text-accent-blue border-accent-blue/20',
            \App\Modules\Squad\Services\NextSeasonProjectionService::REASON_RENEWED => 'bg-accent-green/10 text-accent-green border-accent-green/20',
            default => 'bg-surface-700 text-text-muted border-border-default',
        },
    };

    $reasonLabel = match ($reason) {
        \App\Modules\Squad\Services\NextSeasonProjectionService::REASON_STILL_ON_LOAN
            => __('planner.reason_still_on_loan', ['date' => $gp->activeLoan?->return_at?->translatedFormat('M Y') ?? '—']),
        default => __('planner.reason_' . $reason),
    };
@endphp

<div class="border-b border-border-default last:border-b-0">
    {{-- ===== Mobile row ===== --}}
    <div class="lg:hidden px-4 py-3 cursor-pointer" @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $gp->id]) }}')">
        <div class="flex items-center gap-2.5">
            <x-player-avatar :name="$gp->name" :position-group="$group" :number="$gp->number" size="sm" />
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5">
                    <span class="text-sm font-medium text-text-primary truncate">{{ $gp->name }}</span>
                </div>
                <div class="flex items-center gap-2 mt-1">
                    <div class="flex items-center gap-0.5 shrink-0">
                        @foreach($gp->positions as $pos)
                            <x-position-badge :position="$pos" size="sm" />
                        @endforeach
                    </div>
                    <span class="text-[10px] text-text-muted tabular-nums shrink-0">{{ __('planner.age_next', ['age' => $nextAge]) }}</span>
                </div>
                <div class="mt-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10px] font-medium {{ $reasonTone }}">
                        {{ $reasonLabel }}
                    </span>
                </div>
            </div>
            <x-rating-badge :value="$gp->next_season_overall" size="sm" class="shrink-0" />
        </div>
    </div>

    {{-- ===== Desktop row ===== --}}
    <div class="hidden lg:grid items-center px-4 py-2.5 gap-3 cursor-pointer grid-cols-[1fr_64px_56px_160px_88px_180px]"
         @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $gp->id]) }}')">

        {{-- Player name + avatar --}}
        <div class="flex items-center gap-3 min-w-0">
            <x-player-avatar :name="$gp->name" :position-group="$group" :number="$gp->number" size="sm" />
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-text-primary truncate">{{ $gp->name }}</span>
                </div>
                <div class="flex items-center gap-1.5 mt-0.5">
                    @foreach($gp->positions as $pos)
                        <x-position-badge :position="$pos" size="sm" />
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Age (next season) --}}
        <div class="text-center">
            <div class="text-sm font-semibold text-text-primary tabular-nums">{{ $nextAge }}</div>
            <div class="text-[10px] text-text-faint uppercase tracking-wider">{{ __('app.age') }}</div>
        </div>

        {{-- Contract --}}
        <div class="text-center text-[11px] tabular-nums">
            @if($gp->contract_expiry_year)
                <div class="text-text-secondary">{{ __('planner.contract_until', ['year' => $gp->contract_expiry_year]) }}</div>
            @else
                <div class="text-text-faint">{{ __('planner.no_contract') }}</div>
            @endif
        </div>

        {{-- Potential bar (current + projected) --}}
        <div>
            <x-potential-bar
                :current-ability="$gp->overall_score"
                :potential-low="$gp->potential_low"
                :potential-high="$gp->potential_high"
                :projection="$gp->projection"
                size="sm" />
        </div>

        {{-- Next-season overall rating --}}
        <div class="flex justify-center">
            <x-rating-badge :value="$gp->next_season_overall" size="sm" />
        </div>

        {{-- Reason --}}
        <div class="flex justify-end">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] font-medium {{ $reasonTone }}">
                {{ $reasonLabel }}
            </span>
        </div>
    </div>
</div>
