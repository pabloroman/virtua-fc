@props(['game', 'currentCompetitionId', 'otherLeagues'])

@if($otherLeagues->isNotEmpty())
<div class="relative inline-block" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" @click="open = !open"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wider text-text-muted hover:text-text-body bg-surface-800 hover:bg-surface-700 border border-border-default transition-colors">
        {{ __('game.other_leagues') }}
        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 z-40 mt-2 w-72 rounded-lg shadow-xl bg-surface-800 border border-border-strong"
         style="display: none;">
        <div class="py-1">
            @foreach($otherLeagues as $league)
            <a href="{{ route('game.competition', [$game->id, $league->id]) }}"
               class="flex items-center gap-2.5 px-4 py-2 text-sm whitespace-nowrap {{ $league->id === $currentCompetitionId ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">
                @if($league->flag)
                    <img src="{{ Storage::disk('assets')->url('flags/' . $league->flag . '.svg') }}"
                         alt=""
                         class="w-5 h-4 rounded-sm shadow-sm shrink-0">
                @endif
                <span>{{ __($league->name) }}</span>
            </a>
            @endforeach
        </div>
    </div>
</div>
@endif
