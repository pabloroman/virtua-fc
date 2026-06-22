{{--
    A single scouting target (shortlisted player) row — the Alpine-rendered twin of
    <x-explore-player-row>, so the shortlist table reads identically to the search-results
    table below it (position · name + flag · age · OVR · value · asking), with a chevron
    affordance in the trailing cell.

    It can't reuse <x-explore-player-row> directly: that component is a server-rendered
    <tr> bound to a GamePlayer/Game model, whereas the shortlist is a live reactive list
    rendered from plain JS payload objects (PlayerDossierPresenter::build), so star/un-star
    add and remove rows without a reload. Clicking opens the shared dossier modal, which
    carries the full deal status (agreed / negotiating / cooldown / on loan) and all actions.

    Pure markup with NO props — relies on being rendered inside the scouting hub's Alpine root
    inside a `<template x-for="player in …">`, so `player` and the root's `openDetail()` resolve
    at runtime.
--}}
<tr class="border-b border-border-default transition-colors hover:bg-[rgba(59,130,246,0.05)] cursor-pointer" @click="openDetail(player)">
    {{-- Position badge (primary position) --}}
    <td class="py-2 pl-4">
        <template x-if="player.positions && player.positions.length">
            <span :class="'inline-flex items-center justify-center w-5 h-5 text-[8px] -skew-x-12 font-semibold ' + player.positions[0].bg + ' ' + player.positions[0].text">
                <span class="skew-x-12" x-text="player.positions[0].abbreviation"></span>
            </span>
        </template>
    </td>
    {{-- Name + nationality + inline team / mobile details --}}
    <td class="py-2 pr-3">
        <div class="flex items-center gap-2 min-w-0">
            <template x-if="player.nationalityFlag">
                <img :src="player.nationalityFlag.url" :title="player.nationalityFlag.name" class="w-4 h-3 rounded-xs shadow-xs shrink-0">
            </template>
            <span class="font-medium text-text-primary truncate" x-text="player.name"></span>
            <template x-if="!player.isFreeAgent && player.teamName">
                <span class="hidden md:inline-flex items-center gap-1 text-xs text-text-muted min-w-0 shrink">
                    <span class="text-text-muted/60">&middot;</span>
                    <template x-if="player.teamImage">
                        <img :src="player.teamImage" :alt="player.teamName" class="w-4 h-4 shrink-0 object-contain">
                    </template>
                    <span class="truncate" x-text="player.teamName"></span>
                </span>
            </template>
            <template x-if="player.isFreeAgent">
                <span class="hidden md:inline-flex items-center gap-1 text-xs text-text-muted min-w-0 shrink">
                    <span class="text-text-muted/60">&middot;</span>
                    <span class="truncate">{{ __('transfers.free_agent') }}</span>
                </span>
            </template>
            <template x-if="player.isOnLoan">
                <span class="text-[10px] bg-violet-500/10 text-violet-400 px-1.5 py-0.5 rounded-sm font-medium shrink-0">{{ __('transfers.loaned') }}</span>
            </template>
        </div>
        {{-- Mobile-only details (desktop columns are hidden below md) --}}
        <div class="md:hidden text-xs text-text-muted mt-0.5 flex items-center gap-1 flex-wrap">
            <template x-if="!player.isFreeAgent && player.teamName">
                <span class="flex items-center gap-1">
                    <span class="truncate" x-text="player.teamName"></span>
                    <span>&middot;</span>
                </span>
            </template>
            <template x-if="player.isFreeAgent">
                <span class="flex items-center gap-1">
                    <span>{{ __('transfers.free_agent') }}</span>
                    <span>&middot;</span>
                </span>
            </template>
            <span x-text="player.age + ' {{ __('app.years') }}'"></span>
            <span>&middot;</span>
            <span x-text="player.marketReferenceValue"></span>
            <span>&middot;</span>
            <span x-text="'OVR ' + player.ovr"></span>
            <template x-if="player.isFreeAgent">
                <span class="flex items-center gap-1">
                    <span>&middot;</span>
                    <span class="font-medium text-accent-green">{{ __('transfers.market_free') }}</span>
                </span>
            </template>
            <template x-if="!player.isFreeAgent && player.formattedAskingPrice">
                <span class="flex items-center gap-1">
                    <span>&middot;</span>
                    <span class="font-medium text-text-primary" x-text="player.formattedAskingPrice"></span>
                </span>
            </template>
        </div>
    </td>
    {{-- Age --}}
    <td class="py-2 pr-3 hidden md:table-cell text-center text-text-secondary tabular-nums" x-text="player.age"></td>
    {{-- OVR --}}
    <td class="py-2 pr-3 hidden md:table-cell text-center">
        <div class="flex justify-center">
            <div class="rating-badge w-7 h-7 rounded-md text-xs flex items-center justify-center" :class="player.ovrClass">
                <span class="font-heading font-bold" x-text="player.ovr"></span>
            </div>
        </div>
    </td>
    {{-- Market value (release clause where mandatory — already resolved server-side) --}}
    <td class="py-2 pr-3 hidden md:table-cell text-text-secondary tabular-nums" x-text="player.marketReferenceValue"></td>
    {{-- Asking price --}}
    <td class="py-2 pr-3 hidden md:table-cell text-right tabular-nums">
        <template x-if="!player.isFreeAgent && player.formattedAskingPrice">
            <span class="font-semibold text-text-primary" x-text="player.formattedAskingPrice"></span>
        </template>
        <template x-if="player.isFreeAgent">
            <span class="font-semibold text-accent-green">{{ __('transfers.market_free') }}</span>
        </template>
    </td>
    {{-- Open affordance (full deal status lives in the dossier modal) --}}
    <td class="py-2 pr-4">
        <div class="flex items-center justify-end">
            <svg class="w-4 h-4 shrink-0 text-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </div>
    </td>
</tr>
