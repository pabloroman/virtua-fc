@php
    /** @var App\Models\Game $game */
    /** @var App\Models\ScoutReport $report */
    /** @var \Illuminate\Support\Collection $players */
    /** @var array $playerDetails */
@endphp

<div class="p-4 md:p-6">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 pb-4 border-b border-slate-200">
        <div>
            <h3 class="font-semibold text-lg text-slate-900">{{ __('transfers.scout_results') }}</h3>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-sm text-slate-500">
                <span><span class="font-medium text-slate-700">{{ $positionLabel }}</span></span>
                <span class="text-slate-300">&middot;</span>
                <span>{{ $scopeLabel }}</span>
                <span class="text-slate-300">&middot;</span>
                <span>{{ __('transfers.results_count', ['count' => $players->count()]) }}</span>
            </div>
        </div>
        <button onclick="window.dispatchEvent(new CustomEvent('close-modal', {detail: 'scout-results'}))" class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-100 shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Players List --}}
    @if($players->isEmpty())
        <div class="text-center py-10 text-slate-400">
            <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <p class="font-medium">{{ __('transfers.no_players_found') }}</p>
            <p class="text-sm mt-1">{{ __('transfers.try_broadening') }}</p>
        </div>
    @else
        <div class="divide-y divide-slate-100 -mx-4 md:-mx-6">
            @foreach($players as $player)
                @php
                    $detail = $playerDetails[$player->id] ?? null;
                    $hasOffer = $detail['has_existing_offer'] ?? false;
                    $offerStatus = $detail['offer_status'] ?? null;
                    $offerIsCounter = $detail['offer_is_counter'] ?? false;
                    $askingPrice = $detail['asking_price'] ?? 0;
                    $formattedAskingPrice = $detail['formatted_asking_price'] ?? '-';
                    $wageDemand = $detail['wage_demand'] ?? 0;
                    $formattedWageDemand = $detail['formatted_wage_demand'] ?? '-';
                    $canAffordFee = $detail['can_afford_fee'] ?? false;
                    $canAffordWage = $detail['can_afford_wage'] ?? false;
                    $techRange = $detail['tech_range'] ?? [0, 0];
                    $physRange = $detail['phys_range'] ?? [0, 0];
                    $isExpiring = $player->contract_until && $player->contract_until <= $game->getSeasonEndDate();
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
                                    <span class="font-semibold text-slate-900 truncate">{{ $player->name }}</span>
                                    <span class="text-xs text-slate-400">{{ $player->age }} {{ __('app.years') }}</span>
                                    @if($isExpiring)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700">{{ __('transfers.expiring_contract') }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 text-xs text-slate-500 mt-0.5">
                                    @if($player->team)
                                        <x-team-crest :team="$player->team" class="w-4 h-4 shrink-0" />
                                        <span class="truncate">{{ $player->team->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Ability estimate + Price + Shortlist --}}
                        <div class="flex items-center gap-3 sm:gap-4 shrink-0">
                            <div class="text-right hidden sm:block">
                                <div class="text-xs text-slate-400">{{ __('transfers.ability') }}</div>
                                <div class="text-sm font-semibold text-slate-700 tabular-nums">{{ $techRange[0] }}-{{ $techRange[1] }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-slate-400">{{ __('transfers.asking_price') }}</div>
                                <div class="text-sm font-semibold {{ $canAffordFee ? 'text-slate-900' : 'text-red-600' }}">{{ $formattedAskingPrice }}</div>
                            </div>
                            {{-- Shortlist toggle --}}
                            <button
                                @click.stop="if(toggling) return; toggling = true; fetch('{{ route('game.scouting.shortlist.toggle', [$game->id, $player->id]) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }).then(r => r.json()).then(data => { shortlisted = !shortlisted; toggling = false; window.dispatchEvent(new CustomEvent('shortlist-toggled', { detail: { action: data.action, playerId: data.playerId, player: data.player || null } })); }).catch(() => { toggling = false; })"
                                class="p-1.5 rounded transition-colors min-h-[44px] sm:min-h-0"
                                :class="shortlisted ? 'text-amber-500 hover:text-amber-600' : 'text-slate-300 hover:text-amber-400'"
                                :title="shortlisted ? '{{ __('transfers.remove_from_shortlist') }}' : '{{ __('transfers.add_to_shortlist') }}'"
                            >
                                <svg class="w-5 h-5" :fill="shortlisted ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                            </button>
                            <svg class="w-4 h-4 text-slate-400 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <div class="bg-slate-50 rounded-lg p-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {{-- Left: Scouting Assessment --}}
                                <div>
                                    <h5 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ __('transfers.scouting_assessment') }}</h5>
                                    <div class="space-y-2.5">
                                        {{-- Technical --}}
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-xs text-slate-500 w-16 shrink-0">{{ __('transfers.technical') }}</span>
                                            <div class="flex items-center gap-2 flex-1 justify-end">
                                                <div class="w-20 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                                    @php $midTech = ($techRange[0] + $techRange[1]) / 2; @endphp
                                                    <div class="h-1.5 rounded-full {{ $midTech >= 80 ? 'bg-emerald-500' : ($midTech >= 70 ? 'bg-lime-500' : ($midTech >= 60 ? 'bg-amber-500' : 'bg-slate-400')) }}" style="width: {{ $midTech / 99 * 100 }}%"></div>
                                                </div>
                                                <span class="text-xs font-semibold tabular-nums text-slate-700">{{ $techRange[0] }}-{{ $techRange[1] }}</span>
                                            </div>
                                        </div>
                                        {{-- Physical --}}
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-xs text-slate-500 w-16 shrink-0">{{ __('transfers.physical') }}</span>
                                            <div class="flex items-center gap-2 flex-1 justify-end">
                                                <div class="w-20 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                                    @php $midPhys = ($physRange[0] + $physRange[1]) / 2; @endphp
                                                    <div class="h-1.5 rounded-full {{ $midPhys >= 80 ? 'bg-emerald-500' : ($midPhys >= 70 ? 'bg-lime-500' : ($midPhys >= 60 ? 'bg-amber-500' : 'bg-slate-400')) }}" style="width: {{ $midPhys / 99 * 100 }}%"></div>
                                                </div>
                                                <span class="text-xs font-semibold tabular-nums text-slate-700">{{ $physRange[0] }}-{{ $physRange[1] }}</span>
                                            </div>
                                        </div>
                                        {{-- Market Value --}}
                                        <div class="flex items-center justify-between pt-1">
                                            <span class="text-xs text-slate-500">{{ __('transfers.market_value') }}</span>
                                            <span class="text-xs font-semibold text-slate-700">{{ $player->formatted_market_value }}</span>
                                        </div>
                                        {{-- Contract --}}
                                        @if($player->contract_until)
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs text-slate-500">{{ __('transfers.contract_until') }}</span>
                                                <span class="text-xs font-semibold {{ $isExpiring ? 'text-amber-600' : 'text-slate-700' }}">{{ $player->contract_until->format('M Y') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Right: Financial Details --}}
                                <div>
                                    <h5 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ __('transfers.financial_details') }}</h5>
                                    <div class="space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs text-slate-500">{{ __('transfers.estimated_asking_price') }}</span>
                                            <span class="text-xs font-semibold {{ $canAffordFee ? 'text-slate-900' : 'text-red-600' }}">{{ $formattedAskingPrice }}</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs text-slate-500">{{ __('transfers.wage_demand') }}</span>
                                            <span class="text-xs font-semibold {{ $canAffordWage ? 'text-slate-900' : 'text-amber-600' }}">{{ $formattedWageDemand }}{{ __('squad.per_year') }}</span>
                                        </div>
                                        <div class="flex items-center justify-between pt-1 border-t border-slate-200">
                                            <span class="text-xs text-slate-500">{{ __('transfers.your_transfer_budget') }}</span>
                                            <span class="text-xs font-semibold text-slate-700">{{ $detail['formatted_transfer_budget'] ?? '-' }}</span>
                                        </div>
                                    </div>

                                    {{-- Action Buttons --}}
                                    <div class="mt-4 space-y-2">
                                        @if($hasOffer && $offerStatus === 'pending' && !$offerIsCounter)
                                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-amber-50 text-amber-700 border border-amber-200">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                {{ __('transfers.bid_awaiting_response') }}
                                            </div>
                                        @elseif($hasOffer && $offerStatus === 'pending' && $offerIsCounter)
                                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-50 text-blue-700 border border-blue-200">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                                {{ __('transfers.counter_offer_received') }}
                                            </div>
                                        @elseif($hasOffer && $offerStatus === 'agreed')
                                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-green-50 text-green-700 border border-green-200">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                {{ __('transfers.transfer_agreed') }}
                                            </div>
                                        @elseif($isExpiring && $isPreContractPeriod)
                                            {{-- Pre-contract offer form --}}
                                            <form method="POST" action="{{ route('game.scouting.pre-contract', [$game->id, $player->id]) }}" class="space-y-2">
                                                @csrf
                                                <label class="block text-xs font-medium text-slate-600">{{ __('transfers.offered_wage_euros') }}</label>
                                                <div class="flex items-center gap-2">
                                                    <x-money-input name="offered_wage" :value="(int)($wageDemand / 100)" :min="0" size="sm" />
                                                    <button type="submit" class="inline-flex items-center justify-center px-3 py-1.5 min-h-[36px] bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition-colors whitespace-nowrap">
                                                        {{ __('transfers.submit_pre_contract') }}
                                                    </button>
                                                </div>
                                            </form>
                                        @elseif(!$canAffordFee)
                                            <div class="text-xs text-red-600 font-medium">
                                                {{ __('transfers.transfer_fee_exceeds_budget') }}
                                            </div>
                                        @else
                                            <div class="flex flex-col sm:flex-row gap-2">
                                                {{-- Transfer Bid --}}
                                                <form method="POST" action="{{ route('game.scouting.bid', [$game->id, $player->id]) }}" class="flex items-center gap-2 flex-1">
                                                    @csrf
                                                    <x-money-input name="bid_amount" :value="(int)($askingPrice / 100)" :min="0" size="sm" />
                                                    <button type="submit" class="inline-flex items-center justify-center px-3 py-1.5 min-h-[36px] bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg transition-colors whitespace-nowrap">
                                                        {{ __('transfers.submit_bid') }}
                                                    </button>
                                                </form>
                                                {{-- Loan Request --}}
                                                <form method="POST" action="{{ route('game.scouting.loan', [$game->id, $player->id]) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center justify-center px-3 py-1.5 min-h-[36px] border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50 transition-colors whitespace-nowrap">
                                                        {{ __('transfers.request_loan') }}
                                                    </button>
                                                </form>
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
