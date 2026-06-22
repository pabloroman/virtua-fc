{{--
    Shared player dossier modal. Mounted ONCE per page (next to <x-negotiation-chat-modal>)
    and opened from ANY surface that renders <x-explore-player-row> — transfer market, explore,
    and scouting (search results + shortlist cards) — via the `open-player-dossier` window event.

    The event payload is fully self-contained (built by App\Support\PlayerDossierPresenter): all
    fields the modal renders + every action route. Optional scouting intel (willingness, asking
    price, wage demand, your budget, rival interest) renders only when the producing surface
    supplies it; the shortlist remove control shows only when the player is shortlisted.
--}}
<div x-data="playerDossier()"
     @open-player-dossier.window="open($event.detail)">
    <x-modal name="player-dossier" maxWidth="lg">
        <template x-if="detail">
            <div>
                {{-- Header: position badges + name + close (mirrors the owned-player detail) --}}
                <div class="px-5 py-4 border-b border-border-default flex items-center justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex items-center gap-1 shrink-0">
                            <template x-for="pos in (detail.positions || [])" :key="pos.abbreviation">
                                <span :class="pos.bg + ' ' + pos.text + ' inline-flex items-center justify-center w-7 h-7 text-[10px] -skew-x-12 font-semibold'">
                                    <span class="skew-x-12" x-text="pos.abbreviation"></span>
                                </span>
                            </template>
                        </div>
                        <h3 class="font-heading text-lg font-semibold text-text-primary truncate" x-text="detail.name"></h3>
                    </div>
                    <x-icon-button size="sm" @click="$dispatch('close-modal', 'player-dossier')" class="shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </x-icon-button>
                </div>

                {{-- Player banner: avatar + identity + overall. Pre-rendered server-side
                     from the shared <x-player-banner> (same source as the owned-player
                     detail) and injected here, so the two stay pixel-identical. --}}
                <div x-html="detail.bannerHtml"></div>

                {{-- Body --}}
                <div class="p-4 md:p-5 space-y-4">
                    {{-- Market reference (or release clause) + contract --}}
                    <div class="rounded-xl border border-border-default bg-surface-700/40 p-4 space-y-1.5">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs text-text-muted" x-text="detail.marketReferenceLabel"></span>
                            <span class="text-xs font-semibold text-text-body tabular-nums" x-text="detail.marketReferenceValue"></span>
                        </div>
                        <template x-if="detail.contractDate">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs text-text-muted">{{ __('transfers.contract_until') }}</span>
                                <span class="text-xs font-semibold" :class="detail.isExpiring ? 'text-accent-gold' : 'text-text-body'" x-text="detail.contractDate"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Financial details + scout intel — only when the surface supplies them --}}
                    <template x-if="detail.formattedAskingPrice || detail.formattedWageDemand || detail.formattedTransferBudget || detail.rivalInterest">
                        <div class="rounded-xl border border-border-default bg-surface-700/40 p-4 space-y-3">
                            <p class="text-[10px] font-semibold text-text-muted uppercase tracking-wide">{{ __('transfers.financial_details') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-if="detail.formattedAskingPrice">
                                    <div class="rounded-lg border border-border-default bg-surface-800/60 px-3 py-2">
                                        <p class="text-[10px] text-text-muted uppercase tracking-wide mb-0.5">{{ __('transfers.estimated_asking_price') }}</p>
                                        <p class="text-base font-bold tabular-nums" :class="detail.canAffordFee ? 'text-text-primary' : 'text-accent-red'" x-text="detail.formattedAskingPrice"></p>
                                    </div>
                                </template>
                                <template x-if="detail.formattedWageDemand">
                                    <div class="rounded-lg border border-border-default bg-surface-800/60 px-3 py-2">
                                        <p class="text-[10px] text-text-muted uppercase tracking-wide mb-0.5">{{ __('transfers.wage_demand') }}</p>
                                        <p class="text-base font-bold tabular-nums text-text-body" x-text="detail.formattedWageDemand + '{{ __('squad.per_year') }}'"></p>
                                    </div>
                                </template>
                            </div>
                            <template x-if="detail.formattedTransferBudget || detail.rivalInterest">
                                <div class="pt-3 border-t border-border-default space-y-2">
                                    <template x-if="detail.formattedTransferBudget">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-xs text-text-muted">{{ __('transfers.your_transfer_budget') }}</span>
                                            <span class="text-xs font-semibold text-text-body tabular-nums" x-text="detail.formattedTransferBudget"></span>
                                        </div>
                                    </template>
                                    <template x-if="detail.rivalInterest">
                                        <div class="flex items-center gap-1.5 text-xs font-medium text-accent-orange">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                            {{ __('transfers.rival_interest') }}
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Action zone — exclusive branch chain mirrors the row's eligibility logic --}}
                    <div class="space-y-3">
                        {{-- Free agent: negotiate personal terms (same flow as the inline row button) --}}
                        <template x-if="detail.isFreeAgent">
                            <x-primary-button color="green" class="w-full"
                                @click="$dispatch('open-negotiation', {
                                    playerName: detail.name,
                                    negotiateUrl: detail.freeAgentUrl,
                                    mode: 'free_agent',
                                    phase: 'personal_terms',
                                    chatTitle: {{ \Illuminate\Support\Js::from(__('transfers.chat_free_agent_title')) }},
                                    playerInfo: detail.playerInfo
                                }); $dispatch('close-modal', 'player-dossier')">
                                {{ __('transfers.negotiate') }}
                            </x-primary-button>
                        </template>

                        {{-- Offer awaiting response (pending, no counter) --}}
                        <template x-if="!detail.isFreeAgent && detail.hasOffer && detail.offerStatus === 'pending' && !detail.offerIsCounter">
                            <div class="flex w-full items-center justify-center gap-2 px-3 py-2.5 text-sm font-medium rounded-lg bg-accent-gold/10 text-accent-gold border border-accent-gold/20">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ __('transfers.bid_awaiting_response') }}
                            </div>
                        </template>

                        {{-- Counter-offer received (pending with counter) --}}
                        <template x-if="!detail.isFreeAgent && detail.hasOffer && detail.offerStatus === 'pending' && detail.offerIsCounter">
                            <div class="flex w-full items-center justify-center gap-2 px-3 py-2.5 text-sm font-medium rounded-lg bg-accent-blue/10 text-blue-400 border border-accent-blue/20">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                {{ __('transfers.counter_offer_received') }}
                            </div>
                        </template>

                        {{-- Transfer agreed, waiting for window --}}
                        <template x-if="!detail.isFreeAgent && detail.hasOffer && detail.offerStatus === 'agreed'">
                            <div class="flex w-full items-center justify-center gap-2 px-3 py-2.5 text-sm font-medium rounded-lg bg-accent-green/10 text-accent-green border border-accent-green/20">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ __('transfers.transfer_agreed') }}
                            </div>
                        </template>

                        {{-- Negotiation cooldown --}}
                        <template x-if="!detail.isFreeAgent && !detail.hasOffer && detail.onCooldown">
                            <div class="flex w-full items-center justify-center gap-2 px-3 py-2.5 text-sm font-medium rounded-lg bg-surface-700 text-text-muted border border-border-default">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ __('transfers.negotiation_cooldown_short') }}
                            </div>
                        </template>

                        {{-- Pre-contract --}}
                        <template x-if="!detail.isFreeAgent && !detail.hasOffer && !detail.onCooldown && detail.isExpiring && detail.isPreContractPeriod">
                            <x-primary-button color="green" class="w-full"
                                @click="$dispatch('open-negotiation', {
                                    playerName: detail.name,
                                    negotiateUrl: detail.preContractUrl,
                                    mode: 'pre_contract',
                                    phase: 'personal_terms',
                                    chatTitle: {{ \Illuminate\Support\Js::from(__('transfers.chat_pre_contract_title')) }},
                                    playerInfo: detail.playerInfo
                                }); $dispatch('close-modal', 'player-dossier')">
                                {{ __('transfers.negotiate_pre_contract') }}
                            </x-primary-button>
                        </template>

                        {{-- Player on loan — parent club retains authority --}}
                        <template x-if="!detail.isFreeAgent && !detail.hasOffer && !detail.onCooldown && !(detail.isExpiring && detail.isPreContractPeriod) && detail.isOnLoan">
                            <div class="flex w-full items-center justify-center gap-2 px-3 py-2.5 text-sm font-medium rounded-lg bg-violet-500/10 text-violet-400 border border-violet-500/20">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                {{ __('transfers.player_on_loan_unavailable') }}
                            </div>
                        </template>

                        {{-- Budget zero and can't afford loan — fully blocked --}}
                        <template x-if="!detail.isFreeAgent && !detail.hasOffer && !detail.onCooldown && !(detail.isExpiring && detail.isPreContractPeriod) && !detail.isOnLoan && detail.availableBudget <= 0 && !detail.canAffordLoan">
                            <div>
                                <div class="text-xs text-accent-red font-medium">
                                    {{ __('transfers.loan_fee_exceeds_budget') }}
                                </div>
                                <div class="text-xs text-text-muted mt-1">
                                    {{ __('transfers.loan_cost_salary') }}: <span class="text-text-body font-medium" x-text="detail.formattedWageDemand + '{{ __('squad.per_year') }}'"></span>
                                </div>
                            </div>
                        </template>

                        {{-- Negotiate + Loan --}}
                        <template x-if="!detail.isFreeAgent && !detail.hasOffer && !detail.onCooldown && !(detail.isExpiring && detail.isPreContractPeriod) && !detail.isOnLoan && (detail.availableBudget > 0 || detail.canAffordLoan)">
                            <div class="flex flex-col gap-2">
                                <template x-if="!detail.canAffordFee">
                                    <div class="text-xs text-accent-gold font-medium">
                                        {{ __('transfers.budget_limited_hint') }}
                                    </div>
                                </template>
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <template x-if="detail.availableBudget > 0">
                                        <x-primary-button class="w-full sm:flex-1"
                                            @click="$dispatch('open-negotiation', {
                                                playerName: detail.name,
                                                negotiateUrl: detail.negotiateUrl,
                                                mode: 'transfer_fee',
                                                phase: 'club_fee',
                                                chatTitle: {{ \Illuminate\Support\Js::from(__('transfers.chat_transfer_title')) }},
                                                playerInfo: detail.playerInfo
                                            }); $dispatch('close-modal', 'player-dossier')">
                                            {{ __('transfers.negotiate') }}
                                        </x-primary-button>
                                    </template>
                                    <x-secondary-button class="w-full sm:flex-1"
                                        @click="$dispatch('open-negotiation', {
                                            playerName: detail.name,
                                            negotiateUrl: detail.loanUrl,
                                            mode: 'loan',
                                            phase: 'club_fee',
                                            chatTitle: {{ \Illuminate\Support\Js::from(__('transfers.chat_loan_title')) }},
                                            playerInfo: detail.playerInfo
                                        }); $dispatch('close-modal', 'player-dossier')">
                                        {{ __('transfers.request_loan') }}
                                    </x-secondary-button>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Footer: remove from shortlist (shortlist cards only) --}}
                    <template x-if="detail.isShortlisted">
                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-border-default">
                            <x-ghost-button color="red" size="xs" @click="removeFromShortlist()" x-bind:disabled="removing">
                                {{ __('transfers.remove_from_shortlist') }}
                            </x-ghost-button>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </x-modal>
</div>
