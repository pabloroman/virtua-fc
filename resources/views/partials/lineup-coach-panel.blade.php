<div class="space-y-3">

    {{-- Face to Face Comparison --}}
    <div class="flex items-center justify-between gap-2 mb-1">
        {{-- User Team --}}
        <div class="flex items-center gap-2 min-w-0">
            <x-team-crest :team="$game->team" class="w-7 h-7 shrink-0" />
            <span class="text-lg font-bold text-text-primary" x-text="teamAverage || '-'"></span>
        </div>

        {{-- Advantage Badge --}}
        <template x-if="teamAverage && {{ $opponentData['teamAverage'] ?: 0 }}">
            <span
                class="text-xs font-semibold px-2 py-0.5 rounded-full shrink-0"
                :class="{
                    'bg-accent-green/10 text-accent-green': teamAverage > {{ $opponentData['teamAverage'] ?: 0 }},
                    'bg-accent-red/10 text-accent-red': teamAverage < {{ $opponentData['teamAverage'] ?: 0 }},
                    'bg-surface-700 text-text-secondary': teamAverage === {{ $opponentData['teamAverage'] ?: 0 }}
                }"
                x-text="teamAverage > {{ $opponentData['teamAverage'] ?: 0 }} ? '+' + (teamAverage - {{ $opponentData['teamAverage'] ?: 0 }}) : (teamAverage < {{ $opponentData['teamAverage'] ?: 0 }} ? (teamAverage - {{ $opponentData['teamAverage'] ?: 0 }}) : '=')"
            ></span>
        </template>
        <template x-if="!teamAverage || !{{ $opponentData['teamAverage'] ?: 0 }}">
            <span class="text-xs text-text-secondary">vs</span>
        </template>

        {{-- Opponent Team --}}
        <div class="flex items-center gap-2 min-w-0">
            <span class="text-lg font-bold text-text-primary">{{ $opponentData['teamAverage'] ?: '-' }}</span>
            <x-team-crest :team="$opponent" class="w-7 h-7 shrink-0" />
        </div>
    </div>

    {{-- Opponent Expected Tactics --}}
    @if(!empty($opponentData['formation']))
        <div class="flex items-center justify-end gap-1.5 mb-2">
            <span class="text-[10px] text-text-secondary uppercase tracking-wide">{{ __('squad.coach_opponent_expected_label') }}</span>
            <span class="text-xs font-semibold text-text-body bg-surface-700 px-1.5 py-0.5 rounded-sm">{{ $opponentData['formation'] }}</span>
            <span class="text-text-body">&middot;</span>
            <span class="text-xs font-medium
                @if($opponentData['mentality'] === 'defensive') text-accent-blue
                @elseif($opponentData['mentality'] === 'attacking') text-accent-red
                @else text-text-secondary
                @endif">{{ __('squad.mentality_' . $opponentData['mentality']) }}</span>
        </div>
    @endif

    {{-- Form (symmetrical) --}}
    <div class="flex items-center justify-between gap-2 mb-3">
        {{-- User Form --}}
        <div class="flex gap-1">
            @forelse($playerForm as $result)
                <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center
                    @if($result === 'W') bg-accent-green text-white
                    @elseif($result === 'D') bg-surface-600 text-text-body
                    @else bg-accent-red text-white @endif">
                    {{ $result }}
                </span>
            @empty
                <span class="text-[10px] text-text-secondary">—</span>
            @endforelse
        </div>

        <span class="text-[10px] text-text-secondary">{{ __('game.form') }}</span>

        {{-- Opponent Form --}}
        <div class="flex gap-1">
            @forelse($opponentData['form'] as $result)
                <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center
                    @if($result === 'W') bg-accent-green text-white
                    @elseif($result === 'D') bg-surface-600 text-text-body
                    @else bg-accent-red text-white @endif">
                    {{ $result }}
                </span>
            @empty
                <span class="text-[10px] text-text-secondary">—</span>
            @endforelse
        </div>
    </div>

    {{-- Radar Chart --}}
    <div class="border-t border-border-default pt-3 mb-3">
        <div class="flex items-center justify-center gap-4 mb-1">
            <span class="flex items-center gap-1 text-[10px] text-text-muted">
                <span class="w-2 h-1 rounded-xs bg-sky-400 inline-block"></span>
                {{ $game->team->short_name ?? $game->team->name }}
            </span>
            <span class="flex items-center gap-1 text-[10px] text-text-muted">
                <span class="w-2 h-1 rounded-xs bg-red-400 inline-block"></span>
                {{ $opponent->short_name ?? $opponent->name }}
            </span>
        </div>
        <x-radar-chart
            :userValues="$userRadar"
            :opponentValues="$opponentRadar"
            :labels="[
                'goalkeeper' => __('squad.radar_gk'),
                'defense' => __('squad.radar_def'),
                'midfield' => __('squad.radar_mid'),
                'attack' => __('squad.radar_att'),
                'fitness' => __('squad.radar_fit'),
                'morale' => __('squad.radar_mor'),
                'technical' => __('squad.radar_tec'),
                'physical' => __('squad.radar_phy'),
            ]"
        />
    </div>

    {{-- Tips Section --}}
    <div class="border-t border-border-default pt-3">
        <div class="text-[10px] font-semibold text-text-secondary uppercase tracking-wide mb-2">{{ __('squad.coach_recommendations') }}</div>

        {{-- Dynamic Tips --}}
        <template x-if="coachTips.length > 0">
            <div class="space-y-2">
                <template x-for="tip in coachTips" :key="tip.id">
                    <div class="flex items-start gap-2">
                        <span
                            class="w-1.5 h-1.5 rounded-full mt-1.5 shrink-0"
                            :class="tip.type === 'warning' ? 'bg-amber-400' : 'bg-sky-400'"
                        ></span>
                        <span class="text-xs text-text-secondary leading-relaxed" x-text="tip.message"></span>
                    </div>
                </template>
            </div>
        </template>

        {{-- Empty State --}}
        <template x-if="coachTips.length === 0">
            <p class="text-xs text-text-secondary italic" x-text="translations.coach_no_tips"></p>
        </template>
    </div>
</div>
