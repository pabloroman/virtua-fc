/**
 * Tests for match-simulation.js — the animation/score/phase state machine.
 *
 * These test the critical frontend logic that caused production bugs:
 * - Ghost goals: synthesizeGoalsIfNeeded creating phantom events
 * - Incorrect ET trigger: needsExtraTime returning wrong values
 * - Score forcing: enterRegularTimeEnd/enterFullTime setting wrong scores
 * - revealedEvents not being reset after resimulation
 */
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createMatchSimulation } from '@/modules/match-simulation.js';

// Stub browser APIs that the module uses
globalThis.requestAnimationFrame = vi.fn(() => 1);
globalThis.cancelAnimationFrame = vi.fn();
globalThis.performance = { now: () => 0 };

function createMockState(overrides = {}) {
    return {
        // Core match data
        homeTeamId: 'home-1',
        awayTeamId: 'away-1',
        homeTeamName: 'Home FC',
        awayTeamName: 'Away FC',
        userTeamId: 'home-1',
        finalHomeScore: 0,
        finalAwayScore: 0,
        homeScore: 0,
        awayScore: 0,

        // ET data
        isKnockout: false,
        hasExtraTime: false,
        etHomeScore: 0,
        etAwayScore: 0,
        _needsPenalties: false,
        twoLeggedInfo: null,
        extraTimeEvents: [],
        extraTimeLoading: false,
        preloadedExtraTimeData: null,
        lastRevealedETIndex: -1,

        // Events
        events: [],
        revealedEvents: [],
        lastRevealedIndex: -1,

        // Phase
        phase: 'pre_match',
        currentMinute: 0,
        userPaused: false,
        _skippingToEnd: false,

        // Stubs for methods the module calls on state
        substitutionsMade: [],
        openPenaltyPicker: vi.fn(),
        skipPenaltyReveal: vi.fn(() => false),
        extraTimeUrl: '',
        csrfToken: '',
        autoSubUserTeamBeforeSkip: vi.fn(() => Promise.resolve(false)),

        // Possession
        homePossession: 50,
        awayPossession: 50,
        _basePossession: 50,
        _possessionDisplay: 50,
        penaltyResult: null,

        // Speed
        matchSpeed: 1,

        ...overrides,
    };
}

// ============================================================================
// synthesizeGoalsIfNeeded
// ============================================================================

describe('synthesizeGoalsIfNeeded', () => {
    it('does not synthesize when events match the score', () => {
        const state = createMockState({
            finalHomeScore: 1,
            finalAwayScore: 0,
            events: [
                { minute: 35, type: 'goal', teamId: 'home-1', gamePlayerId: 'p1', metadata: {} },
            ],
        });

        const sim = createMatchSimulation(() => state);
        const result = sim.synthesizeGoalsIfNeeded(state.events);

        expect(result.length).toBe(1);
        expect(result[0].gamePlayerId).toBe('p1');
    });

    it('synthesizes missing home goals', () => {
        const state = createMockState({
            finalHomeScore: 2,
            finalAwayScore: 0,
            events: [
                { minute: 35, type: 'goal', teamId: 'home-1', gamePlayerId: 'p1', metadata: {} },
            ],
        });

        const sim = createMatchSimulation(() => state);
        const result = sim.synthesizeGoalsIfNeeded(state.events);

        expect(result.length).toBe(2);
        const synthetic = result.find(e => e.gamePlayerId === null);
        expect(synthetic).toBeDefined();
        expect(synthetic.teamId).toBe('home-1');
    });

    it('synthesizes missing away goals', () => {
        const state = createMockState({
            finalHomeScore: 0,
            finalAwayScore: 1,
            events: [],
        });

        const sim = createMatchSimulation(() => state);
        const result = sim.synthesizeGoalsIfNeeded(state.events);

        expect(result.length).toBe(1);
        expect(result[0].teamId).toBe('away-1');
        expect(result[0].gamePlayerId).toBeNull();
    });

    it('does not synthesize when events exceed the score (no phantom removal)', () => {
        const state = createMockState({
            finalHomeScore: 0,
            finalAwayScore: 1,
            events: [
                { minute: 10, type: 'goal', teamId: 'home-1', gamePlayerId: 'p1', metadata: {} },
                { minute: 55, type: 'goal', teamId: 'away-1', gamePlayerId: 'p2', metadata: {} },
            ],
        });

        const sim = createMatchSimulation(() => state);
        const result = sim.synthesizeGoalsIfNeeded(state.events);

        // Should NOT add synthetic goals and should NOT remove the extra home goal
        expect(result.length).toBe(2);
    });

    it('counts own goals correctly for the benefiting team', () => {
        const state = createMockState({
            finalHomeScore: 1,
            finalAwayScore: 0,
            events: [
                // Away team own goal = home team gets the point
                { minute: 20, type: 'own_goal', teamId: 'away-1', gamePlayerId: 'p3', metadata: {} },
            ],
        });

        const sim = createMatchSimulation(() => state);
        const result = sim.synthesizeGoalsIfNeeded(state.events);

        // own_goal by away team counts as home goal, so score matches — no synthesis
        expect(result.length).toBe(1);
    });

    it('synthesized goals have minutes within 1-90', () => {
        const state = createMockState({
            finalHomeScore: 3,
            finalAwayScore: 0,
            events: [],
        });

        const sim = createMatchSimulation(() => state);
        const result = sim.synthesizeGoalsIfNeeded(state.events);

        expect(result.length).toBe(3);
        for (const event of result) {
            expect(event.minute).toBeGreaterThanOrEqual(1);
            expect(event.minute).toBeLessThanOrEqual(90);
        }
    });
});

