export default function negotiationChat() {
    return {
        // State
        open: false,
        messages: [],
        loading: false,
        negotiationStatus: null, // 'open' | 'accepted' | 'rejected' | 'fee_agreed' | 'terms_open' | 'completed'
        round: 0,
        maxRounds: 3,

        // Mode: 'renewal' | 'transfer_fee' | 'personal_terms' | 'pre_contract' | 'loan' | 'free_agent'
        mode: 'renewal',
        // Phase: null (renewal) | 'club_fee' | 'personal_terms' | 'counter_offer'
        phase: null,

        // Player info (set on open)
        playerName: '',
        negotiateUrl: '',
        playerInfo: null,

        // Chat title (set on open)
        chatTitle: '',

        // Input state
        offerWage: 0,
        offerYears: 3,

        // Budget cap state (transfer fee mode)
        availableBudget: 0,
        budgetLoanAvailable: false,
        budgetLoanUrl: '',

        // Stepper hold state
        _holdTimer: null,
        _holdInterval: null,

        // CSRF
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',

        get isTerminal() {
            return ['accepted', 'rejected', 'completed'].includes(this.negotiationStatus);
        },

        get isAwaitingConfirmation() {
            return this.negotiationStatus === 'awaiting_confirmation';
        },

        get canSubmit() {
            return !this.loading && !this.isTerminal && this.offerWage > 0;
        },

        get isBudgetConstrained() {
            return this.mode === 'transfer_fee' && this.availableBudget > 0 && this.offerWage >= this.availableBudget;
        },

        get wageStep() {
            if (this.mode === 'transfer_fee' || this.phase === 'counter_offer') {
                return this.offerWage >= 10000000 ? 1000000 : 100000;
            }
            return this.offerWage >= 1000000 ? 100000 : 10000;
        },

        get wageDisplay() {
            return '€ ' + new Intl.NumberFormat('es-ES').format(this.offerWage);
        },

        incrementWage() {
            const newValue = this.offerWage + this.wageStep;
            if (this.mode === 'transfer_fee' && this.availableBudget > 0 && newValue > this.availableBudget) {
                this.offerWage = this.availableBudget;
                return;
            }
            this.offerWage = newValue;
        },

        decrementWage() {
            this.offerWage = Math.max(this.offerWage - this.wageStep, 0);
        },

        startHold(fn) {
            fn();
            this._holdTimer = setTimeout(() => {
                this._holdInterval = setInterval(() => fn(), 80);
            }, 400);
        },

        stopHold() {
            clearTimeout(this._holdTimer);
            clearInterval(this._holdInterval);
        },

        async openChat(detail) {
            this.playerName = detail.playerName;
            this.negotiateUrl = detail.negotiateUrl;
            this.mode = detail.mode || 'renewal';
            this.phase = detail.phase || null;
            this.chatTitle = detail.chatTitle || '';
            this.playerInfo = detail.playerInfo || null;
            this.messages = [];
            this.loading = true;
            this.negotiationStatus = null;
            this.offerWage = 0;
            this.offerYears = 3;
            this.availableBudget = 0;
            this.budgetLoanAvailable = false;
            this.budgetLoanUrl = '';
            this.round = 0;
            this.open = true;

            const data = await this.sendAction('start');
            if (data) {
                this.negotiationStatus = data.negotiation_status;
                this.round = data.round || 0;
                this.maxRounds = data.max_rounds || 3;
                if (data.available_budget !== undefined) this.availableBudget = data.available_budget;
                if (data.budget_loan_available !== undefined) this.budgetLoanAvailable = data.budget_loan_available;
                if (data.budget_loan_url) this.budgetLoanUrl = data.budget_loan_url;
                this.appendMessages(data.messages);

                // Fee already agreed from a previous session — go straight to personal terms
                if (data.negotiation_status === 'fee_agreed') {
                    await this.transitionToPersonalTerms();
                } else {
                    this.prefillFromOptions();
                }
            }
            this.loading = false;
        },

        async submitOffer() {
            if (!this.canSubmit) return;

            if (this.phase === 'counter_offer') {
                // Counter-offer: user is seller demanding a higher price
                this.messages.push({
                    sender: 'user',
                    type: 'bid',
                    content: { fee: this.offerWage },
                    options: null,
                });
                this.clearLastOptions();
                this.loading = true;
                await this.delay(400 + Math.random() * 300);

                const data = await this.sendAction('counter', { bid: this.offerWage });
                if (data) {
                    this.negotiationStatus = data.negotiation_status;
                    this.round = data.round || this.round;
                    this.appendMessages(data.messages);
                    this.prefillFromOptions();
                }
                this.loading = false;
            } else if (this.phase === 'club_fee') {
                // Show user's bid as a message
                this.messages.push({
                    sender: 'user',
                    type: 'bid',
                    content: { fee: this.offerWage },
                    options: null,
                });
                this.clearLastOptions();
                this.loading = true;
                await this.delay(400 + Math.random() * 300);

                const data = await this.sendAction('offer', { bid: this.offerWage });
                if (data) {
                    this.negotiationStatus = data.negotiation_status;
                    this.round = data.round || this.round;
                    if (data.available_budget !== undefined) this.availableBudget = data.available_budget;
                    this.appendMessages(data.messages);

                    // Handle fee agreed → transition to personal terms (transfers only)
                    if (data.negotiation_status === 'fee_agreed') {
                        await this.transitionToPersonalTerms();
                    } else {
                        this.prefillFromOptions();
                    }
                }
                this.loading = false;
            } else if (this.phase === 'personal_terms' || this.mode === 'pre_contract' || this.mode === 'free_agent') {
                // Show user's wage/years offer
                this.messages.push({
                    sender: 'user',
                    type: 'offer',
                    content: { wage: this.offerWage, years: this.offerYears },
                    options: null,
                });
                this.clearLastOptions();
                this.loading = true;
                await this.delay(400 + Math.random() * 300);

                const data = await this.sendAction('offer_terms', {
                    wage: this.offerWage,
                    years: this.offerYears,
                });
                if (data) {
                    this.negotiationStatus = data.negotiation_status;
                    this.round = data.round || this.round;
                    this.appendMessages(data.messages);
                    this.prefillFromOptions();
                }
                this.loading = false;
            } else {
                // Renewal mode (original behavior)
                this.messages.push({
                    sender: 'user',
                    type: 'offer',
                    content: { wage: this.offerWage, years: this.offerYears },
                    options: null,
                });
                this.clearLastOptions();
                this.loading = true;
                await this.delay(400 + Math.random() * 300);

                const data = await this.sendAction('offer', {
                    wage: this.offerWage,
                    years: this.offerYears,
                });
                if (data) {
                    this.negotiationStatus = data.negotiation_status;
                    this.round = data.round || this.round;
                    this.appendMessages(data.messages);
                    this.prefillFromOptions();
                }
                this.loading = false;
            }
        },

        async acceptCounter() {
            if (this.loading || this.isTerminal) return;

            // Show user acceptance
            this.messages.push({
                sender: 'user',
                type: 'accept',
                content: { text: '' },
                options: null,
            });
            this.clearLastOptions();

            this.loading = true;
            await this.delay(300);

            if (this.phase === 'counter_offer') {
                // Counter-offer: user accepts AI buyer's latest bid
                const data = await this.sendAction('accept_counter');
                if (data) {
                    this.negotiationStatus = data.negotiation_status;
                    this.appendMessages(data.messages);
                }
            } else if (this.phase === 'personal_terms' || this.mode === 'pre_contract' || this.mode === 'free_agent') {
                const data = await this.sendAction('accept_terms_counter');
                if (data) {
                    this.negotiationStatus = data.negotiation_status;
                    this.appendMessages(data.messages);
                }
            } else if (this.phase === 'club_fee') {
                const data = await this.sendAction('accept_counter');
                if (data) {
                    this.negotiationStatus = data.negotiation_status;
                    this.appendMessages(data.messages);

                    // Handle fee agreed → transition to personal terms (transfers only)
                    if (data.negotiation_status === 'fee_agreed') {
                        await this.transitionToPersonalTerms();
                    }
                }
            } else {
                // Renewal mode
                const data = await this.sendAction('accept_counter');
                if (data) {
                    this.negotiationStatus = data.negotiation_status;
                    this.appendMessages(data.messages);
                }
            }
            this.loading = false;
        },

        async confirmLoan() {
            if (this.loading || this.isTerminal) return;

            this.messages.push({
                sender: 'user',
                type: 'accept',
                content: { text: '' },
                options: null,
            });
            this.clearLastOptions();

            this.loading = true;
            await this.delay(400 + Math.random() * 300);

            const data = await this.sendAction('confirm');
            if (data) {
                this.negotiationStatus = data.negotiation_status;
                this.appendMessages(data.messages);
            }
            this.loading = false;
        },

        async rejectOffer() {
            if (this.loading || this.isTerminal) return;

            this.messages.push({
                sender: 'user',
                type: 'reject',
                content: { text: '' },
                options: null,
            });
            this.clearLastOptions();

            this.loading = true;
            await this.delay(300);

            const data = await this.sendAction('reject');
            if (data) {
                this.negotiationStatus = data.negotiation_status;
                this.appendMessages(data.messages);
            }
            this.loading = false;
        },

        async transitionToPersonalTerms() {
            await this.delay(800);

            // Append transition system message
            this.messages.push({
                sender: 'system',
                type: 'transition',
                content: { text: this.chatTitle ? '' : '' },
                options: null,
            });

            // Switch to personal terms mode
            this.phase = 'personal_terms';
            this.mode = 'personal_terms';
            this.negotiationStatus = 'terms_open';
            this.round = 0;

            // Fetch player's wage demand
            this.loading = true;
            const data = await this.sendAction('start_terms');
            if (data) {
                this.negotiationStatus = data.negotiation_status;
                this.round = data.round || 0;
                this.maxRounds = data.max_rounds || 3;
                this.appendMessages(data.messages);
                this.prefillFromOptions();
            }
            this.loading = false;
        },

        closeChat() {
            this.open = false;
            if (this.isTerminal) {
                window.location.reload();
            }
        },

        // ── Helpers ──

        async sendAction(action, payload = {}) {
            try {
                const response = await fetch(this.negotiateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ action, ...payload }),
                });

                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    this.messages.push({
                        sender: 'system',
                        type: 'error',
                        content: { text: error.message || 'Something went wrong' },
                        options: null,
                    });
                    return null;
                }

                return await response.json();
            } catch {
                this.messages.push({
                    sender: 'system',
                    type: 'error',
                    content: { text: 'Network error. Please try again.' },
                    options: null,
                });
                return null;
            }
        },

        appendMessages(messages) {
            if (!messages) return;
            for (const msg of messages) {
                this.messages.push(msg);
            }
            this.$nextTick(() => {
                const container = this.$refs.chatMessages;
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },

        prefillFromOptions() {
            const lastMsg = this.messages[this.messages.length - 1];
            if (lastMsg?.options?.suggestedWage) this.offerWage = lastMsg.options.suggestedWage;
            if (lastMsg?.options?.suggestedFee) {
                let fee = lastMsg.options.suggestedFee;
                if (this.mode === 'transfer_fee' && this.availableBudget > 0 && fee > this.availableBudget) {
                    fee = this.availableBudget;
                }
                this.offerWage = fee;
            }
            if (lastMsg?.options?.preferredYears) this.offerYears = lastMsg.options.preferredYears;
        },

        clearLastOptions() {
            for (let i = this.messages.length - 1; i >= 0; i--) {
                if (this.messages[i].sender === 'agent' && this.messages[i].options) {
                    this.messages[i].options = null;
                    break;
                }
            }
        },

        delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },

        formatWage(euros) {
            if (euros >= 1000000) {
                const m = euros / 1000000;
                return '€' + (Number.isInteger(m) ? m : m.toFixed(1)) + 'M';
            }
            if (euros >= 1000) {
                const k = euros / 1000;
                return '€' + (Number.isInteger(k) ? k : k.toFixed(0)) + 'K';
            }
            return '€' + euros;
        },
    };
}
