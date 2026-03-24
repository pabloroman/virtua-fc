/**
 * Penalty shootout module.
 *
 * Manages the penalty picker UI, kicker selection, server submission,
 * and kick-by-kick animated reveal. Self-contained: only reads from ctx()
 * for lineup/bench data and writes penalty-specific state back.
 *
 * @param {Function} ctx - Returns the Alpine component instance
 */
export function createPenaltyShootout(ctx) {
    let _revealTimer = null;

    function openPenaltyPicker() {
        const state = ctx();
        state.selectedPenaltyKickers = [];
        state.penaltyPickerOpen = true;
        document.body.classList.add('overflow-y-hidden');
    }

    function getAvailablePenaltyPlayers() {
        const state = ctx();
        const selectedIds = state.selectedPenaltyKickers.map(k => k.id);
        const confirmedOutIds = state.substitutionsMade.map(s => s.playerOutId);
        const confirmedInIds = state.substitutionsMade.map(s => s.playerInId);
        const redCarded = state.redCardedPlayerIds;

        // Original lineup players still on pitch
        const onPitch = state.lineupPlayers.filter(p =>
            !confirmedOutIds.includes(p.id) && !redCarded.includes(p.id) && !selectedIds.includes(p.id)
        );
        // Players who came on via substitution
        const subsOnPitch = state.benchPlayers.filter(p =>
            confirmedInIds.includes(p.id) && !confirmedOutIds.includes(p.id)
            && !redCarded.includes(p.id) && !selectedIds.includes(p.id)
        );

        return [...onPitch, ...subsOnPitch]
            .sort((a, b) => (b.overallScore ?? 50) - (a.overallScore ?? 50));
    }

    function addPenaltyKicker(player) {
        const state = ctx();
        if (state.selectedPenaltyKickers.length >= 5) return;
        state.selectedPenaltyKickers.push({ ...player });
    }

    function removePenaltyKicker(index) {
        ctx().selectedPenaltyKickers.splice(index, 1);
    }

    async function confirmPenaltyKickers() {
        const state = ctx();
        if (state.selectedPenaltyKickers.length < 5 || state.penaltyProcessing) return;
        state.penaltyProcessing = true;

        const kickerOrder = state.selectedPenaltyKickers.map(k => k.id);

        try {
            const response = await fetch(state.penaltiesUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': state.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ kickerOrder }),
            });

            if (!response.ok) {
                console.error('Penalty request failed');
                state.penaltyProcessing = false;
                return;
            }

            const result = await response.json();

            state.penaltyResult = {
                home: result.homeScore,
                away: result.awayScore,
            };
            state.penaltyKicks = result.kicks || [];
            state._needsPenalties = false;

            // Close picker and start kick-by-kick reveal
            state.penaltyPickerOpen = false;
            document.body.classList.remove('overflow-y-hidden');
            revealPenaltyKicks();
        } catch (err) {
            console.error('Penalty request failed:', err);
        } finally {
            state.penaltyProcessing = false;
        }
    }

    function revealPenaltyKicks() {
        const state = ctx();
        state.revealedPenaltyKicks = [];
        state.penaltyPreparing = false;
        state.nextPenaltyKicker = null;
        let idx = 0;

        const showPreparing = () => {
            if (idx >= state.penaltyKicks.length) {
                state.penaltyPreparing = false;
                state.nextPenaltyKicker = null;
                // All kicks revealed, transition to full time
                _revealTimer = setTimeout(() => state.enterFullTime(), 2000);
                return;
            }

            // Show "preparing to shoot" with the next kicker's info
            state.nextPenaltyKicker = state.penaltyKicks[idx];
            state.penaltyPreparing = true;

            // After the preparation phase, reveal the result
            _revealTimer = setTimeout(() => {
                state.penaltyPreparing = false;
                state.nextPenaltyKicker = null;
                state.revealedPenaltyKicks.push(state.penaltyKicks[idx]);
                idx++;

                // Pause before next kicker prepares
                _revealTimer = setTimeout(showPreparing, 600);
            }, 1500);
        };

        // Start after a short pause
        _revealTimer = setTimeout(showPreparing, 800);
    }

    /**
     * Fast-forward penalty reveal (called by skipToEnd).
     * Returns true if there were penalties to fast-forward.
     */
    function skipPenaltyReveal() {
        const state = ctx();
        if (state.phase === 'penalties' && state.penaltyKicks.length > 0
            && state.revealedPenaltyKicks.length < state.penaltyKicks.length) {
            clearTimeout(_revealTimer);
            state.penaltyPreparing = false;
            state.nextPenaltyKicker = null;
            state.revealedPenaltyKicks = [...state.penaltyKicks];
            setTimeout(() => state.enterFullTime(), 500);
            return true;
        }
        return false;
    }

    function getPenaltyHomeScore() {
        const state = ctx();
        if (state.penaltyKicks.length > 0) {
            return state.revealedPenaltyKicks.filter(k => k.side === 'home' && k.scored).length;
        }
        return state.penaltyResult ? state.penaltyResult.home : 0;
    }

    function getPenaltyAwayScore() {
        const state = ctx();
        if (state.penaltyKicks.length > 0) {
            return state.revealedPenaltyKicks.filter(k => k.side === 'away' && k.scored).length;
        }
        return state.penaltyResult ? state.penaltyResult.away : 0;
    }

    function getPenaltyWinner() {
        const state = ctx();
        if (!state.penaltyResult) return null;
        const homeWon = state.penaltyResult.home > state.penaltyResult.away;
        return {
            name: homeWon ? state.homeTeamName : state.awayTeamName,
            image: homeWon ? state.homeTeamImage : state.awayTeamImage,
        };
    }

    function destroyTimers() {
        clearTimeout(_revealTimer);
    }

    return {
        openPenaltyPicker,
        get availablePenaltyPlayers() { return getAvailablePenaltyPlayers(); },
        addPenaltyKicker,
        removePenaltyKicker,
        confirmPenaltyKickers,
        revealPenaltyKicks,
        skipPenaltyReveal,
        get penaltyHomeScore() { return getPenaltyHomeScore(); },
        get penaltyAwayScore() { return getPenaltyAwayScore(); },
        get penaltyWinner() { return getPenaltyWinner(); },
        _destroyPenaltyTimers: destroyTimers,
    };
}
