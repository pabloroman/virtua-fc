@props([
    'game',
    'tier' => 0,
    'canSearchInternationally' => false,
])

{{--
    Always-on scouting operations strip: search scope + an "expand network"
    link to the club investment page. Permanent context for what your scouting
    department can reach and how to widen it.
--}}
<div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2.5 rounded-xl border border-border-default bg-surface-800">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 min-w-0">
        <span class="flex items-center gap-2 text-sm font-semibold text-text-primary">
            <svg class="w-4 h-4 text-text-secondary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            {{ __('transfers.ops_title') }}
        </span>

        {{-- Search scope --}}
        <span class="flex items-center gap-1.5 text-xs text-text-secondary">
            <svg class="w-3.5 h-3.5 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18M12 3a9 9 0 100 18 9 9 0 000-18z"/>
            </svg>
            {{ $canSearchInternationally ? __('transfers.ops_scope_international') : __('transfers.ops_scope_domestic') }}
        </span>
    </div>

    @if($tier < 4)
        <x-secondary-button-link :href="route('game.club.investment', $game->id)" size="sm">
            {{ __('transfers.ops_expand_network') }}
        </x-secondary-button-link>
    @endif
</div>
