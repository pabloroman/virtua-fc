@php
    /** @var App\Models\GamePlayer $gp */
    /** @var App\Models\Game $game */
    /** @var string $group */
    $posAbbrev = \App\Support\PositionMapper::toAbbreviation($gp->position);
    $reason = $gp->next_season_reason;
    $status = $gp->next_season_status;
    $nextAge = $gp->next_season_age;
    $role = $gp->squad_role ?? null;
    $blurb = $gp->squad_blurb ?? null;

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
        <div class="flex items-start gap-2.5">
            <x-player-avatar :name="$gp->name" :position-group="$group" :number="$gp->number" size="sm" />
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 min-w-0">
                    <span class="text-sm font-medium text-text-primary truncate">{{ $gp->name }}</span>
                </div>
                <div class="flex items-center gap-2 mt-1 min-w-0">
                    <div class="flex items-center gap-0.5 shrink-0">
                        @foreach($gp->positions as $pos)
                            <x-position-badge :position="$pos" size="sm" />
                        @endforeach
                    </div>
                    <span class="text-[10px] text-text-muted tabular-nums shrink-0">{{ __('planner.age_next', ['age' => $nextAge]) }}</span>
                </div>
                @if($blurb)
                    <p class="mt-1 text-[11px] italic text-text-secondary line-clamp-2">{{ $blurb }}</p>
                @endif
                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    @if($role)
                        <x-squad-role-badge :role="$role" />
                    @endif
                    @if($reason !== \App\Modules\Squad\Services\NextSeasonProjectionService::REASON_OWNED)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10px] font-medium {{ $reasonTone }}">
                            {{ $reasonLabel }}
                        </span>
                    @endif
                </div>
            </div>
            <x-rating-badge :value="$gp->next_season_overall" size="sm" class="shrink-0" />
        </div>
    </div>

    {{-- ===== Desktop row ===== --}}
    <div class="hidden lg:grid items-center px-4 py-2.5 gap-3 cursor-pointer grid-cols-[1.1fr_44px_1.4fr_150px_110px_140px]"
         @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $gp->id]) }}')">

        {{-- Player name + avatar + positions + contract --}}
        <div class="flex items-center gap-3 min-w-0">
            <x-player-avatar :name="$gp->name" :position-group="$group" :number="$gp->number" size="sm" />
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-text-primary truncate">{{ $gp->name }}</span>
                </div>
                <div class="flex items-center gap-1.5 mt-0.5 text-[10px] text-text-faint">
                    <div class="flex items-center gap-1">
                        @foreach($gp->positions as $pos)
                            <x-position-badge :position="$pos" size="sm" />
                        @endforeach
                    </div>
                    @if($gp->contract_expiry_year)
                        <span class="tabular-nums">·</span>
                        <span class="tabular-nums">{{ __('planner.contract_until', ['year' => $gp->contract_expiry_year]) }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Age (next season) --}}
        <div class="text-center">
            <div class="text-sm font-semibold text-text-primary tabular-nums">{{ $nextAge }}</div>
            <div class="text-[10px] text-text-faint uppercase tracking-wider">y</div>
        </div>

        {{-- Auto-generated blurb --}}
        <div class="min-w-0 text-[11px] italic text-text-secondary line-clamp-2">
            {{ $blurb }}
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

        {{-- Role badge --}}
        <div class="flex justify-center">
            @if($role)
                <x-squad-role-badge :role="$role" />
            @endif
        </div>

        {{-- Reason chip (only shown when the situation differs from a plain "owned" stay) --}}
        <div class="flex justify-end">
            @if($reason !== \App\Modules\Squad\Services\NextSeasonProjectionService::REASON_OWNED)
                <span class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] font-medium {{ $reasonTone }}">
                    {{ $reasonLabel }}
                </span>
            @endif
        </div>
    </div>
</div>
