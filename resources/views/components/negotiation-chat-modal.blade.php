<div x-data="negotiationChat()" @open-negotiation.window="openChat($event.detail)" x-cloak>
    {{-- Backdrop + Modal --}}
    <div x-show="open" class="fixed inset-0 z-[60] overflow-y-auto px-4 py-6 sm:px-0" style="display:none">
        {{-- Backdrop --}}
        <div x-show="open" @click="closeChat()"
            class="fixed inset-0 transition-opacity"
            x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="absolute inset-0 bg-surface-900 opacity-60"></div>
        </div>

        {{-- Dialog --}}
        <div x-show="open"
            class="relative mb-6 bg-surface-800 rounded-xl shadow-2xl sm:w-full sm:max-w-md sm:mx-auto flex flex-col"
            style="height: min(600px, calc(100vh - 3rem))"
            x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">

            {{-- Header --}}
            <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-border-strong shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-surface-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-semibold text-text-primary truncate text-sm" x-text="playerName"></h3>
                        <p class="text-xs text-text-muted" x-text="chatTitle || @js(__('transfers.chat_title'))"></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <template x-if="round > 0 && !isTerminal">
                        <span class="text-[10px] text-text-muted tabular-nums"
                            x-text="@js(__('transfers.chat_round', ['current' => '__R__', 'max' => '__M__'])).replace('__R__', round).replace('__M__', maxRounds)"></span>
                    </template>
                    <button type="button" @click="closeChat()"
                        class="p-1.5 rounded-lg text-text-secondary hover:text-text-primary hover:bg-surface-700 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>

            {{-- Player info strip --}}
            <div x-show="playerInfo" x-cloak class="shrink-0 border-b border-border-strong px-5 py-2.5 bg-surface-700/30">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                    {{-- Position badge (mirrors x-position-badge size="sm", dynamic because data comes from Alpine) --}}
                    <template x-if="playerInfo?.position">
                        <span class="inline-flex items-center justify-center w-5 h-5 text-[10px] -skew-x-12 font-semibold"
                            :class="(playerInfo.positionBg || 'bg-surface-600') + ' ' + (playerInfo.positionText || 'text-text-primary')">
                            <span class="skew-x-12" x-text="playerInfo.position"></span>
                        </span>
                    </template>

                    {{-- Age --}}
                    <template x-if="playerInfo?.age">
                        <span class="text-text-secondary">
                            <span class="text-text-muted">{{ __('transfers.chat_player_age') }}</span>
                            <span class="font-semibold text-text-primary tabular-nums" x-text="playerInfo.age"></span>
                        </span>
                    </template>

                    {{-- Salary (own player) --}}
                    <template x-if="playerInfo?.wage">
                        <span class="text-text-secondary">
                            <span class="text-text-muted">{{ __('transfers.chat_player_salary') }}</span>
                            <span class="font-semibold text-text-primary" x-text="playerInfo.wage"></span>
                        </span>
                    </template>

                    {{-- Market value (other player) --}}
                    <template x-if="playerInfo?.marketValue">
                        <span class="text-text-secondary">
                            <span class="text-text-muted">{{ __('transfers.chat_player_value') }}</span>
                            <span class="font-semibold text-text-primary" x-text="playerInfo.marketValue"></span>
                        </span>
                    </template>

                    {{-- Contract year (other player) --}}
                    <template x-if="playerInfo?.contractYear">
                        <span class="text-text-secondary">
                            <span class="text-text-muted">{{ __('transfers.chat_player_contract') }}</span>
                            <span class="font-semibold text-text-primary tabular-nums" x-text="playerInfo.contractYear"></span>
                        </span>
                    </template>

                    {{-- TEC (exact) --}}
                    <template x-if="playerInfo?.tec != null">
                        <span class="text-text-secondary">
                            <span class="text-text-muted">{{ __('squad.technical_abbr') }}</span>
                            <span class="font-semibold text-text-primary tabular-nums" x-text="playerInfo.tec"></span>
                        </span>
                    </template>

                    {{-- FIS (exact, own players only) --}}
                    <template x-if="playerInfo?.fis != null">
                        <span class="text-text-secondary">
                            <span class="text-text-muted">{{ __('squad.physical_abbr') }}</span>
                            <span class="font-semibold text-text-primary tabular-nums" x-text="playerInfo.fis"></span>
                        </span>
                    </template>
                </div>
            </div>

            {{-- Messages --}}
            <div x-ref="chatMessages" class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
                <template x-for="(msg, idx) in messages" :key="idx">
                    <div>
                        {{-- Agent message --}}
                        <template x-if="msg.sender === 'agent'">
                            <div class="flex gap-2.5 items-start">
                                <div class="w-7 h-7 rounded-full bg-surface-600 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-3.5 h-3.5 text-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div class="max-w-[85%] space-y-2">
                                    <div class="bg-surface-700 rounded-xl rounded-tl-sm px-3.5 py-2.5 text-sm text-text-body" x-text="msg.content.text"></div>
                                    {{-- Mood indicator --}}
                                    <template x-if="msg.content.mood">
                                        <div class="flex items-center gap-1.5 px-1">
                                            <span class="w-1.5 h-1.5 rounded-full"
                                                :class="{
                                                    'bg-accent-green': msg.content.mood.color === 'green',
                                                    'bg-accent-gold': msg.content.mood.color === 'amber',
                                                    'bg-accent-red': msg.content.mood.color === 'red',
                                                }"></span>
                                            <span class="text-xs"
                                                :class="{
                                                    'text-accent-green': msg.content.mood.color === 'green',
                                                    'text-accent-gold': msg.content.mood.color === 'amber',
                                                    'text-accent-red': msg.content.mood.color === 'red',
                                                }"
                                                x-text="msg.content.mood.label"></span>
                                        </div>
                                    </template>
                                    {{-- Accept / Reject counter-offer --}}
                                    <template x-if="msg.options?.canAccept">
                                        <div class="pt-1 flex gap-2">
                                            <button type="button" @click="acceptCounter()"
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-accent-green/15 text-accent-green hover:bg-accent-green/25 transition-colors min-h-[36px]">
                                                {{ __('transfers.chat_accept') }}
                                            </button>
                                            <template x-if="phase === 'counter_offer'">
                                                <button type="button" @click="rejectOffer()"
                                                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-accent-red/15 text-accent-red hover:bg-accent-red/25 transition-colors min-h-[36px]">
                                                    {{ __('transfers.chat_reject') }}
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                    {{-- Confirm loan --}}
                                    <template x-if="msg.options?.canConfirm">
                                        <div class="pt-1">
                                            <button type="button" @click="confirmLoan()"
                                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-accent-green/15 text-accent-green hover:bg-accent-green/25 transition-colors min-h-[36px]">
                                                {{ __('transfers.chat_loan_confirm') }}
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- User message --}}
                        <template x-if="msg.sender === 'user'">
                            <div class="flex justify-end">
                                <div class="max-w-[80%]">
                                    {{-- Transfer fee bid --}}
                                    <template x-if="msg.type === 'bid'">
                                        <div class="bg-accent-blue/15 rounded-xl rounded-tr-sm px-3.5 py-2.5 text-sm text-text-body">
                                            <span x-text="formatWage(msg.content.fee)"></span>
                                        </div>
                                    </template>
                                    {{-- Wage + years offer --}}
                                    <template x-if="msg.type === 'offer'">
                                        <div class="bg-accent-blue/15 rounded-xl rounded-tr-sm px-3.5 py-2.5 text-sm text-text-body">
                                            <span x-text="formatWage(msg.content.wage)"></span>{{ __('squad.per_year') }}
                                            <span class="text-text-muted mx-1">&middot;</span>
                                            <span x-text="msg.content.years + (msg.content.years === 1 ? ' {{ __("transfers.year_singular") }}' : ' {{ __("transfers.year_plural") }}')"></span>
                                        </div>
                                    </template>
                                    {{-- User accepts counter --}}
                                    <template x-if="msg.type === 'accept'">
                                        <div class="bg-accent-green/15 rounded-xl rounded-tr-sm px-3.5 py-2.5 text-sm text-accent-green font-medium">
                                            {{ __('transfers.chat_user_accepts') }}
                                        </div>
                                    </template>
                                    {{-- User rejects offer --}}
                                    <template x-if="msg.type === 'reject'">
                                        <div class="bg-accent-red/15 rounded-xl rounded-tr-sm px-3.5 py-2.5 text-sm text-accent-red font-medium">
                                            {{ __('transfers.chat_user_rejects') }}
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- System message --}}
                        <template x-if="msg.sender === 'system'">
                            <div class="text-center py-2">
                                <template x-if="msg.type === 'transition'">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-accent-blue/10 text-accent-blue border border-accent-blue/20">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                        </svg>
                                        {{ __('transfers.chat_terms_transition') }}
                                    </span>
                                </template>
                                <template x-if="msg.type !== 'transition'">
                                    <span class="text-xs text-text-muted" x-text="msg.content.text"></span>
                                </template>
                            </div>
                        </template>

                        {{-- Accepted celebration --}}
                        <template x-if="msg.sender === 'agent' && msg.type === 'accepted'">
                            <div class="text-center py-2">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-accent-green/15 text-accent-green">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <template x-if="mode === 'renewal'">
                                        <span>{{ __('transfers.chat_renewal_agreed') }}</span>
                                    </template>
                                    <template x-if="negotiationStatus === 'completed' && mode === 'pre_contract'">
                                        <span>{{ __('transfers.chat_pre_contract_deal') }}</span>
                                    </template>
                                    <template x-if="negotiationStatus === 'completed' && mode === 'loan'">
                                        <span>{{ __('transfers.chat_loan_deal') }}</span>
                                    </template>
                                    <template x-if="mode !== 'renewal' && negotiationStatus === 'completed' && mode !== 'pre_contract' && mode !== 'loan'">
                                        <span>{{ __('transfers.chat_deal_agreed') }}</span>
                                    </template>
                                    <template x-if="mode !== 'renewal' && negotiationStatus !== 'completed'">
                                        <span>{{ __('transfers.chat_club_agreement') }}</span>
                                    </template>
                                </span>
                            </div>
                        </template>

                        {{-- Rejected --}}
                        <template x-if="msg.sender === 'agent' && msg.type === 'rejected'">
                            <div class="text-center py-2">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-accent-red/15 text-accent-red">
                                    {{ __('transfers.chat_deal_failed') }}
                                </span>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Typing indicator --}}
                <template x-if="loading">
                    <div class="flex gap-2.5 items-start">
                        <div class="w-7 h-7 rounded-full bg-surface-600 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-3.5 h-3.5 text-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="bg-surface-700 rounded-xl rounded-tl-sm px-4 py-3">
                            <div class="flex gap-1">
                                <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                                <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                                <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Budget constraint banner --}}
            <div x-show="mode === 'transfer_fee' && availableBudget > 0 && !isTerminal && negotiationStatus !== 'fee_agreed' && availableBudget < (messages[0]?.content?.fee || 0)"
                 x-cloak class="shrink-0 border-t border-border-strong px-5 py-2.5">
                <div class="rounded-lg bg-accent-gold/10 border border-accent-gold/20 px-3 py-2">
                    <div class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-accent-gold shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="text-xs text-accent-gold font-medium">{{ __('transfers.budget_cap_warning') }}</p>
                            <p class="text-xs text-text-muted mt-0.5">
                                {{ __('transfers.budget_available') }}: <span class="text-text-body font-semibold" x-text="'€ ' + new Intl.NumberFormat('es-ES').format(availableBudget)"></span>
                            </p>
                            <template x-if="budgetLoanAvailable">
                                <a :href="budgetLoanUrl" target="_blank"
                                   class="inline-flex items-center gap-1 mt-1 text-xs text-accent-blue hover:underline font-medium">
                                    {{ __('transfers.request_budget_loan') }}
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Input area: Transfer fee / loan fee mode --}}
            <div class="shrink-0 border-t border-border-strong px-5 py-3 space-y-2.5" x-show="mode === 'transfer_fee' && !isTerminal && !loading && negotiationStatus !== 'fee_agreed'">
                <div class="flex items-end gap-2">
                    <div class="flex-1 min-w-0">
                        <label class="text-[10px] text-text-muted uppercase tracking-wider block mb-1">{{ __('transfers.chat_your_bid') }}</label>
                        <div class="inline-flex items-stretch border border-border-strong rounded-lg overflow-hidden h-[36px] w-full">
                            <button type="button"
                                :disabled="offerWage <= 0"
                                :class="offerWage <= 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-surface-600'"
                                class="min-h-[32px] min-w-[32px] flex items-center justify-center bg-surface-700 text-text-body font-bold select-none transition-colors text-sm"
                                @mousedown.prevent="startHold(() => decrementWage())"
                                @mouseup="stopHold()" @mouseleave="stopHold()"
                                @touchstart.prevent="startHold(() => decrementWage())" @touchend="stopHold()"
                            >&minus;</button>
                            <input type="text" readonly :value="wageDisplay"
                                class="min-h-[32px] flex-1 min-w-0 text-center font-semibold text-text-primary bg-surface-800 border-x border-y-0 border-border-strong outline-hidden cursor-default focus:outline-hidden focus:ring-0 text-xs">
                            <button type="button"
                                class="min-h-[32px] min-w-[32px] flex items-center justify-center bg-surface-700 hover:bg-surface-600 text-text-body font-bold select-none transition-colors text-sm"
                                @mousedown.prevent="startHold(() => incrementWage())"
                                @mouseup="stopHold()" @mouseleave="stopHold()"
                                @touchstart.prevent="startHold(() => incrementWage())" @touchend="stopHold()"
                            >+</button>
                        </div>
                    </div>
                </div>

                <button type="button" @click="submitOffer()"
                    :disabled="!canSubmit"
                    :class="!canSubmit ? 'opacity-40 cursor-not-allowed' : 'hover:bg-accent-green/80'"
                    class="w-full h-[36px] rounded-lg bg-accent-green text-white text-xs font-semibold transition-colors min-h-[36px] flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    {{ __('transfers.chat_send_offer') }}
                </button>
            </div>

            {{-- Input area: Wage + years mode (renewal, personal terms, and pre-contract) --}}
            <div class="shrink-0 border-t border-border-strong px-5 py-3 space-y-2.5" x-show="(mode === 'renewal' || mode === 'personal_terms' || mode === 'pre_contract') && !isTerminal && !loading">
                <div class="flex items-end gap-2">
                    {{-- Wage stepper --}}
                    <div class="flex-1 min-w-0">
                        <label class="text-[10px] text-text-muted uppercase tracking-wider block mb-1">{{ __('transfers.your_offer') }}</label>
                        <div class="inline-flex items-stretch border border-border-strong rounded-lg overflow-hidden h-[36px] w-full">
                            <button type="button"
                                :disabled="offerWage <= 0"
                                :class="offerWage <= 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-surface-600'"
                                class="min-h-[32px] min-w-[32px] flex items-center justify-center bg-surface-700 text-text-body font-bold select-none transition-colors text-sm"
                                @mousedown.prevent="startHold(() => decrementWage())"
                                @mouseup="stopHold()" @mouseleave="stopHold()"
                                @touchstart.prevent="startHold(() => decrementWage())" @touchend="stopHold()"
                            >&minus;</button>
                            <input type="text" readonly :value="wageDisplay"
                                class="min-h-[32px] flex-1 min-w-0 text-center font-semibold text-text-primary bg-surface-800 border-x border-y-0 border-border-strong outline-hidden cursor-default focus:outline-hidden focus:ring-0 text-xs">
                            <button type="button"
                                class="min-h-[32px] min-w-[32px] flex items-center justify-center bg-surface-700 hover:bg-surface-600 text-text-body font-bold select-none transition-colors text-sm"
                                @mousedown.prevent="startHold(() => incrementWage())"
                                @mouseup="stopHold()" @mouseleave="stopHold()"
                                @touchstart.prevent="startHold(() => incrementWage())" @touchend="stopHold()"
                            >+</button>
                        </div>
                    </div>

                    {{-- Years dropdown --}}
                    <div class="flex-1 min-w-0">
                        <label class="text-[10px] text-text-muted uppercase tracking-wider block mb-1">{{ __('transfers.contract_duration') }}</label>
                        <select x-model.number="offerYears"
                            class="bg-surface-700 border border-border-strong text-text-primary focus:border-accent-blue/50 focus:ring-accent-blue rounded-lg shadow-xs text-xs h-[36px] w-full">
                            <option value="1">1 {{ __('transfers.year_singular') }}</option>
                            <option value="2">2 {{ __('transfers.year_plural') }}</option>
                            <option value="3">3 {{ __('transfers.year_plural') }}</option>
                            <option value="4">4 {{ __('transfers.year_plural') }}</option>
                            <option value="5">5 {{ __('transfers.year_plural') }}</option>
                        </select>
                    </div>
                </div>

                {{-- Submit --}}
                <button type="button" @click="submitOffer()"
                    :disabled="!canSubmit"
                    :class="!canSubmit ? 'opacity-40 cursor-not-allowed' : 'hover:bg-accent-green/80'"
                    class="w-full h-[36px] rounded-lg bg-accent-green text-white text-xs font-semibold transition-colors min-h-[36px] flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    {{ __('transfers.chat_send_offer') }}
                </button>
            </div>

            {{-- Terminal state: close button --}}
            <div class="shrink-0 border-t border-border-strong px-5 py-3 text-center" x-show="isTerminal && !loading">
                <button type="button" @click="closeChat()"
                    class="px-6 py-2 text-sm font-medium rounded-lg bg-surface-700 text-text-body hover:bg-surface-600 transition-colors min-h-[40px]">
                    {{ __('app.close') }}
                </button>
            </div>
        </div>
    </div>
</div>
