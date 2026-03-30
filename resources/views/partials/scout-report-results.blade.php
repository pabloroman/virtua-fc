@php
    /** @var App\Models\Game $game */
    /** @var App\Models\ScoutReport $report */
    /** @var \Illuminate\Support\Collection $players */
    /** @var array $playerDetails */
@endphp

<div class="p-4 md:p-6">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 pb-4 border-b border-border-strong">
        <div>
            <h3 class="font-semibold text-lg text-text-primary">{{ __('transfers.scout_results') }}</h3>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-sm text-text-muted">
                <span><span class="font-medium text-text-body">{{ $positionLabel }}</span></span>
                <span class="text-text-body">&middot;</span>
                <span>{{ $scopeLabel }}</span>
                <span class="text-text-body">&middot;</span>
                <span>{{ __('transfers.results_count', ['count' => $players->count()]) }}</span>
            </div>
        </div>
        <x-icon-button size="sm" onclick="window.dispatchEvent(new CustomEvent('close-modal', {detail: 'scout-results'}))">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </x-icon-button>
    </div>

    {{-- Players List --}}
    @if($players->isEmpty())
        <div class="text-center py-10 text-text-secondary">
            <svg class="w-10 h-10 mx-auto mb-2 text-text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <p class="font-medium">{{ __('transfers.no_players_found') }}</p>
            <p class="text-sm mt-1">{{ __('transfers.try_broadening') }}</p>
        </div>
    @else
        <div class="divide-y divide-border-default -mx-4 md:-mx-6">
            @foreach($players as $player)
                @php
                    $detail = $playerDetails[$player->id] ?? null;
                    $isFreeAgent = $detail['is_free_agent'] ?? false;
                    $hasOffer = $detail['has_existing_offer'] ?? false;
                    $offerStatus = $detail['offer_status'] ?? null;
                    $offerIsCounter = $detail['offer_is_counter'] ?? false;
                    $askingPrice = $detail['asking_price'] ?? 0;
                    $formattedAskingPrice = $detail['formatted_asking_price'] ?? '-';
                    $wageDemand = $detail['wage_demand'] ?? 0;
                    $formattedWageDemand = $detail['formatted_wage_demand'] ?? '-';
                    $canAffordFee = $detail['can_afford_fee'] ?? false;
                    $canAffordLoan = $detail['can_afford_loan'] ?? false;
                    $availableBudget = (int) (($detail['available_budget'] ?? 0) / 100);
                    $onCooldown = $detail['on_cooldown'] ?? false;
                    $techRange = $detail['tech_range'] ?? [0, 0];
                    $physRange = $detail['phys_range'] ?? [0, 0];
                    $isExpiring = !$isFreeAgent && $player->contract_until && $player->contract_until <= $game->getSeasonEndDate();
                    $isShortlisted = in_array($player->id, $shortlistedPlayerIds ?? []);
                @endphp
                <div class="px-4 md:px-6 py-4" x-data="{ expanded: false, shortlisted: {{ $isShortlisted ? 'true' : 'false' }}, toggling: false }" @shortlist-toggled.window="if($event.detail.playerId === '{{ $player->id }}') { shortlisted = $event.detail.action === 'added' }">
                    {{-- Player Summary Row --}}
                    <div class="flex items-center gap-3 cursor-pointer" @click="expanded = !expanded">
                        {{-- Position + Name --}}
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <x-position-badge :position="$player->position" />
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-text-primary truncate">{{ $player->name }}</span>
                                    <span class="text-xs text-text-secondary">{{ $player->age($game->current_date) }} {{ __('app.years') }}</span>
                                    @if($isFreeAgent)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-sm text-[10px] font-medium bg-accent-green/10 text-accent-green">{{ __('transfers.free_agent') }}</span>
                                    @elseif($isExpiring)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-sm text-[10px] font-medium bg-accent-gold/10 text-accent-gold">{{ __('transfers.expiring_contract') }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 text-xs text-text-muted mt-0.5">
                                    @if($player->team)
                                        <x-team-crest :team="$player->team" class="w-4 h-4 shrink-0" />
                                        <span class="truncate">{{ $player->team->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Ability estimate + Price + Shortlist --}}
                        <div class="flex items-center gap-3 sm:gap-4 shrink-0">
                            <div class="text-right">
                                <div class="text-xs text-text-secondary">{{ __('transfers.ability') }}</div>
                                <div class="text-sm font-semibold text-text-body tabular-nums">{{ $techRange[0] }}-{{ $techRange[1] }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-text-secondary">{{ __('transfers.asking_price') }}</div>
                                <div class="text-sm font-semibold {{ $canAffordFee ? 'text-text-primary' : 'text-accent-red' }}">{{ $formattedAskingPrice }}</div>
                            </div>
                            {{-- Shortlist toggle --}}
                            <x-icon-button
                                @click.stop="if(toggling) return; toggling = true; fetch('{{ route('game.scouting.shortlist.toggle', [$game->id, $player->id]) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ source: 'scout_report' }) }).then(r => r.json()).then(data => { if(data.success === false) { alert(data.message); toggling = false; return; } shortlisted = !shortlisted; toggling = false; window.dispatchEvent(new CustomEvent('shortlist-toggled', { detail: { action: data.action, playerId: data.playerId, player: data.player || null } })); }).catch(() => { toggling = false; })"
                                class="sm:min-h-0"
                                x-bind:class="shortlisted ? 'text-accent-gold hover:text-amber-400' : 'text-text-body hover:text-accent-gold'"
                                x-bind:title="shortlisted ? {{ \Illuminate\Support\Js::from(__('transfers.remove_from_shortlist')) }} : {{ \Illuminate\Support\Js::from(__('transfers.add_to_shortlist')) }}"
                            >
                                <svg class="w-5 h-5" x-bind:fill="shortlisted ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                            </x-icon-button>
                            <svg class="w-4 h-4 text-text-secondary transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Expanded Detail --}}
                    <div x-show="expanded" x-cloak class="mt-4"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1">
                        <div class="bg-surface-700/50 rounded-lg p-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {{-- Left: Scouting Assessment --}}
                                <div>
                                    <h5 class="text-xs font-semibold text-text-muted uppercase tracking-wide mb-3">{{ __('transfers.scouting_assessment') }}</h5>
                                    <div class="space-y-2.5">
                                        {{-- Technical --}}
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-xs text-text-muted w-16 shrink-0">{{ __('transfers.technical') }}</span>
                                            <x-ability-bar :range="$techRange" size="sm" class="text-xs font-semibold tabular-nums text-text-body" />
                                        </div>
                                        {{-- Physical --}}
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-xs text-text-muted w-16 shrink-0">{{ __('transfers.physical') }}</span>
                                            <x-ability-bar :range="$physRange" size="sm" class="text-xs font-semibold tabular-nums text-text-body" />
                                        </div>
                                        {{-- Market Value --}}
                                        <div class="flex items-center justify-between pt-1">
                                            <span class="text-xs text-text-muted">{{ __('transfers.market_value') }}</span>
                                            <span class="text-xs font-semibold text-text-body">{{ $player->formatted_market_value }}</span>
                                        </div>
                                        {{-- Contract --}}
                                        @if(!$isFreeAgent && $player->contract_until)
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs text-text-muted">{{ __('transfers.contract_until') }}</span>
                                                <span class="text-xs font-semibold {{ $isExpiring ? 'text-accent-gold' : 'text-text-body' }}">{{ $player->contract_until->format('M Y') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Right: Financial Details --}}
                                <div>
                                    <h5 class="text-xs font-semibold text-text-muted uppercase tracking-wide mb-3">{{ __('transfers.financial_details') }}</h5>
                                    <div class="space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs text-text-muted">{{ __('transfers.estimated_asking_price') }}</span>
                                            <span class="text-xs font-semibold {{ $canAffordFee ? 'text-text-primary' : 'text-accent-red' }}">{{ $formattedAskingPrice }}</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs text-text-muted">{{ __('transfers.wage_demand') }}</span>
                                            <span class="text-xs font-semibold text-text-body">{{ $formattedWageDemand }}{{ __('squad.per_year') }}</span>
                                        </div>
                                        <div class="flex items-center justify-between pt-1 border-t border-border-strong">
                                            <span class="text-xs text-text-muted">{{ __('transfers.your_transfer_budget') }}</span>
                                            <span class="text-xs font-semibold text-text-body">{{ $detail['formatted_transfer_budget'] ?? '-' }}</span>
                                        </div>
                                    </div>

                                    {{-- Action Buttons --}}
                                    <div class="mt-4 space-y-2">
                                        @if($isFreeAgent)
                                                <form method="POST" action="{{ route('game.scouting.sign-free-agent', [$game->id, $player->id]) }}">
                                                    @csrf
                                                    <x-primary-button color="green" size="xs">
                                                        {{ __('transfers.sign_free_agent') }}
                                                    </x-primary-button>
                                                </form>
                                        @elseif($hasOffer && $offerStatus === 'pending' && !$offerIsCounter)
                                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-accent-gold/10 text-accent-gold border border-accent-gold/20">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                {{ __('transfers.bid_awaiting_response') }}
                                            </div>
                                        @elseif($hasOffer && $offerStatus === 'pending' && $offerIsCounter)
                                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-accent-blue/10 text-blue-400 border border-accent-blue/20">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                                {{ __('transfers.counter_offer_received') }}
                                            </div>
                                        @elseif($hasOffer && $offerStatus === 'agreed')
                                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-accent-green/10 text-accent-green border border-accent-green/20">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                {{ __('transfers.transfer_agreed') }}
                                            </div>
                                        @elseif($onCooldown)
                                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-surface-700 text-text-muted border border-border-default">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                {{ __('transfers.negotiation_cooldown_short') }}
                                            </div>
                                        @elseif($isExpiring && $isPreContractPeriod)
                                            {{-- Pre-contract negotiation --}}
                                            @php
                                                $posDisp = $player->position_display;
                                                $scoutPlayerInfo = \Illuminate\Support\Js::from([
                                                    'age' => $player->age($game->current_date),
                                                    'position' => $posDisp['abbreviation'],
                                                    'positionBg' => $posDisp['bg'],
                                                    'positionText' => $posDisp['text'],
                                                    'marketValue' => $player->formatted_market_value,
                                                    'contractYear' => $player->contract_expiry_year,
                                                ]);
                                            @endphp
                                            <x-primary-button size="xs" color="green"
                                                @click="$dispatch('open-negotiation', {
                                                    playerName: {{ \Illuminate\Support\Js::from($player->name) }},
                                                    negotiateUrl: {{ \Illuminate\Support\Js::from(route('game.negotiate.pre-contract', [$game->id, $player->id])) }},
                                                    mode: 'pre_contract',
                                                    phase: 'personal_terms',
                                                    chatTitle: {{ \Illuminate\Support\Js::from(__('transfers.chat_pre_contract_title')) }},
                                                    playerInfo: {{ $scoutPlayerInfo }}
                                                })">
                                                {{ __('transfers.negotiate_pre_contract') }}
                                            </x-primary-button>
                                        @elseif($availableBudget <= 0 && !$canAffordLoan)
                                            <div>
                                                <div class="text-xs text-accent-red font-medium">
                                                    {{ __('transfers.loan_fee_exceeds_budget') }}
                                                </div>
                                                <div class="text-xs text-text-muted mt-1">
                                                    {{ __('transfers.loan_cost_salary') }}: <span class="text-text-body font-medium">{{ $formattedWageDemand }}{{ __('squad.per_year') }}</span>
                                                </div>
                                            </div>
                                        @else
                                            @php
                                                $posDisp = $player->position_display;
                                                $scoutPlayerInfo = \Illuminate\Support\Js::from([
                                                    'age' => $player->age($game->current_date),
                                                    'position' => $posDisp['abbreviation'],
                                                    'positionBg' => $posDisp['bg'],
                                                    'positionText' => $posDisp['text'],
                                                    'marketValue' => $player->formatted_market_value,
                                                    'contractYear' => $player->contract_expiry_year,
                                                ]);
                                            @endphp
                                            <div class="flex flex-col gap-2">
                                                @if(!$canAffordFee)
                                                    <div class="text-xs text-accent-gold font-medium">
                                                        {{ __('transfers.budget_limited_hint') }}
                                                    </div>
                                                @endif
                                                <div class="flex flex-col sm:flex-row gap-2">
                                                    @if($availableBudget > 0)
                                                        {{-- Transfer Negotiate --}}
                                                        <x-primary-button size="xs"
                                                            @click="$dispatch('open-negotiation', {
                                                                playerName: {{ \Illuminate\Support\Js::from($player->name) }},
                                                                negotiateUrl: {{ \Illuminate\Support\Js::from(route('game.negotiate.transfer', [$game->id, $player->id])) }},
                                                                mode: 'transfer_fee',
                                                                phase: 'club_fee',
                                                                chatTitle: {{ \Illuminate\Support\Js::from(__('transfers.chat_transfer_title')) }},
                                                                playerInfo: {{ $scoutPlayerInfo }}
                                                            })">
                                                            {{ __('transfers.negotiate') }}
                                                        </x-primary-button>
                                                    @endif
                                                    {{-- Loan Request --}}
                                                    <x-secondary-button size="xs"
                                                        @click="$dispatch('open-negotiation', {
                                                            playerName: {{ \Illuminate\Support\Js::from($player->name) }},
                                                            negotiateUrl: {{ \Illuminate\Support\Js::from(route('game.negotiate.loan', [$game->id, $player->id])) }},
                                                            mode: 'loan',
                                                            phase: 'club_fee',
                                                            chatTitle: {{ \Illuminate\Support\Js::from(__('transfers.chat_loan_title')) }},
                                                            playerInfo: {{ $scoutPlayerInfo }}
                                                        })">
                                                        {{ __('transfers.request_loan') }}
                                                    </x-secondary-button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
