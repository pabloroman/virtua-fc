@props([
    'player',
    'game',
    'showTeam' => false,
    'isOwnTeam' => false,
])

@php
/** @var App\Models\GamePlayer $player */
/** @var App\Models\Game $game */
$canOffer = !$isOwnTeam && $player->team_id !== null;
$isFreeAgent = $player->team_id === null;
$canNegotiateFreeAgent = $isFreeAgent && !$isOwnTeam;
@endphp

<tr class="border-b border-border-default transition-colors hover:bg-[rgba(59,130,246,0.05)]">
    {{-- Position badge --}}
    <td class="py-2 pl-4">
        <x-position-badge :position="$player->position" size="sm" />
    </td>
    {{-- Name + nationality + mobile details --}}
    <td class="py-2 pr-3">
        <div class="flex items-center gap-2">
            @if($player->nationality_flag['code'] ?? null)
            <img src="{{ Storage::disk('assets')->url('flags/' . $player->nationality_flag['code'] . '.svg') }}" class="w-4 h-3 rounded-xs shadow-xs shrink-0" title="{{ $player->nationality_flag['name'] }}">
            @endif
            <span class="font-medium text-text-primary truncate">{{ $player->name }}</span>
            @if(!$showTeam && $player->is_loaned_in)
            <span class="text-[10px] bg-violet-500/10 text-violet-400 px-1.5 py-0.5 rounded-sm font-medium shrink-0">{{ __('transfers.loans') }}</span>
            @endif
        </div>
        {{-- Mobile-only details --}}
        <div class="md:hidden text-xs text-text-muted mt-0.5 flex items-center gap-1 flex-wrap">
            @if($showTeam)
                @if($player->team)
                    <span class="truncate">{{ $player->team->name }}</span>
                    <span>&middot;</span>
                @else
                    <span>{{ __('transfers.free_agent') }}</span>
                    <span>&middot;</span>
                @endif
            @endif
            <span>{{ $player->age($game->current_date) }} {{ __('app.years') }}</span>
            <span>&middot;</span>
            <span>{{ \App\Support\Money::format($player->market_value_cents) }}</span>
            @if(!$showTeam)
                <span>&middot;</span>
                <span>{{ $player->contract_until?->year ?? '—' }}</span>
            @endif
        </div>
    </td>
    @if($showTeam)
    {{-- Team --}}
    <td class="py-2 pr-3 hidden md:table-cell">
        @if($player->team)
            <div class="flex items-center gap-2">
                <img src="{{ $player->team->image }}" alt="{{ $player->team->name }}" class="w-5 h-5 shrink-0 object-contain">
                <span class="text-text-secondary truncate">{{ $player->team->name }}</span>
            </div>
        @else
            <span class="text-text-muted">{{ __('transfers.free_agent') }}</span>
        @endif
    </td>
    @endif
    {{-- Age --}}
    <td class="py-2 pr-3 hidden md:table-cell text-center text-text-secondary tabular-nums">{{ $player->age($game->current_date) }}</td>
    {{-- Market value --}}
    <td class="py-2 pr-3 hidden md:table-cell text-text-secondary tabular-nums">{{ \App\Support\Money::format($player->market_value_cents) }}</td>
    @if(!$showTeam)
    {{-- Contract --}}
    <td class="py-2 pr-3 hidden md:table-cell text-center text-text-muted tabular-nums">{{ $player->contract_until?->year ?? '—' }}</td>
    @endif
    {{-- Offer button --}}
    <td class="py-2 pr-1 text-center">
        @if($canOffer)
            @php
                $posDisp = $player->position_display;
                $offerPayload = \Illuminate\Support\Js::from([
                    'playerName' => $player->name,
                    'negotiateUrl' => route('game.negotiate.transfer', [$game->id, $player->id]),
                    'mode' => 'transfer_fee',
                    'phase' => 'club_fee',
                    'chatTitle' => __('transfers.chat_transfer_title'),
                    'playerInfo' => [
                        'age' => $player->age($game->current_date),
                        'position' => $posDisp['abbreviation'],
                        'positionBg' => $posDisp['bg'],
                        'positionText' => $posDisp['text'],
                        'marketValue' => \App\Support\Money::format($player->market_value_cents),
                        'contractYear' => $player->contract_until?->year,
                    ],
                ]);
            @endphp
            <x-icon-button
                x-data
                x-on:click.prevent="$dispatch('open-negotiation', {{ $offerPayload }})"
                class="rounded-full text-text-body hover:text-accent-blue"
                title="{{ __('transfers.explore_make_offer') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </x-icon-button>
        @elseif($canNegotiateFreeAgent)
            @php
                $posDisp = $player->position_display;
                $freeAgentPayload = \Illuminate\Support\Js::from([
                    'playerName' => $player->name,
                    'negotiateUrl' => route('game.negotiate.free-agent', [$game->id, $player->id]),
                    'mode' => 'free_agent',
                    'phase' => 'personal_terms',
                    'chatTitle' => __('transfers.chat_free_agent_title'),
                    'playerInfo' => [
                        'age' => $player->age($game->current_date),
                        'position' => $posDisp['abbreviation'],
                        'positionBg' => $posDisp['bg'],
                        'positionText' => $posDisp['text'],
                        'marketValue' => \App\Support\Money::format($player->market_value_cents),
                    ],
                ]);
            @endphp
            <x-icon-button
                x-data
                x-on:click.prevent="$dispatch('open-negotiation', {{ $freeAgentPayload }})"
                class="rounded-full text-text-body hover:text-accent-green"
                title="{{ __('transfers.explore_negotiate') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </x-icon-button>
        @endif
    </td>
    {{-- Shortlist star --}}
    <td class="py-2 pr-4 text-center"
        x-data="{
            isShortlisted: {{ $player->is_shortlisted ? 'true' : 'false' }},
            async toggle() {
                try {
                    const response = await fetch('{{ route('game.scouting.shortlist.toggle', [$game->id, $player->id]) }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.isShortlisted = data.action === 'added';
                    } else if (data.message) {
                        alert(data.message);
                    }
                } catch (e) {}
            }
        }">
        <x-icon-button @click.prevent="toggle()"
                class="rounded-full"
                x-bind:class="isShortlisted ? 'text-accent-gold hover:text-amber-400' : 'text-text-body hover:text-accent-gold'"
                x-bind:title="isShortlisted ? {{ \Illuminate\Support\Js::from(__('transfers.remove_from_shortlist')) }} : {{ \Illuminate\Support\Js::from(__('transfers.add_to_shortlist')) }}">
            <svg class="w-5 h-5" :fill="isShortlisted ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
        </x-icon-button>
    </td>
</tr>
