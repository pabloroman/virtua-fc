/**
 * Half-time advisor module.
 *
 * Wires the "Apply" / "Dismiss" buttons in the half-time tip panel into the
 * existing tactical-actions pipeline. A tip's `tacticalChange` payload is
 * staged into the same `pending*` slots the tactical panel uses, then
 * confirmAllChanges() (from tactical-submission.js) submits to the existing
 * /tactical-actions endpoint — no new endpoints, no new write paths.
 */
export function createHalfTimeAdvisor(ctx) {
    return {
        /**
         * Stage a tip's tactical changes into the same pending slots used by
         * the tactical panel, then submit through the standard pipeline.
         * Dismisses the tip on success so it can't be re-applied accidentally.
         */
        async applyHalfTimeTip(tip) {
            const c = ctx();

            if (!tip || !tip.tacticalChange || c.applyingChanges) {
                return;
            }

            const change = tip.tacticalChange;

            // Stage each field on the matching pending slot. Fields the tip
            // doesn't mention stay null so confirmAllChanges() leaves them alone.
            if (change.mentality !== undefined) c.pendingMentality = change.mentality;
            if (change.playing_style !== undefined) c.pendingPlayingStyle = change.playing_style;
            if (change.pressing !== undefined) c.pendingPressing = change.pressing;
            if (change.defensive_line !== undefined) c.pendingDefLine = change.defensive_line;

            await c.confirmAllChanges();

            // Dismiss only after a successful submission (applyingChanges resets to
            // false in confirmAllChanges' finally block; tacticalError signals failure).
            if (!c.tacticalError) {
                this.dismissHalfTimeTip(tip);
            }
        },

        dismissHalfTimeTip(tip) {
            const c = ctx();
            if (!tip || !tip.id) return;
            if (c.halfTimeTipsDismissed.includes(tip.id)) return;

            c.halfTimeTipsDismissed = [...c.halfTimeTipsDismissed, tip.id];
        },
    };
}