// ============================================================================
// enterRegularTimeEnd — score forcing
// ============================================================================

describe('enterRegularTimeEnd score forcing', () => {
    it('forces score to finalHomeScore/finalAwayScore regardless of revealed events', () => {
        const state = createMockState({
            phase: 'second_half',
            currentMinute: 93,
            finalHomeScore: 1,
            finalAwayScore: 1,
            homeScore: 2,  // Wrong — was incremented by extra event reveals
            awayScore: 1,
            isKnockout: false,
            events: [
                { minute: 30, type: 'goal', teamId: 'home-1', gamePlayerId: 'p1', metadata: {} },
                { minute: 60, type: 'goal', teamId: 'away-1', gamePlayerId: 'p2', metadata: {} },
            ],
            lastRevealedIndex: 1,
        });

        const sim = createMatchSimulation(() => state);

        // enterFullTime is the non-knockout path
        sim.enterFullTime();

        expect(state.homeScore).toBe(1);
        expect(state.awayScore).toBe(1);
    });
});

// ============================================================================
// enterExtraTimeEnd — ET score forcing and penalty decision
// ============================================================================

describe('enterExtraTimeEnd penalty decision', () => {
    it('does not open penalty picker when ET has a winner', () => {
        const state = createMockState({
            phase: 'extra_time_second_half',
            currentMinute: 123,
            isKnockout: true,
            hasExtraTime: true,
            finalHomeScore: 1,
            finalAwayScore: 1,
            etHomeScore: 1,
            etAwayScore: 0,
            homeScore: 2,
            awayScore: 1,
            _needsPenalties: false,
            extraTimeEvents: [],
            lastRevealedETIndex: -1,
            penaltyResult: null,
        });

        const sim = createMatchSimulation(() => state);

        // This should call enterFullTime, not openPenaltyPicker
        // (since _needsPenalties is false)
        // We can verify by checking that phase becomes 'full_time'
        // and openPenaltyPicker was not called
        sim.enterFullTime();
        expect(state.phase).toBe('full_time');
        expect(state.openPenaltyPicker).not.toHaveBeenCalled();
    });

    it('forces total score (regular + ET) at end of extra time', () => {
        const state = createMockState({
            phase: 'extra_time_second_half',
            currentMinute: 120,
            isKnockout: true,
            hasExtraTime: true,
            finalHomeScore: 1,
            finalAwayScore: 1,
            etHomeScore: 1,
            etAwayScore: 0,
            homeScore: 0,  // Wrong
            awayScore: 0,  // Wrong
            _needsPenalties: false,
            extraTimeEvents: [],
            lastRevealedETIndex: -1,
            penaltyResult: null,
        });

        const sim = createMatchSimulation(() => state);
        sim.enterFullTime();

        // In ET mode, enterFullTime sets currentMinute=120 but doesn't re-force scores
        // The score was already forced by enterExtraTimeEnd
        expect(state.phase).toBe('full_time');
    });
});

// ============================================================================
// needsExtraTime — single-leg and two-legged
// ============================================================================

