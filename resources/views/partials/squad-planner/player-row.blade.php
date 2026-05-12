@php
    /** @var App\Models\GamePlayer $gp */
    /** @var App\Models\Game $game */
    /** @var string $group */
    $reason = $gp->next_season_reason;
    $status = $gp->next_season_status;
    $nextAge = $gp->next_season_age;
    $role = $gp->squad_role ?? null;
    $blurb = $gp->squad_blurb ?? null;
    $action = $gp->squad_action ?? null;

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

    $showReason = $reason !== \App\Modules\Squad\Services\NextSeasonProjectionService::REASON_OWNED;
    $contractExpiring = $gp->isContractExpiring($game->getSeasonEndDate());
@endphp

{{-- Mobile row --}}
<div class="md:hidden px-4 py-3 border-b border-border-default last:border-b-0 cursor-pointer"
     @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $gp->id]) }}')">
    <div class="flex items-center gap-3">
        <x-player-avatar :name="$gp->name" :position-group="$group" :number="$gp->number" size="sm" />
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-sm font-medium text-text-primary truncate">{{ $gp->name }}</span>
                @if($showReason)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full border text-[10px] font-medium {{ $reasonTone }}">
                        {{ $reasonLabel }}
                    </span>
                @endif
            </div>
            <div class="flex items-center gap-2 mt-1 min-w-0 text-[10px] text-text-faint">
                <div class="flex items-center gap-0.5 shrink-0">
                    @foreach($gp->positions as $pos)
                        <x-position-badge :position="$pos" size="sm" />
                    @endforeach
                </div>
                <span class="tabular-nums shrink-0">{{ $nextAge }}</span>
                @if($gp->contract_expiry_year)
                    <span>·</span>
                    <span class="tabular-nums {{ $contractExpiring ? 'text-accent-red font-medium' : '' }}">{{ $gp->contract_expiry_year }}</span>
                @endif
            </div>
            <div class="mt-2 flex items-center gap-1.5">
                @if($role)
                    <x-squad-role-badge :role="$role" :tooltip="$blurb" />
                @endif
                <x-squad-action-chip :action="$action" />
            </div>
        </div>
        <div class="shrink-0 w-[130px]">
            <x-potential-bar
                :current-ability="$gp->overall_score"
                :potential-low="$gp->potential_low"
                :potential-high="$gp->potential_high"
                :projection="$gp->projection"
                size="sm" />
        </div>
    </div>
</div>

{{-- Desktop row --}}
<div class="hidden md:grid grid-cols-[40px_1fr_140px_48px_72px_180px_48px] gap-3 items-center px-4 py-2.5 border-b border-border-default last:border-b-0 hover:bg-surface-700/30 transition-colors cursor-pointer"
     @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $gp->id]) }}')">

    {{-- Position badge --}}
    <div class="flex justify-center">
        <x-position-badge :position="$gp->position" size="sm" :tooltip="\App\Support\PositionMapper::toDisplayName($gp->position)" class="cursor-help" />
    </div>

    {{-- Name + nationality + reason badge --}}
    <div class="flex items-center gap-2 min-w-0">
        @if($gp->nationality_flag)
            <img src="{{ Storage::disk('assets')->url('flags/' . $gp->nationality_flag['code'] . '.svg') }}"
                 class="w-4 h-3 rounded-xs shadow-xs shrink-0"
                 title="{{ $gp->nationality_flag['name'] }}"
                 alt="">
        @endif
        <span class="text-sm font-medium text-text-primary truncate">{{ $gp->name }}</span>
        @if($showReason)
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full border text-[10px] font-medium whitespace-nowrap shrink-0 {{ $reasonTone }}">
                {{ $reasonLabel }}
            </span>
        @endif
    </div>

    {{-- Action tag (icon + label) --}}
    <div class="flex justify-end">
        <x-squad-action-chip :action="$action" />
    </div>

    {{-- Age (next season) --}}
    <span class="text-xs text-text-secondary text-center tabular-nums">{{ $nextAge }}</span>

    {{-- Contract --}}
    <span class="text-[11px] text-center tabular-nums {{ $contractExpiring ? 'text-accent-red font-medium' : 'text-text-muted' }}">
        {{ $gp->contract_expiry_year ?? '—' }}
    </span>

    {{-- Quality + potential bar --}}
    <div>
        <x-potential-bar
            :current-ability="$gp->overall_score"
            :potential-low="$gp->potential_low"
            :potential-high="$gp->potential_high"
            :projection="$gp->projection"
            size="sm" />
    </div>

    {{-- Role icon (label + blurb in tooltip) --}}
    <div class="flex justify-center">
        @if($role)
            <x-squad-role-badge :role="$role" :tooltip="$blurb" />
        @endif
    </div>
</div>
