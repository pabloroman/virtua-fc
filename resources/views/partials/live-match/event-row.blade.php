{{-- Narrative events: compact inline rows --}}
<template x-if="isNarrativeEvent(event.type)">
    <div class="flex items-center gap-2 py-0.5 opacity-60">
        <span class="font-heading text-[10px] text-text-muted w-8 text-right shrink-0 tabular-nums"
              x-text="event.minute + '\''"></span>
        <span class="w-5 text-center shrink-0 flex items-center justify-center">
            {{-- Shot on target --}}
            <template x-if="event.type === 'shot_on_target'">
                <svg class="w-3 h-3 text-text-muted" fill="currentColor" viewBox="0 0 16 16"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="8" cy="8" r="2"/></svg>
            </template>
            {{-- Shot off target --}}
            <template x-if="event.type === 'shot_off_target'">
                <svg class="w-3 h-3 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 16 16"><circle cx="8" cy="8" r="6" stroke-width="1.5"/><path stroke-width="1.5" d="M5 11L11 5"/></svg>
            </template>
            {{-- Dangerous attack --}}
            <template x-if="event.type === 'dangerous_attack'">
                <svg class="w-3 h-3 text-text-muted" fill="currentColor" viewBox="0 0 16 16"><path d="M8 1l2 5h5l-4 3 1.5 5L8 11l-4.5 3L5 9 1 6h5z"/></svg>
            </template>
            {{-- Great save --}}
            <template x-if="event.type === 'great_save'">
                <svg class="w-3 h-3 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 16 16"><path stroke-width="1.5" d="M3 8a5 5 0 0110 0M8 3v2M5 4l1 1.5M11 4l-1 1.5"/></svg>
            </template>
            {{-- Near miss --}}
            <template x-if="event.type === 'near_miss'">
                <svg class="w-3 h-3 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 16 16"><rect x="2" y="4" width="12" height="8" rx="1" stroke-width="1.5"/><path stroke-width="1.5" d="M14 5l-3 3"/></svg>
            </template>
            {{-- Key pass --}}
            <template x-if="event.type === 'key_pass'">
                <svg class="w-3 h-3 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 16 16"><path stroke-width="1.5" d="M3 8h7m0 0l-3-3m3 3l-3 3"/></svg>
            </template>
        </span>
        <span class="text-[10px] text-text-muted truncate" x-text="getNarrativeLabel(event)"></span>
    </div>
</template>

{{-- Insight events: distinctive accent styling --}}
<template x-if="event.type === 'insight'">
    <div class="flex items-start gap-2 py-1.5 px-2 -mx-2 rounded-lg bg-accent-blue/5 border border-accent-blue/10">
        <span class="font-heading text-[10px] text-accent-blue w-8 text-right shrink-0 tabular-nums mt-0.5"
              x-text="event.minute + '\''"></span>
        <svg class="w-3.5 h-3.5 text-accent-blue shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
        </svg>
        <span class="text-[10px] text-accent-blue font-medium" x-text="getInsightText(event)"></span>
    </div>
</template>

{{-- Standard events (goal, card, injury, substitution) --}}
<template x-if="!isNarrativeEvent(event.type) && event.type !== 'insight'">
    <div class="flex items-center gap-2">
        <span class="font-heading font-bold text-xs text-text-muted w-8 text-right shrink-0 tabular-nums"
              x-text="event.minute + '\''"></span>
        <span class="w-6 text-center shrink-0 flex items-center justify-center"
              x-show="event.type === 'goal'">
            <svg class="w-3.5 h-3.5 text-accent-green" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8"/></svg>
        </span>
        <span class="w-6 text-center shrink-0 flex items-center justify-center"
              x-show="event.type === 'own_goal'">
            <svg class="w-3.5 h-3.5 text-accent-red" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8"/></svg>
        </span>
        <span class="w-6 text-center shrink-0 flex items-center justify-center"
              x-show="event.type === 'yellow_card'">
            <div class="w-2.5 h-3.5 rounded-[2px] bg-accent-gold"></div>
        </span>
        <span class="w-6 text-center shrink-0 flex items-center justify-center"
              x-show="event.type === 'red_card'">
            <div class="w-2.5 h-3.5 rounded-[2px] bg-accent-red"></div>
        </span>
        <span class="w-6 text-center shrink-0 flex items-center justify-center"
              x-show="event.type === 'injury'">
            <svg class="w-3.5 h-3.5 text-accent-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </span>
        <span class="w-6 text-center shrink-0 flex items-center justify-center"
              x-show="event.type === 'substitution'">
            <svg class="w-3.5 h-3.5 text-accent-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
        </span>
        <img :src="getEventSide(event) === 'home' ? homeTeamImage : awayTeamImage"
             class="w-5 h-5 shrink-0 object-contain"
             :alt="getEventSide(event) === 'home' ? 'Home' : 'Away'">
        <div class="flex-1 min-w-0">
            <span class="font-semibold text-xs text-text-primary" x-text="event.type === 'substitution' ? event.playerInName : event.playerName"></span>
            <template x-if="event.type === 'goal'">
                <span class="text-[10px] text-text-muted ml-1">{{ __('game.live_goal') }}</span>
            </template>
            <template x-if="event.type === 'own_goal'">
                <span class="text-[10px] text-accent-red ml-1">({{ __('game.og') }})</span>
            </template>
            <template x-if="event.type === 'yellow_card'">
                <span class="text-[10px] text-text-muted ml-1">{{ __('game.live_yellow_card') }}</span>
            </template>
            <template x-if="event.type === 'red_card'">
                <span class="text-[10px] text-accent-red ml-1" x-text="event.metadata?.second_yellow ? '{{ __('game.live_second_yellow') }}' : '{{ __('game.live_red_card') }}'"></span>
            </template>
            <template x-if="event.type === 'injury'">
                <span class="text-[10px] text-accent-orange ml-1">{{ __('game.live_injury') }}</span>
            </template>
            <template x-if="event.type === 'substitution'">
                <div class="text-[10px] text-text-secondary"><span class="text-[10px] text-accent-red font-semibold">OFF</span> <span x-text="event.playerName"></span></div>
            </template>
            <template x-if="event.assistPlayerName">
                <div class="text-[10px] text-text-secondary" x-text="'{{ __('game.live_assist') }} ' + event.assistPlayerName"></div>
            </template>
        </div>
    </div>
</template>
