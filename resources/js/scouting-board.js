// Scouting hub shortlist board. Holds the current shortlist cards and reacts to
// the `shortlist-toggled` window event (fired by any star or the dossier modal),
// live-adding or removing a card so the board stays in sync without a reload.
export default function scoutingBoard(config) {
    return {
        players: config.players || [],

        handleToggle(detail) {
            if (detail.action === 'added' && detail.player) {
                if (!this.players.find((p) => p.id === detail.player.id)) {
                    this.players.unshift(detail.player);
                }
            } else if (detail.action === 'removed') {
                this.players = this.players.filter((p) => p.id !== detail.playerId);
            }
        },

        openDetail(player) {
            // Open the shared dossier modal — the target object already carries the
            // full self-contained payload (from buildTargetData).
            this.$dispatch('open-player-dossier', player);
        },
    };
}
