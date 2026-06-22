import { toggleShortlist } from './modules/shortlist';

// The shortlist star rendered on every actionable <x-explore-player-row>. Owns
// its own optimistic state and stays in sync with other surfaces (the board,
// the dossier modal, a second row for the same player) via `shortlist-toggled`.
export default function shortlistStar(config) {
    return {
        isShortlisted: !!config.isShortlisted,
        inFlight: false,

        async toggle() {
            if (this.inFlight) return;
            this.inFlight = true;
            try {
                const data = await toggleShortlist(config.toggleUrl);
                if (data.success) {
                    this.isShortlisted = data.action === 'added';
                } else if (data.message) {
                    alert(data.message);
                }
            } catch (e) {
                // Network/parse error — leave the star as-is.
            } finally {
                this.inFlight = false;
            }
        },

        // Mirror a toggle that happened on another surface for this same player.
        syncFromEvent(detail) {
            if (detail.playerId === config.playerId) {
                this.isShortlisted = detail.action === 'added';
            }
        },
    };
}
