<div class="bg-slate-50 rounded-lg p-4 border border-slate-200 {{ $class ?? '' }}">
    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ __('squad.coach_assistant') }}</div>

    {{-- Face to Face Comparison --}}
    <div class="flex items-center justify-between gap-2 mb-1">
        {{-- User Team --}}
        <div class="flex items-center gap-2 min-w-0">
            <x-team-crest :team="$game->team" class="w-7 h-7 shrink-0" />
            <span class="text-lg font-bold text-slate-900" x-text="teamAverage || '-'"></span>
        </div>

        {{-- Advantage Badge --}}
        <template x-if="teamAverage && {{ $opponentData['teamAverage'] ?: 0 }}">
            <span
                class="text-xs font-semibold px-2 py-0.5 rounded-full shrink-0"
                :class="{
                    'bg-green-100 text-green-700': teamAverage > {{ $opponentData['teamAverage'] ?: 0 }},
                    'bg-red-100 text-red-700': teamAverage < {{ $opponentData['teamAverage'] ?: 0 }},
                    'bg-slate-100 text-slate-600': teamAverage === {{ $opponentData['teamAverage'] ?: 0 }}
                }"
                x-text="teamAverage > {{ $opponentData['teamAverage'] ?: 0 }} ? '+' + (teamAverage - {{ $opponentData['teamAverage'] ?: 0 }}) : (teamAverage < {{ $opponentData['teamAverage'] ?: 0 }} ? (teamAverage - {{ $opponentData['teamAverage'] ?: 0 }}) : '=')"
            ></span>
        </template>
        <template x-if="!teamAverage || !{{ $opponentData['teamAverage'] ?: 0 }}">
            <span class="text-xs text-slate-400">vs</span>
        </template>

        {{-- Opponent Team --}}
        <div class="flex items-center gap-2 min-w-0">
            <span class="text-lg font-bold text-slate-900">{{ $opponentData['teamAverage'] ?: '-' }}</span>
            <x-team-crest :team="$opponent" class="w-7 h-7 shrink-0" />
        </div>
    </div>

    {{-- Opponent Expected Tactics --}}
    @if(!empty($opponentData['formation']))
        <div class="flex items-center justify-end gap-1.5 mb-2">
            <span class="text-[10px] text-slate-400 uppercase tracking-wide">{{ __('squad.coach_opponent_expected_label') }}</span>
            <span class="text-xs font-semibold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded">{{ $opponentData['formation'] }}</span>
            <span class="text-slate-300">&middot;</span>
            <span class="text-xs font-medium
                @if($opponentData['mentality'] === 'defensive') text-blue-600
                @elseif($opponentData['mentality'] === 'attacking') text-red-600
                @else text-slate-600
                @endif">{{ __('squad.mentality_' . $opponentData['mentality']) }}</span>
        </div>
    @endif

    {{-- Form (symmetrical) --}}
    <div class="flex items-center justify-between gap-2 mb-3">
        {{-- User Form --}}
        <div class="flex gap-1">
            @forelse($playerForm as $result)
                <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center
                    @if($result === 'W') bg-green-500 text-white
                    @elseif($result === 'D') bg-slate-400 text-white
                    @else bg-red-500 text-white @endif">
                    {{ $result }}
                </span>
            @empty
                <span class="text-[10px] text-slate-400">—</span>
            @endforelse
        </div>

        <span class="text-[10px] text-slate-400">{{ __('game.form') }}</span>

        {{-- Opponent Form --}}
        <div class="flex gap-1">
            @forelse($opponentData['form'] as $result)
                <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center
                    @if($result === 'W') bg-green-500 text-white
                    @elseif($result === 'D') bg-slate-400 text-white
                    @else bg-red-500 text-white @endif">
                    {{ $result }}
                </span>
            @empty
                <span class="text-[10px] text-slate-400">—</span>
            @endforelse
        </div>
    </div>

    {{-- Radar Chart --}}
    <div class="border-t border-slate-200 pt-3 mb-3">
        <div class="flex items-center justify-center gap-4 mb-1">
            <span class="flex items-center gap-1 text-[10px] text-slate-500">
                <span class="w-2 h-1 rounded-sm bg-sky-400 inline-block"></span>
                {{ $game->team->short_name ?? $game->team->name }}
            </span>
            <span class="flex items-center gap-1 text-[10px] text-slate-500">
                <span class="w-2 h-1 rounded-sm bg-red-400 inline-block"></span>
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
    <div class="border-t border-slate-200 pt-3">
        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-2">{{ __('squad.coach_recommendations') }}</div>

        {{-- Dynamic Tips --}}
        <template x-if="coachTips.length > 0">
            <div class="space-y-2">
                <template x-for="tip in coachTips" :key="tip.id">
                    <div class="flex items-start gap-2">
                        <span
                            class="w-1.5 h-1.5 rounded-full mt-1.5 shrink-0"
                            :class="tip.type === 'warning' ? 'bg-amber-400' : 'bg-sky-400'"
                        ></span>
                        <span class="text-xs text-slate-600 leading-relaxed" x-text="tip.message"></span>
                    </div>
                </template>
            </div>
        </template>

        {{-- Empty State --}}
        <template x-if="coachTips.length === 0">
            <p class="text-xs text-slate-400 italic" x-text="translations.coach_no_tips"></p>
        </template>
    </div>
</div>
