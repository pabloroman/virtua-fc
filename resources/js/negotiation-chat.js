export default function negotiationChat() {
    return {
        // State
        open: false,
        messages: [],
        loading: false,
        negotiationStatus: null, // 'open' | 'accepted' | 'rejected' | 'walked_away'
        round: 0,
        maxRounds: 3,

        // Player info (set on open)
        playerName: '',
        negotiateUrl: '',

        // Input state
        offerWage: 0,
        offerYears: 3,

        // CSRF
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',

        get isTerminal() {
            return ['accepted', 'rejected', 'walked_away'].includes(this.negotiationStatus);
        },

        get lastMessage() {
            return this.messages[this.messages.length - 1];
        },

        get hasOptions() {
            return this.lastMessage?.options != null;
        },

        async openChat(detail) {
            this.playerName = detail.playerName;
            this.negotiateUrl = detail.negotiateUrl;
            this.messages = [];
            this.loading = true;
            this.negotiationStatus = null;
            this.offerWage = 0;
            this.offerYears = 3;
            this.round = 0;
            this.open = true;

            const data = await this.sendAction('start');
            if (data) {
                this.negotiationStatus = data.negotiation_status;
                this.round = data.round || 0;
                this.maxRounds = data.max_rounds || 3;
                this.appendMessages(data.messages);

                // Pre-fill offer from suggested wage
                const lastMsg = this.messages[this.messages.length - 1];
                if (lastMsg?.options) {
                    this.offerWage = lastMsg.options.suggestedWage || 0;
                    this.offerYears = lastMsg.options.preferredYears || 3;
                }
            }
            this.loading = false;
        },

        async submitOffer() {
            if (this.loading || this.isTerminal) return;
            if (this.offerWage <= 0) return;

            // Show user's offer as a message
            this.messages.push({
                sender: 'user',
                type: 'offer',
                content: {
                    wage: this.offerWage,
                    years: this.offerYears,
                },
                options: null,
            });

            // Clear options from previous agent message
            this.clearLastOptions();

            this.loading = true;

            // Artificial delay for feel
            await this.delay(400 + Math.random() * 300);

            const data = await this.sendAction('offer', {
                wage: this.offerWage,
                years: this.offerYears,
            });

            if (data) {
                this.negotiationStatus = data.negotiation_status;
                this.round = data.round || this.round;
                this.appendMessages(data.messages);

                // Pre-fill next offer from suggested wage
                const lastMsg = this.messages[this.messages.length - 1];
                if (lastMsg?.options?.suggestedWage) {
                    this.offerWage = lastMsg.options.suggestedWage;
                }
                if (lastMsg?.options?.preferredYears) {
                    this.offerYears = lastMsg.options.preferredYears;
                }

                // Auto-close on accept
                if (this.negotiationStatus === 'accepted') {
                    setTimeout(() => this.closeAndReload(), 1500);
                }
            }
            this.loading = false;
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

            const data = await this.sendAction('accept_counter');
            if (data) {
                this.negotiationStatus = data.negotiation_status;
                this.appendMessages(data.messages);

                if (this.negotiationStatus === 'accepted') {
                    setTimeout(() => this.closeAndReload(), 1500);
                }
            }
            this.loading = false;
        },

        async walkAway() {
            if (this.loading || this.isTerminal) return;

            this.clearLastOptions();
            this.loading = true;

            const data = await this.sendAction('walk_away');
            if (data) {
                this.negotiationStatus = data.negotiation_status;
                this.appendMessages(data.messages);
            }
            this.loading = false;
        },

        closeChat() {
            this.open = false;
            // If negotiation completed or walked away, reload to update UI
            if (this.isTerminal) {
                window.location.reload();
            }
        },

        closeAndReload() {
            this.open = false;
            window.location.reload();
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
            // Scroll to bottom
            this.$nextTick(() => {
                const container = this.$refs.chatMessages;
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },

        clearLastOptions() {
            // Remove options from the last agent message so buttons disappear
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

        formatWage(cents) {
            // Format euros with K/M suffix
            if (cents >= 1000000) {
                const m = cents / 1000000;
                return '€' + (Number.isInteger(m) ? m : m.toFixed(1)) + 'M';
            }
            if (cents >= 1000) {
                const k = cents / 1000;
                return '€' + (Number.isInteger(k) ? k : k.toFixed(0)) + 'K';
            }
            return '€' + cents;
        },
    };
}
