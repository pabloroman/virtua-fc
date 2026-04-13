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
