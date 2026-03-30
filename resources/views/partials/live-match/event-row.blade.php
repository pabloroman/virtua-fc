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
      x-show="event.type === 'substitution' || event.type === 'substitution_group'">
    <svg class="w-3.5 h-3.5 text-accent-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
</span>
<img :src="getEventSide(event) === 'home' ? homeTeamImage : awayTeamImage"
     class="w-5 h-5 shrink-0 object-contain"
     :alt="getEventSide(event) === 'home' ? 'Home' : 'Away'">
<div class="flex-1 min-w-0">
    <template x-if="event.type !== 'substitution_group'">
        <div>
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
    </template>
    <template x-if="event.type === 'substitution_group'">
        <div class="flex flex-col gap-0.5">
            <template x-for="(sub, si) in event.substitutions" :key="si">
                <div class="flex items-baseline gap-1">
                    <span class="font-semibold text-xs text-text-primary" x-text="sub.playerInName"></span>
                    <span class="text-[10px] text-accent-red font-semibold">OFF</span>
                    <span class="text-[10px] text-text-secondary" x-text="sub.playerName"></span>
                </div>
            </template>
        </div>
    </template>
</div>
