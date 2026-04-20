/**
 * Tactical panel UI module. Owns modal/picker state, pending-change
 * getters, formation preview fetch, and the compute-slots POST helper.
 *
 * Network pipeline (POST /tactical-actions) lives in tactical-submission.js;
 * this module only covers the panel's UI surface.
 */

const COMPAT_NATURAL_THRESHOLD = 80;

export function createTacticalPanel(ctx) {
    return {
        // --- Open / close -----------------------------------------------
        openTacticalPanel(tab = 'substitutions', keepInjuryAlert = false) {
            const c = ctx();
            c.tacticalTab = tab;
            c.tacticalPanelOpen = true;
            c.selectedPlayerOut = null;
            c.selectedPlayerIn = null;
            c.livePitchSelectedOutId = null;
            c.pendingSubs = [];
            c.pendingFormation = null;
            c.pendingMentality = null;
            c.previewSlotMap = null;
            this.closeFormationPicker();
            if (!keepInjuryAlert) {
                c.injuryAlertPlayer = null;
            }
            document.body.classList.add('overflow-y-hidden');
        },

        closeTacticalPanel() {
            const c = ctx();
            c.tacticalPanelOpen = false;
            c.selectedPlayerOut = null;
            c.selectedPlayerIn = null;
            c.livePitchSelectedOutId = null;
            c.pendingSubs = [];
            c.pendingFormation = null;
            c.pendingMentality = null;
            c.previewSlotMap = null;
            c.draggingSlotId = null;
            c.dragPosition = null;
            c.positioningSlotId = null;
            c.injuryAlertPlayer = null;
            c.showingConfirmation = false;
            this.closeFormationPicker();
            document.body.classList.remove('overflow-y-hidden');
        },

        safeCloseTacticalPanel() {
            const c = ctx();
            if (this.hasPendingChanges) {
                if (!confirm(c.translations?.unsavedTacticalChanges ?? 'You have unsubmitted changes. Close anyway?')) {
                    return;
                }
            }
            this.closeTacticalPanel();
        },

        // --- Pending-change getters -------------------------------------
        get hasSubPendingChanges() {
            const c = ctx();
            return c.pendingSubs.length > 0
                || (c.selectedPlayerOut !== null && c.selectedPlayerIn !== null);
        },

        get hasPendingChanges() {
            return this.hasSubPendingChanges || this.hasTacticalChanges;
        },

        get hasTacticalChanges() {
            const c = ctx();
            return (c.pendingFormation !== null && c.pendingFormation !== c.activeFormation)
                || (c.pendingMentality !== null && c.pendingMentality !== c.activeMentality)
                || (c.pendingPlayingStyle !== null && c.pendingPlayingStyle !== c.activePlayingStyle)
                || (c.pendingPressing !== null && c.pendingPressing !== c.activePressing)
                || (c.pendingDefLine !== null && c.pendingDefLine !== c.activeDefLine)
                || Object.keys(c._manualSlotPins).length > 0;
        },

        // --- Option label / tooltip lookups -----------------------------
        get mentalityLabel() {
            const c = ctx();
            const m = c.availableMentalities.find(m => m.value === c.activeMentality);
            return m ? m.label : c.activeMentality;
        },

        getMentalityLabel(value) {
            const c = ctx();
            const m = c.availableMentalities.find(m => m.value === value);
            return m ? m.label : value;
        },

        getFormationTooltip() {
            const c = ctx();
            const selected = c.pendingFormation ?? c.activeFormation;
            const f = c.availableFormations.find(f => f.value === selected);
            return f ? f.tooltip : '';
        },

        getMentalityTooltip(value) {
            const c = ctx();
            const m = c.availableMentalities.find(m => m.value === value);
            return m ? m.tooltip : '';
        },

        getOptionLabel(options, value) {
            const opt = options.find(o => o.value === value);
            return opt ? opt.label : value;
        },

        // --- Reset ------------------------------------------------------
        resetTactics() {
            const c = ctx();
            c.pendingFormation = null;
            c.pendingMentality = null;
            c.pendingPlayingStyle = null;
            c.pendingPressing = null;
            c.pendingDefLine = null;
            c.previewSlotMap = null;
        },

        resetAllChanges() {
            const c = ctx();
            c.resetSubstitutions();
            this.resetTactics();
            c.showingConfirmation = false;
            this.closeFormationPicker();
            c.tacticalError = null;
        },

        // --- Formation preview ------------------------------------------
        /**
         * Fired by the tactical-lever callback whenever the user clicks a
         * formation button in the tactical panel. Hits the backend's
         * compute-slots endpoint with the current on-pitch 11 + the new
         * formation, and stores the resulting slot map in `previewSlotMap`.
         *
         * Uses a monotonic `_previewFetchId` to ignore stale responses when
         * the user clicks multiple formations in quick succession.
         */
        async refreshFormationPreview() {
            const c = ctx();
            if (c.pendingFormation === null || c.pendingFormation === c.activeFormation) {
                c.previewSlotMap = null;
                return;
            }
            if (!c.computeSlotsUrl) return;

            // Clear the previous preview so the getter falls back to the
            // local placeholder during the fetch window.
            c.previewSlotMap = null;

            const fetchId = ++c._previewFetchId;
            const targetFormation = c.pendingFormation;
            // Preview the XI the user is about to commit, not the current
            // on-pitch 11: pending subs must be reflected so the incoming
            // bench players are placed into the new formation.
            const playerIds = this._postSubActivePlayerIds();
            if (playerIds.length === 0) return;

            try {
                const data = await this._postComputeSlots({
                    formation: targetFormation,
                    player_ids: playerIds,
                    manual_assignments: {},
                });
                // Ignore stale responses from superseded clicks.
                if (fetchId !== c._previewFetchId) return;
                // Ignore responses for a formation the user already moved off.
                if (c.pendingFormation !== targetFormation) return;
                if (data) c.previewSlotMap = data.slot_assignments ?? {};
            } catch (e) {
                console.error('Failed to compute formation preview', e);
            }
        },

        /**
         * POST to the compute-slots endpoint. Returns the parsed JSON body
         * on 2xx, or null otherwise. Throws on network/parse error so
         * callers can decide whether to log.
         */
        async _postComputeSlots(body) {
            const c = ctx();
            const response = await fetch(c.computeSlotsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': c.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            });
            if (!response.ok) return null;
            return response.json();
        },

        /**
         * List of player ids that will be on the pitch after the currently
         * staged subs are committed.
         */
        _postSubActivePlayerIds() {
            const c = ctx();
            const allPending = [...c.pendingSubs];
            if (c.selectedPlayerOut && c.selectedPlayerIn) {
                const alreadyPending = allPending.some(s => s.playerOut.id === c.selectedPlayerOut.id);
                if (!alreadyPending) {
                    allPending.push({
                        playerOut: c.selectedPlayerOut,
                        playerIn: c.selectedPlayerIn,
                    });
                }
            }
            const pendingOutIds = new Set(allPending.map(s => s.playerOut.id));
            const active = c.getActiveLineupPlayers()
                .filter(p => !pendingOutIds.has(p.id))
                .map(p => p.id);
            for (const sub of allPending) active.push(sub.playerIn.id);
            return active;
        },

        // --- Confirmation overlay ---------------------------------------
        get confirmationSummary() {
            const c = ctx();
            const summary = { subs: [], tactics: [] };

            for (const sub of c.pendingSubs) {
                summary.subs.push({
                    playerOut: sub.playerOut.name,
                    playerOutAbbr: sub.playerOut.positionAbbr,
                    playerOutGroup: sub.playerOut.positionGroup,
                    playerIn: sub.playerIn.name,
                    playerInAbbr: sub.playerIn.positionAbbr,
                    playerInGroup: sub.playerIn.positionGroup,
                });
            }

            if (c.selectedPlayerOut && c.selectedPlayerIn) {
                const alreadyPending = summary.subs.some(
                    s => s.playerOut === c.selectedPlayerOut.name && s.playerIn === c.selectedPlayerIn.name,
                );
                if (!alreadyPending) {
                    summary.subs.push({
                        playerOut: c.selectedPlayerOut.name,
                        playerOutAbbr: c.selectedPlayerOut.positionAbbr,
                        playerOutGroup: c.selectedPlayerOut.positionGroup,
                        playerIn: c.selectedPlayerIn.name,
                        playerInAbbr: c.selectedPlayerIn.positionAbbr,
                        playerInGroup: c.selectedPlayerIn.positionGroup,
                    });
                }
            }

            if (c.pendingFormation !== null && c.pendingFormation !== c.activeFormation) {
                summary.tactics.push({
                    label: c.translations.confirmFormation ?? 'Formation',
                    from: c.activeFormation,
                    to: c.pendingFormation,
                });
            }
            if (c.pendingMentality !== null && c.pendingMentality !== c.activeMentality) {
                summary.tactics.push({
                    label: c.translations.confirmMentality ?? 'Mentality',
                    from: this.getOptionLabel(c.availableMentalities, c.activeMentality),
                    to: this.getOptionLabel(c.availableMentalities, c.pendingMentality),
                });
            }
            if (c.pendingPlayingStyle !== null && c.pendingPlayingStyle !== c.activePlayingStyle) {
                summary.tactics.push({
                    label: c.translations.confirmPlayingStyle ?? 'Playing style',
                    from: this.getOptionLabel(c.availablePlayingStyles, c.activePlayingStyle),
                    to: this.getOptionLabel(c.availablePlayingStyles, c.pendingPlayingStyle),
                });
            }
            if (c.pendingPressing !== null && c.pendingPressing !== c.activePressing) {
                summary.tactics.push({
                    label: c.translations.confirmPressing ?? 'Pressing',
                    from: this.getOptionLabel(c.availablePressing, c.activePressing),
                    to: this.getOptionLabel(c.availablePressing, c.pendingPressing),
                });
            }
            if (c.pendingDefLine !== null && c.pendingDefLine !== c.activeDefLine) {
                summary.tactics.push({
                    label: c.translations.confirmDefLine ?? 'Defensive line',
                    from: this.getOptionLabel(c.availableDefLine, c.activeDefLine),
                    to: this.getOptionLabel(c.availableDefLine, c.pendingDefLine),
                });
            }

            return summary;
        },

        showConfirmation() {
            const c = ctx();
            c.tacticalError = null;

            // Gate: if the pending subs (combined with the current formation)
            // leave a player in a slot where their compat falls below the
            // natural-position threshold, open the formation picker instead.
            const offenders = this._computePickerOffenders();
            if (offenders.length > 0) {
                this.openFormationPicker(offenders);
                return;
            }

            c.showingConfirmation = true;
            this._scrollTacticalToTop();
        },

        cancelConfirmation() {
            ctx().showingConfirmation = false;
        },

        // On mobile the pitch and controls stack vertically in a single
        // scroll container and the confirmation/picker overlays are
        // absolutely positioned. Reset scroll so overlay content lands
        // inside the viewport when it opens.
        _scrollTacticalToTop() {
            const c = ctx();
            c.$nextTick(() => {
                const scrollContainer = c.$refs.tacticalScrollContainer;
                if (scrollContainer) scrollContainer.scrollTop = 0;
            });
        },

        /**
         * Return assignments whose player sits in a slot with compatibility
         * below the natural-position threshold, under the pending or active
         * formation.
         */
        _computePickerOffenders() {
            const c = ctx();
            const hasStagedSubs = c.pendingSubs.length > 0
                || (c.selectedPlayerOut && c.selectedPlayerIn);
            const hasStagedFormationChange = c.pendingFormation !== null
                && c.pendingFormation !== c.activeFormation;
            if (!hasStagedSubs && !hasStagedFormationChange) return [];

            return c.slotAssignments.filter(a =>
                a.player
                && a.compatibility < COMPAT_NATURAL_THRESHOLD
                && !c.redCardedPlayerIds.includes(a.player.id),
            );
        },

        // --- Formation picker -------------------------------------------
        async openFormationPicker(offenders = null) {
            const c = ctx();
            const items = offenders ?? this._computePickerOffenders();
            c.formationPickerOffenders = items.map(a => ({
                slotLabel: a.label,
                displayLabel: a.displayLabel,
                playerName: a.player.name,
                playerPosition: a.player.position,
                compatibility: a.compatibility,
            }));
            c.formationPickerOpen = true;
            c.formationPickerSuggested = null;
            c.formationPickerLoading = true;

            if (!c.computeSlotsUrl) {
                c.formationPickerLoading = false;
                return;
            }

            const currentFormation = c.pendingFormation ?? c.activeFormation;
            const playerIds = this._postSubActivePlayerIds();
            if (playerIds.length === 0) {
                c.formationPickerLoading = false;
                return;
            }

            try {
                const data = await this._postComputeSlots({
                    formation: currentFormation,
                    player_ids: playerIds,
                    include_suggested_formation: true,
                });
                if (data?.suggested_formation && data.suggested_formation !== currentFormation) {
                    c.formationPickerSuggested = data.suggested_formation;
                }
            } catch (e) {
                console.error('Failed to fetch suggested formation', e);
            } finally {
                c.formationPickerLoading = false;
            }
        },

        closeFormationPicker() {
            const c = ctx();
            c.formationPickerOpen = false;
            c.formationPickerOffenders = [];
            c.formationPickerSuggested = null;
            c.formationPickerLoading = false;
        },

        /**
         * User picked a formation from the prompt (or the suggested one).
         * Queue it as a pending formation change so confirmAllChanges()
         * applies the sub and the formation in the same round trip.
         */
        acceptFormationPickerChoice(formationValue) {
            if (!formationValue) return;
            const c = ctx();
            if (formationValue !== c.activeFormation) {
                c.pendingFormation = formationValue;
                this.refreshFormationPreview();
            } else {
                c.pendingFormation = null;
            }
            this._advanceToConfirmation();
        },

        keepFormationWithPenalty() {
            this._advanceToConfirmation();
        },

        _advanceToConfirmation() {
            this.closeFormationPicker();
            ctx().showingConfirmation = true;
            this._scrollTacticalToTop();
        },
    };
}
