@props([
    'player',
    'game',
    'showTeam' => false,
    'isOwnTeam' => false,
    'showOvr' => false,
    'showContract' => null,
    'askingPrice' => null,
    'showAskingPrice' => null,
    'teamPlacement' => 'column', // 'column' | 'inline'
])

@php
/** @var App\Models\GamePlayer $player */
/** @var App\Models\Game $game */
$isOnLoan = $player->is_on_loan ?? false;
// `is_user_owned` covers first-team, reserve-team, and loaned-out players the
// user's organisation actually owns. `$isOwnTeam` only knows about the first
// team, so it stays as the fallback for callers that haven't been migrated.
$isUserOwned = $player->is_user_owned ?? $isOwnTeam;
$userPreContractStatus = $player->user_pre_contract_status ?? null;
$hasUserPreContract = $userPreContractStatus !== null;
$canOffer = !$isUserOwned && !$hasUserPreContract && $player->team_id !== null && !$isOnLoan;
$isFreeAgent = $player->team_id === null;
$canNegotiateFreeAgent = $isFreeAgent && !$isUserOwned && !$hasUserPreContract;
// Release clause: pay the buyout to force a sale of an AI-owned, contracted,
// non-loaned player. Gated on the per-game feature flag and the player carrying
// a clause; the server re-checks every condition in TransferService.
$canPayClause = ($game->release_clauses_enabled ?? false)
    && !$isUserOwned
    && !$hasUserPreContract
    && !$isFreeAgent
    && !$isOnLoan
    && $player->hasReleaseClause();
// Default: contract column shown when the team column isn't rendered.
$showContract = $showContract ?? !$showTeam;
// When rendering alongside listings, the asking-price cell must always be
// rendered to keep column alignment stable — free agents show a placeholder.
$showAskingPrice = $showAskingPrice ?? ($askingPrice !== null);
@endphp

