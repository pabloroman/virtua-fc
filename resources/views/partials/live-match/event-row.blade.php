<span class="text-xs font-mono text-slate-400 w-8 text-right shrink-0"
      x-text="event.minute + '\''"></span>
<span class="text-sm w-6 text-center shrink-0" x-text="getEventIcon(event.type)"></span>
<img :src="getEventSide(event) === 'home' ? homeTeamImage : awayTeamImage"
     class="w-6 h-6 shrink-0 object-contain"
     :alt="getEventSide(event) === 'home' ? 'Home' : 'Away'">
<div class="flex-1 min-w-0">
    <span class="font-semibold text-sm text-slate-800" x-text="event.type === 'substitution' ? event.playerInName : event.playerName"></span>
    <template x-if="event.type === 'goal'">
        <span class="text-xs text-slate-500 ml-1">{{ __('game.live_goal') }}</span>
    </template>
    <template x-if="event.type === 'own_goal'">
        <span class="text-xs text-red-500 ml-1">({{ __('game.og') }})</span>
    </template>
    <template x-if="event.type === 'yellow_card'">
        <span class="text-xs text-slate-500 ml-1">{{ __('game.live_yellow_card') }}</span>
    </template>
    <template x-if="event.type === 'red_card'">
        <span class="text-xs text-red-600 ml-1" x-text="event.metadata?.second_yellow ? '{{ __('game.live_second_yellow') }}' : '{{ __('game.live_red_card') }}'"></span>
    </template>
    <template x-if="event.type === 'injury'">
        <span class="text-xs text-orange-600 ml-1">{{ __('game.live_injury') }}</span>
    </template>
    <template x-if="event.type === 'substitution'">
        <div class="text-xs text-slate-400" x-text="'&#8617; ' + event.playerName"></div>
    </template>
    <template x-if="event.assistPlayerName">
        <div class="text-xs text-slate-400" x-text="'{{ __('game.live_assist') }} ' + event.assistPlayerName"></div>
    </template>
</div>
