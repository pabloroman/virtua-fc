@php
    /** @var App\Models\Game $game */
    /** @var bool $isCareerMode */
@endphp

<div class="space-y-5">
    {{-- Number Grid (visible only in numbers mode) --}}
    <div x-show="viewMode === 'numbers'" x-cloak>
        <h4 class="font-heading text-[11px] font-semibold text-text-muted uppercase tracking-widest pb-2 border-b border-border-default mb-3">{{ __('squad.number_grid') }}</h4>
        <div class="grid grid-cols-10 gap-1">
            @for($n = 1; $n <= 99; $n++)
            <div class="aspect-square flex items-center justify-center rounded-sm text-xs font-medium cursor-default transition-colors"
                 :class="getNumberOwner({{ $n }}) ? 'bg-accent-blue/10 text-accent-blue border border-accent-blue/20' : 'bg-surface-700/50 text-text-body border border-border-default'"
                 :title="getNumberOwner({{ $n }})?.name ?? '{{ __('squad.available_number') }}'">
                <span class="tabular-nums">{{ $n }}</span>
            </div>
            @endfor
        </div>
        <div class="mt-3 flex items-center gap-3 text-xs text-text-muted">
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-accent-blue/10 border border-accent-blue/20"></span> {{ __('squad.assigned') }}</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-surface-700/50 border border-border-default"></span> {{ __('squad.available_number') }}</span>
        </div>
    </div>

    {{-- Standard sidebar content (hidden in numbers mode) --}}
    <template x-if="viewMode !== 'numbers'">
    <div class="space-y-5">

    {{-- Alerts --}}
    @if(count($alerts) > 0)
    <div>
        <h4 class="font-heading text-[11px] font-semibold text-text-muted uppercase tracking-widest pb-2 border-b border-border-default mb-3">{{ __('squad.alerts') }}</h4>
        <div class="space-y-2">
            @foreach($alerts as $alert)
                <div class="flex items-start gap-2 p-2.5 rounded-lg text-xs
                    @if($alert['type'] === 'danger') bg-accent-red/10 text-accent-red border border-accent-red/20
                    @elseif($alert['type'] === 'warning') bg-accent-gold/10 text-accent-gold border border-accent-gold/20
                    @else bg-accent-blue/10 text-accent-blue border border-accent-blue/20
                    @endif">
                    @if($alert['type'] === 'danger')
                        <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    @elseif($alert['type'] === 'warning')
                        <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @else
                        <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                    <span>{{ $alert['message'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Position Depth Chart --}}
    <div>
        <h4 class="font-heading text-[11px] font-semibold text-text-muted uppercase tracking-widest pb-2 border-b border-border-default mb-3">{{ __('squad.position_depth') }}</h4>
        <div class="space-y-1.5">
            @foreach($depthChart as $slot => $data)
                @php
                    $slotGroup = \App\Support\PositionSlotMapper::getSlotPositionGroup($slot);
                    $barColor = match(true) {
                        $data['count'] >= 3 => 'bg-green-400',
                        $data['count'] === 2 => 'bg-green-300',
                        $data['count'] === 1 => 'bg-amber-400',
                        default => 'bg-red-400',
                    };
                @endphp
                <div class="flex items-center gap-2">
                    <span class="w-8 text-xs font-medium text-text-secondary tabular-nums text-right shrink-0">{{ \App\Support\PositionMapper::slotToDisplayAbbreviation($slot) }}</span>
                    <div class="flex-1 flex items-center gap-1">
                        @for($i = 0; $i < min($data['count'], 5); $i++)
                            <div class="w-4 h-4 rounded-xs {{ $barColor }}"></div>
                        @endfor
                        @if($data['count'] === 0)
                            <div class="w-4 h-4 rounded-xs border-2 border-dashed border-red-500/40"></div>
                        @endif
                    </div>
                    <span class="text-xs tabular-nums text-text-secondary w-4 text-right">{{ $data['count'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Age Profile --}}
    <div>
        <h4 class="font-heading text-[11px] font-semibold text-text-muted uppercase tracking-widest pb-2 border-b border-border-default mb-3">{{ __('squad.age_profile') }}</h4>
        @php
            $total = max($squadSize, 1);
            $youngPct = round($youngCount / $total * 100);
            $primePct = round($primeCount / $total * 100);
            $veteranPct = 100 - $youngPct - $primePct;
        @endphp
        <div class="flex h-3 rounded-full overflow-hidden bg-surface-700">
            @if($youngPct > 0)
                <div class="bg-green-400 transition-all" style="width: {{ $youngPct }}%"></div>
            @endif
            @if($primePct > 0)
                <div class="bg-sky-400 transition-all" style="width: {{ $primePct }}%"></div>
            @endif
            @if($veteranPct > 0)
                <div class="bg-orange-400 transition-all" style="width: {{ $veteranPct }}%"></div>
            @endif
        </div>
        <div class="flex items-center justify-between mt-2 text-xs">
            <span class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-green-400"></span>
                <span class="text-text-secondary">≤23: {{ $youngCount }}</span>
            </span>
            <span class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                <span class="text-text-secondary">24-31: {{ $primeCount }}</span>
            </span>
            <span class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                <span class="text-text-secondary">32+: {{ $veteranCount }}</span>
            </span>
        </div>
    </div>

    {{-- Contract Watchlist (career mode) --}}
    @if($isCareerMode)
    <div>
        <h4 class="font-heading text-[11px] font-semibold text-text-muted uppercase tracking-widest pb-2 border-b border-border-default mb-3">{{ __('squad.contract_watch') }}</h4>
        <div class="space-y-3">
            @if($expiringThisSeason->isNotEmpty())
                <div>
                    <div class="text-xs font-medium text-accent-red mb-1.5">{{ __('squad.expiring_this_season') }}</div>
                    <div class="space-y-1">
                        @foreach($expiringThisSeason as $ep)
                            <x-ghost-button color="slate" size="xs" @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $ep->id]) }}')"
                                    class="w-full justify-between py-1 px-2 text-left">
                                <span class="text-xs text-text-body truncate">{{ $ep->name }}</span>
                                <span class="text-xs text-red-500 font-medium shrink-0 ml-2">{{ $ep->contract_expiry_year }}</span>
                            </x-ghost-button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($expiringNextSeason->isNotEmpty())
                <div>
                    <div class="text-xs font-medium text-amber-600 mb-1.5">{{ __('squad.expiring_next_season') }}</div>
                    <div class="space-y-1">
                        @foreach($expiringNextSeason as $ep)
                            <x-ghost-button color="slate" size="xs" @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $ep->id]) }}')"
                                    class="w-full justify-between py-1 px-2 text-left">
                                <span class="text-xs text-text-body truncate">{{ $ep->name }}</span>
                                <span class="text-xs text-amber-500 font-medium shrink-0 ml-2">{{ $ep->contract_expiry_year }}</span>
                            </x-ghost-button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($expiringThisSeason->isEmpty() && $expiringNextSeason->isEmpty())
                <p class="text-xs text-text-secondary italic">{{ __('squad.no_contract_issues') }}</p>
            @endif

            @if($highEarners->isNotEmpty())
                <div>
                    <div class="text-xs font-medium text-text-secondary mb-1.5">{{ __('squad.highest_earners') }}</div>
                    <div class="space-y-1">
                        @foreach($highEarners as $he)
                            <div class="flex items-center justify-between py-1 px-2 text-xs">
                                <span class="text-text-body truncate">{{ $he->name }}</span>
                                <span class="text-text-muted font-medium shrink-0 ml-2">{{ $he->formatted_wage }}{{ __('squad.per_year') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endif

    </div>
    </template>
</div>
