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
        <div class="flex items-center justify-end gap-1.5 flex-wrap mb-2">
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

    {{-- xG Preview --}}
    <template x-if="xgPreview">
        <div class="bg-surface-700/50 border border-border-strong rounded-lg p-3 mb-3">
            <div class="text-[10px] font-semibold text-text-secondary uppercase tracking-wide mb-2 text-center">{{ __('game.xg_preview') }}</div>
            <div class="flex items-center justify-between gap-3">
                {{-- User xG --}}
                <div class="flex-1 text-center">
                    <div class="text-2xl font-bold tabular-nums"
                         :class="xgPreview.userXG > xgPreview.opponentXG ? 'text-accent-green' : (xgPreview.userXG < xgPreview.opponentXG ? 'text-accent-red' : 'text-text-primary')"
                         x-text="xgPreview.userXG.toFixed(2)"></div>
                    <div class="text-[10px] text-text-muted mt-0.5">{{ __('game.xg_your_team') }}</div>
                </div>

                {{-- Divider --}}
                <div class="text-text-secondary text-xs font-medium shrink-0">xG</div>

                {{-- Opponent xG --}}
                <div class="flex-1 text-center">
                    <div class="text-2xl font-bold tabular-nums"
                         :class="xgPreview.opponentXG > xgPreview.userXG ? 'text-accent-red' : (xgPreview.opponentXG < xgPreview.userXG ? 'text-accent-green' : 'text-text-primary')"
                         x-text="xgPreview.opponentXG.toFixed(2)"></div>
                    <div class="text-[10px] text-text-muted mt-0.5">{{ __('game.xg_opponent') }}</div>
                </div>
            </div>

            {{-- xG Difference Bar --}}
            <div class="mt-2">
                <div class="flex h-1.5 rounded-full overflow-hidden gap-0.5">
                    <div class="h-full rounded-l-full transition-all duration-300"
                         :class="xgPreview.userXG >= xgPreview.opponentXG ? 'bg-accent-green' : 'bg-accent-red'"
                         :style="'width: ' + (xgPreview.userXG / (xgPreview.userXG + xgPreview.opponentXG) * 100) + '%'"></div>
                    <div class="h-full rounded-r-full transition-all duration-300"
                         :class="xgPreview.opponentXG >= xgPreview.userXG ? 'bg-accent-red' : 'bg-accent-green'"
                         :style="'width: ' + (xgPreview.opponentXG / (xgPreview.userXG + xgPreview.opponentXG) * 100) + '%'"></div>
                </div>
            </div>

            <p class="text-[10px] text-text-secondary mt-2 text-center leading-relaxed">{{ __('game.xg_explanation') }}</p>
        </div>
    </template>

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