<tr class="border-b border-border-default transition-colors hover:bg-[rgba(59,130,246,0.05)]">
    {{-- Position badge --}}
    <td class="py-2 pl-4">
        <x-position-badge :position="$player->position" size="sm" />
    </td>
    {{-- Name + nationality + mobile details --}}
    <td class="py-2 pr-3">
        <div class="flex items-center gap-2 min-w-0">
            @if($player->nationality_flag['code'] ?? null)
            <img src="{{ Storage::disk('assets')->url('flags/' . $player->nationality_flag['code'] . '.svg') }}" class="w-4 h-3 rounded-xs shadow-xs shrink-0" title="{{ $player->nationality_flag['name'] }}">
            @endif
            <span class="font-medium text-text-primary truncate">{{ $player->name }}</span>
            @if($teamPlacement === 'inline' && $showTeam && $player->team)
            <span class="hidden md:inline-flex items-center gap-1 text-xs text-text-muted min-w-0 shrink">
                <span class="text-text-muted/60">&middot;</span>
                <img src="{{ $player->team->image }}" alt="{{ $player->team->name }}" class="w-4 h-4 shrink-0 object-contain">
                <span class="truncate">{{ $player->team->name }}</span>
            </span>
            @elseif($teamPlacement === 'inline' && $showTeam && $isFreeAgent)
            <span class="hidden md:inline-flex items-center gap-1 text-xs text-text-muted min-w-0 shrink">
                <span class="text-text-muted/60">&middot;</span>
                <span class="truncate">{{ __('transfers.free_agent') }}</span>
            </span>
            @endif
            @if($isOnLoan || (!$showTeam && $player->is_loaned_in))
            <span class="text-[10px] bg-violet-500/10 text-violet-400 px-1.5 py-0.5 rounded-sm font-medium shrink-0">{{ __('transfers.loaned') }}</span>
            @endif
            @if($hasUserPreContract)
                <x-pre-contract-badge :status="$userPreContractStatus" class="shrink-0" />
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
            <span><x-player-market-value :player="$player" :game="$game" /></span>
            @if($showOvr)
                <span>&middot;</span>
                <span>OVR {{ $player->effective_rating }}</span>
            @endif
            @if($showAskingPrice)
                <span>&middot;</span>
                @if($askingPrice !== null)
                    <span class="font-medium text-text-primary">{{ \App\Support\Money::format($askingPrice) }}</span>
                @else
                    <span class="font-medium text-accent-green">{{ __('transfers.market_free') }}</span>
                @endif
            @endif
            @if($showContract)
                <span>&middot;</span>
                <span>{{ $isFreeAgent ? '—' : ($player->contract_until?->year ?? '—') }}</span>
            @endif
        </div>
    </td>
    @if($showTeam && $teamPlacement === 'column')
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
    @if($showOvr)
    {{-- OVR --}}
    <td class="py-2 pr-3 hidden md:table-cell text-center">
        <div class="flex justify-center">
            <x-rating-badge :value="$player->effective_rating" size="sm" />
        </div>
    </td>
    @endif
    {{-- Market value (release clause where mandatory) --}}
    <td class="py-2 pr-3 hidden md:table-cell text-text-secondary tabular-nums"><x-player-market-value :player="$player" :game="$game" /></td>
    @if($showContract)
    {{-- Contract --}}
    <td class="py-2 pr-3 hidden md:table-cell text-text-secondary tabular-nums">{{ $isFreeAgent ? '—' : ($player->contract_until?->year ?? '—') }}</td>
    @endif
    @if($showAskingPrice)
    {{-- Asking price --}}
    <td class="py-2 pr-3 hidden md:table-cell text-right tabular-nums">
        @if($askingPrice !== null)
            <span class="font-semibold text-text-primary">{{ \App\Support\Money::format($askingPrice) }}</span>
        @else
            <span class="font-semibold text-accent-green">{{ __('transfers.market_free') }}</span>
        @endif
    </td>
    @endif
    {{-- Offer button --}}
    <td class="py-2 pr-1 text-center">
        <div class="flex items-center justify-center gap-1">
        @if($canOffer)
            @php
                $posDisp = $player->position_display;
                $offerPayload = \Illuminate\Support\Js::from([
                    'playerName' => $player->name,
                    'negotiateUrl' => route('game.negotiate.transfer', [$game->id, $player->id]),
                    'mode' => 'transfer_fee',
                    'phase' => 'club_fee',
                    'chatTitle' => __('transfers.chat_transfer_title'),
                    // Release clause folds into the negotiation modal: a formatted
                    // value for the info strip, plus a numeric euro cap for the slider
                    // (release_clause is stored in cents). The server re-checks the
                    // clause when an offer meets it, so these are display/UX only.
                    'playerInfo' => array_merge([
                        'age' => $player->age($game->current_date),
                        'position' => $posDisp['abbreviation'],
                        'positionBg' => $posDisp['bg'],
                        'positionText' => $posDisp['text'],
                        'marketValue' => \App\Support\Money::format($player->market_value_cents),
                        'contractYear' => $player->contract_until?->year,
                    ], $canPayClause ? [
                        'releaseClause' => \App\Support\Money::format($player->release_clause),
                        'releaseClauseEuros' => (int) ($player->release_clause / 100),
                    ] : []),
                ]);
            @endphp
            <x-icon-button
                size="sm"
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
                size="sm"
                x-data
                x-on:click.prevent="$dispatch('open-negotiation', {{ $freeAgentPayload }})"
                class="rounded-full text-text-body hover:text-accent-green"
                title="{{ __('transfers.explore_negotiate') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </x-icon-button>
        @endif
        </div>
    </td>
    {{-- Shortlist star (hidden for players the user already owns) --}}
    @if($isUserOwned)
    <td class="py-2 pr-4"></td>
    @elseif($hasUserPreContract)
    <td class="py-2 pr-4 text-center">
        <x-icon-button
            size="sm"
            class="rounded-full opacity-40 cursor-not-allowed"
            title="{{ __('transfers.shortlist_disabled_pre_contract') }}">
            <svg class="w-5 h-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
        </x-icon-button>
    </td>
    @else
    <td class="py-2 pr-4 text-center"
        x-data="{
            isShortlisted: {{ $player->is_shortlisted ? 'true' : 'false' }},
            inFlight: false,
            async toggle() {
                if (this.inFlight) return;
                this.inFlight = true;
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
                } catch (e) {} finally {
                    this.inFlight = false;
                }
            }
        }">
        <x-icon-button @click.prevent="toggle()"
                size="sm"
                class="rounded-full"
                x-bind:class="isShortlisted ? 'text-accent-gold hover:text-amber-400' : 'text-text-body hover:text-accent-gold'"
                x-bind:title="isShortlisted ? {{ \Illuminate\Support\Js::from(__('transfers.remove_from_shortlist')) }} : {{ \Illuminate\Support\Js::from(__('transfers.add_to_shortlist')) }}">
            <svg class="w-5 h-5" :fill="isShortlisted ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
        </x-icon-button>
    </td>
    @endif
</tr>
