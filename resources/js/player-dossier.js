import { removeFromShortlist as sendRemove } from './modules/shortlist';

// Shared player dossier modal. Mounted once per page and opened from any surface
// that renders <x-explore-player-row> via the `open-player-dossier` event; the
// payload is fully self-contained (App\Support\PlayerDossierPresenter). The only
// behaviour beyond display is the optional "remove from shortlist" control.
export default function playerDossier() {
    return {
        detail: null,
        removing: false,

        open(detail) {
            this.detail = detail;
            this.$dispatch('open-modal', 'player-dossier');
        },

        removeFromShortlist() {
            if (this.removing || !this.detail || !this.detail.removeUrl) return;
            this.removing = true;
            const playerId = this.detail.id;
            sendRemove(this.detail.removeUrl, playerId)
                .then(() => {
                    this.$dispatch('close-modal', 'player-dossier');
                })
                .finally(() => {
                    this.removing = false;
                });
        },
    };
}