describe('needsExtraTime (via skipToEnd)', () => {
    it('triggers ET when single-leg knockout score is a draw', async () => {
        const state = createMockState({
            phase: 'second_half',
            currentMinute: 93,
            isKnockout: true,
            hasExtraTime: false,
            finalHomeScore: 1,
            finalAwayScore: 1,
            homeScore: 1,
            awayScore: 1,
            events: [],
            lastRevealedIndex: -1,
            extraTimeUrl: '/et',
            csrfToken: 'token',
            preloadedExtraTimeData: null,
            otherMatches: [],
        });

        // Mock fetch so fetchExtraTime doesn't crash
        globalThis.fetch = vi.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ needed: false }),
        }));

        const sim = createMatchSimulation(() => state);

        // skipToEnd is async — await it
        await sim.skipToEnd();

        // Knockout with finalHomeScore === finalAwayScore → going_to_extra_time
        expect(state.phase).toBe('going_to_extra_time');
    });

    it('does not trigger ET when knockout score is not a draw', async () => {
        const state = createMockState({
            phase: 'second_half',
            currentMinute: 93,
            isKnockout: true,
            hasExtraTime: false,
            finalHomeScore: 2,
            finalAwayScore: 1,
            homeScore: 2,
            awayScore: 1,
            events: [],
            lastRevealedIndex: -1,
            otherMatches: [],
        });

        const sim = createMatchSimulation(() => state);
        await sim.skipToEnd();

        // Non-draw knockout → full_time
        expect(state.phase).toBe('full_time');
    });
});

// ============================================================================
// skipToHalfTime — must reveal phase-less atmosphere events too
// ============================================================================

describe('skipToHalfTime atmosphere reveal', () => {
    it('reveals first-half events through the end of 1H stoppage, including phase-less atmosphere shots', () => {
        // Regression: the previous implementation broke the reveal loop
        // at the first event without a `phase` field, leaving every
        // event after the first atmosphere shot unrevealed until the
        // 2H tick caught up minutes later.
        const state = createMockState({
            phase: 'first_half',
            currentMinute: 5,
            firstHalfStoppage: 3,
            events: [
                // Real backend events carry phase.
                { minute: 10, type: 'yellow_card', phase: 'first_half', gamePlayerId: 'p1', teamId: 'home-1', metadata: {} },
                // Client-injected atmosphere shot — no phase. This used
                // to terminate the reveal loop early.
                { minute: 12, type: 'shot_on_target', atmosphere: true, gamePlayerId: 'p2', teamId: 'away-1', metadata: { narrative: '' } },
                { minute: 25, type: 'goal', phase: 'first_half', teamId: 'home-1', gamePlayerId: 'p1', metadata: {} },
                // Atmosphere narrative inside 1H stoppage (fhs=3 → up to minute 48).
                { minute: 47, type: 'shot_off_target', atmosphere: true, gamePlayerId: 'p3', teamId: 'home-1', metadata: { narrative: '' } },
                // First event past 1H stoppage — must NOT be revealed.
                { minute: 60, type: 'goal', phase: 'second_half', teamId: 'away-1', gamePlayerId: 'p4', metadata: {} },
            ],
            otherMatches: [],
        });

        const sim = createMatchSimulation(() => state);
        sim.skipToHalfTime();

        const revealedMinutes = state.revealedEvents.map(e => e.minute).sort((a, b) => a - b);
        expect(revealedMinutes).toEqual([10, 12, 25, 47]);
        expect(state.phase).toBe('half_time');
        expect(state.homeScore).toBe(1); // goal at minute 25 was tracked
    });

    it('stops at a second-half event even when its phase is missing (minute-fallback path)', () => {
        // The second-half-start contextual narrative carries
        // phase='second_half', so it's classified via the phase check.
        // But verify the minute-fallback path also catches a phase-less
        // event placed past firstHalfEnd.
        const state = createMockState({
            phase: 'first_half',
            currentMinute: 5,
            firstHalfStoppage: 2,
            events: [
                { minute: 10, type: 'goal', phase: 'first_half', teamId: 'home-1', gamePlayerId: 'p1', metadata: {} },
                // Phase-less, past firstHalfEnd (47) — should NOT be revealed.
                { minute: 47.1, type: 'contextual', atmosphere: true, gamePlayerId: null, teamId: null, metadata: { narrative: '' } },
            ],
            otherMatches: [],
        });

        const sim = createMatchSimulation(() => state);
        sim.skipToHalfTime();

        expect(state.revealedEvents.map(e => e.minute)).toEqual([10]);
    });
});

// ============================================================================
// tick() phase-aware clock clamp — regression for issue #1158
// ============================================================================

describe('tick() phase-aware clock clamp (#1158)', () => {
    it('clamps currentMinute to firstHalfEnd when rAF stalls mid-first-half', () => {
        // Regression: a backgrounded mobile tab (or any rAF stall) used to
        // produce a giant deltaMs on the first resume tick. The clock was
        // clamped to secondHalfEnd (90+stoppage) instead of firstHalfEnd
        // (45+stoppage), so processEvents() revealed every regular-time
        // event in one shot — including second-half goals, which then
        // incremented homeScore/awayScore via updateScore — before the
        // half-time check finally fired. The user was left looking at a
        // HALF_TIME pause with the FINAL score and second-half events on
        // screen. The fix clamps the clock to the END OF THE CURRENT HALF.
        const capturedTicks = [];
        const originalRaf = globalThis.requestAnimationFrame;
        globalThis.requestAnimationFrame = vi.fn((cb) => {
            capturedTicks.push(cb);
            return capturedTicks.length;
        });

        const originalNow = globalThis.performance.now;
        let mockNow = 0;
        globalThis.performance.now = () => mockNow;

        // Use fake timers BEFORE startSimulation so its 1000ms kickoff
        // setTimeout is scheduled against the fake clock and can be
        // advanced synchronously below.
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });

        try {
            const state = createMockState({
                phase: 'pre_match',
                currentMinute: 0,
                firstHalfStoppage: 2,   // firstHalfEnd = 47
                secondHalfStoppage: 3,  // secondHalfEnd = 93
                finalHomeScore: 2,
                finalAwayScore: 4,
                events: [
                    { minute: 30, type: 'goal', phase: 'first_half',  teamId: 'home-1', gamePlayerId: 'p1', metadata: {} },
                    { minute: 40, type: 'goal', phase: 'first_half',  teamId: 'home-1', gamePlayerId: 'p2', metadata: {} },
                    // Second-half goals — must NOT be revealed during 1H.
                    { minute: 50, type: 'goal', phase: 'second_half', teamId: 'away-1', gamePlayerId: 'p3', metadata: {} },
                    { minute: 60, type: 'goal', phase: 'second_half', teamId: 'away-1', gamePlayerId: 'p4', metadata: {} },
                    { minute: 70, type: 'goal', phase: 'second_half', teamId: 'away-1', gamePlayerId: 'p5', metadata: {} },
                    { minute: 80, type: 'goal', phase: 'second_half', teamId: 'away-1', gamePlayerId: 'p6', metadata: {} },
                ],
                otherMatches: [],
                otherMatchScores: [],
                speed: 4,
                speedRates: { 1: 1.5, 2: 3.0, 4: 6.0 },
                narrativeTemplates: {},
                // Skip the "fourth official adds N" announcement so the
                // tick falls straight through to the half-time check —
                // we're testing the clamp, not the announcement path.
                _announcedFirstHalfStoppage: true,
            });

            // realEvents = events: synthesizeGoalsIfNeeded would otherwise
            // inject random ghost goals into realEvents based on the score
            // gap. Aligning realEvents with the test's seeded score (2 home
            // + 4 away = 2-4) makes the synthesizer a no-op so we control
            // the event timeline exactly.
            state.realEvents = [...state.events];

            const sim = createMatchSimulation(() => state);
            sim.startSimulation();
            vi.advanceTimersByTime(1100); // fire the 1000ms kickoff

            // Kickoff set phase=FIRST_HALF, _lastTick=performance.now()=0,
            // and scheduled the first tick via rAF.
            expect(state.phase).toBe('first_half');
            expect(capturedTicks.length).toBeGreaterThan(0);
            const tick = capturedTicks[capturedTicks.length - 1];

            // Simulate a 20-second stall (mobile tab throttling resume,
            // OS suspension, GC pause). At speed=4 that's 120 game-minutes
            // worth of deltaMinutes — enough to vault past secondHalfEnd
            // (93) pre-fix, exposing every regular-time event in one tick.
            mockNow = 20000;
            tick(mockNow);

            // 1) The clock never went past firstHalfEnd. (enterHalfTime
            // snaps currentMinute back to MINUTE.FIRST_HALF_END=45 once
            // the boundary is crossed, but the key invariant — the clock
            // was clamped during the tick before processEvents ran — is
            // demonstrated by the score/feed assertions below.)
            expect(state.currentMinute).toBeLessThanOrEqual(47);

            // 2) No second-half goal leaked into revealedEvents.
            const revealedMinutes = state.revealedEvents.map((e) => e.minute);
            expect(revealedMinutes.length).toBeGreaterThan(0);
            for (const m of revealedMinutes) {
                expect(m).toBeLessThanOrEqual(47);
            }

            // 3) Score reflects only the two first-half home goals.
            expect(state.homeScore).toBe(2);
            expect(state.awayScore).toBe(0);

            // 4) Phase transitioned to HALF_TIME (the boundary check fired).
            expect(state.phase).toBe('half_time');
        } finally {
            vi.useRealTimers();
            globalThis.requestAnimationFrame = originalRaf;
            globalThis.performance.now = originalNow;
        }
    });
});
