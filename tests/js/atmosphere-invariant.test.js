/**
 * Invariant test for atmosphere-event correctness across mutation paths.
 *
 * The contract this test locks down:
 *   For every atmosphere event (shot_on_target, shot_off_target, etc.)
 *   that names a specific player (gamePlayerId !== null), that player
 *   MUST be on the pitch at the event's minute. They cannot have been:
 *     - red-carded earlier (type: 'red_card')
 *     - injured earlier (type: 'injury')
 *     - substituted off earlier (type: 'substitution', player_out)
 *     - a substitute who hasn't entered yet (type: 'substitution', player_in,
 *       atmosphere event at minute before sub minute)
 *
 * This contract is checked centrally by `assertAtmosphereInvariant`, then
 * exercised across the actual mutation paths: initial load, tactical-change
 * resimulation, skip-to-end resimulation. Any new mutator added to the
 * system should add a scenario here and call the same checker.
 *
 * Designed as the safety net for the "atmosphere as derived view" refactor:
 * it currently passes against the post-bug-fix code, and must continue to
 * pass after each refactor stage.
 */
import { describe, it, expect, vi } from 'vitest';
import {
    generateRegularTimeAtmosphere,
    generateExtraTimeAtmosphere,
    addGoalNarratives,
} from '@/modules/atmosphere-generator.js';
import { createTacticalSubmission } from '@/modules/tactical-submission.js';

// =============================================================================
// Contract — independent definition, NOT calling into atmosphere-generator's
// internal helpers. If the implementation changes how it tracks off-pitch
// players, this test still asserts the user-facing invariant.
// =============================================================================

function isPlayerOffPitchAt(playerId, realEvents, minute) {
    for (const e of realEvents) {
        if (e.minute > minute) continue;
        if (e.gamePlayerId !== playerId) continue;
        if (e.type === 'red_card' || e.type === 'injury') return true;
        if (e.type === 'substitution') return true; // gamePlayerId = player OUT
    }
    return false;
}

function isSubstituteBeforeEntry(playerId, realEvents, minute) {
    for (const e of realEvents) {
        if (e.type !== 'substitution') continue;
        const playerInId = e.metadata?.player_in_id;
        if (playerInId !== playerId) continue;
        if (minute < e.minute) return true;
    }
    return false;
}

function assertAtmosphereInvariant(allEvents, context = '') {
    const realEvents = allEvents.filter(e => !e.atmosphere);
    const atmosphereEvents = allEvents.filter(e => e.atmosphere && e.gamePlayerId);

    for (const ev of atmosphereEvents) {
        const wasOff = isPlayerOffPitchAt(ev.gamePlayerId, realEvents, ev.minute);
        expect(
            wasOff,
            `${context}: atmosphere ${ev.type} at ${ev.minute}' by ${ev.gamePlayerId} who was already off-pitch`,
        ).toBe(false);

        const notYetOn = isSubstituteBeforeEntry(ev.gamePlayerId, realEvents, ev.minute);
        expect(
            notYetOn,
            `${context}: atmosphere ${ev.type} at ${ev.minute}' by sub ${ev.gamePlayerId} who hasn't entered yet`,
        ).toBe(false);
    }
}

// =============================================================================
// Seeded RNG — deterministic test runs. Mulberry32; tiny and good enough.
// =============================================================================

function makeRng(seed) {
    let state = seed >>> 0;
    return () => {
        state = (state + 0x6D2B79F5) >>> 0;
        let t = state;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

function pickInt(rng, min, max) {
    return min + Math.floor(rng() * (max - min + 1));
}

// =============================================================================
// Fixtures
// =============================================================================

function makeRoster(prefix, teamId) {
    const positions = [
        ['gk',  'Goalkeeper'],
        ['cb1', 'Defender'], ['cb2', 'Defender'],
        ['lb',  'Defender'], ['rb',  'Defender'],
        ['cm1', 'Midfielder'], ['cm2', 'Midfielder'], ['cm3', 'Midfielder'],
        ['fw1', 'Forward'], ['fw2', 'Forward'], ['fw3', 'Forward'],
    ];
    return positions.map(([slot, group]) => ({
        id: `${prefix}-${slot}`,
        name: `${prefix} ${slot}`,
        positionGroup: group,
        teamId,
    }));
}

function baseAtmosphereConfig(overrides = {}) {
    return {
        homeTeamId: 'home-1',
        awayTeamId: 'away-1',
        homeTeamName: 'Home FC',
        awayTeamName: 'Away FC',
        homeArticle: 'el',
        awayArticle: 'el',
        homePlayers: makeRoster('home', 'home-1'),
        awayPlayers: makeRoster('away', 'away-1'),
        homeScore: 2,
        awayScore: 1,
        narrativeTemplates: {
            shotOnTarget: ['Shot by :player'],
            shotOffTarget: ['Wide by :player'],
            goalAssisted: ['Goal by :player'],
            goalSolo: ['Goal by :player'],
            goalPrefix: [''],
        },
        userTeamId: 'home-1',
        tactics: {},
        allEvents: [],
        ...overrides,
    };
}

function createMockState(overrides = {}) {
    const state = {
        tacticalActionsUrl: '/tactical-actions',
        csrfToken: 'test-token',
        translations: {},
        tacticalError: null,
        applyingChanges: false,

        homeTeamId: 'home-1',
        awayTeamId: 'away-1',
        homeTeamName: 'Home FC',
        awayTeamName: 'Away FC',
        userTeamId: 'home-1',
        finalHomeScore: 2,
        finalAwayScore: 1,
        homeScore: 0,
        awayScore: 0,

        currentMinute: 10,
        phase: 'first_half',

        homeLineupRoster: makeRoster('home', 'home-1'),
        awayLineupRoster: makeRoster('away', 'away-1'),
        benchPlayers: [],
        opponentBenchPlayers: [],

        pendingSubs: [],
        pendingFormation: null,
        pendingMentality: null,
        pendingPlayingStyle: null,
        pendingPressing: null,
        pendingDefLine: null,
        hasTacticalChanges: false,
        hasPendingChanges: true,
        showingConfirmation: true,
        _manualSlotPins: {},
        substitutionsMade: [],
        activeFormation: '4-3-3',
        activeMentality: 'balanced',
        activePlayingStyle: 'balanced',
        activePressing: 'standard',
        activeDefLine: 'normal',

        _atmosphereConfig() {
            return {
                homeTeamId: this.homeTeamId,
                awayTeamId: this.awayTeamId,
                homeTeamName: this.homeTeamName,
                awayTeamName: this.awayTeamName,
                homeArticle: 'el',
                awayArticle: 'el',
                homePlayers: this.homeLineupRoster,
                awayPlayers: this.awayLineupRoster,
                homeScore: this.finalHomeScore,
                awayScore: this.finalAwayScore,
                narrativeTemplates: {
                    shotOnTarget: ['Shot by :player'],
                    shotOffTarget: ['Wide by :player'],
                    goalAssisted: ['Goal by :player'],
                    goalSolo: ['Goal by :player'],
                    goalPrefix: [''],
                },
                userTeamId: this.userTeamId,
                tactics: {},
            };
        },

        events: [],
        extraTimeEvents: [],
        revealedEvents: [],
        lastRevealedIndex: -1,

        addPendingSub: vi.fn(),
        closeTacticalPanel: vi.fn(),
        recalculateScore: vi.fn(),
        resetPossessionTarget: vi.fn(),
        recalculatePlayerRatings: vi.fn(),
        synthesizeGoalsIfNeeded: (events) => events,
    };
    return Object.assign(state, overrides);
}

// =============================================================================
// Scenario drivers — each one exercises a real mutation path and runs the
// invariant against the resulting state.
// =============================================================================

function runInitialLoadScenario(initialRealEvents, label) {
    // Mirrors atmosphere-glue.js _injectAtmosphere: starting from a fresh
    // c.events containing only server real events, inject atmosphere.
    const events = [...initialRealEvents];
    const cfg = baseAtmosphereConfig({ allEvents: events });
    addGoalNarratives(events, cfg);
    const atmosphere = generateRegularTimeAtmosphere({ ...cfg, allEvents: events });
    const merged = [...events, ...atmosphere].sort((a, b) => a.minute - b.minute);
    assertAtmosphereInvariant(merged, label);
}

function runETInjectionScenario(regularEvents, etEvents, label) {
    // Mirrors atmosphere-glue.js _injectETAtmosphere.
    const cfg = baseAtmosphereConfig({
        allEvents: [...regularEvents, ...etEvents],
    });
    addGoalNarratives(etEvents, cfg);
    const allEvents = [...regularEvents, ...etEvents];
    const atmosphere = generateExtraTimeAtmosphere({ ...cfg, allEvents });
    const mergedET = [...etEvents, ...atmosphere].sort((a, b) => a.minute - b.minute);
    assertAtmosphereInvariant([...regularEvents, ...mergedET], label);
}

async function runTacticalChangeScenario(initialEvents, tacticalMinute, newEvents, label) {
    const state = createMockState({
        currentMinute: tacticalMinute,
        events: [...initialEvents],
    });
    globalThis.fetch = vi.fn(() => Promise.resolve({
        ok: true,
        json: () => Promise.resolve({
            isExtraTime: false,
            substitutions: [],
            newEvents,
            newScore: { home: 2, away: 1 },
            homePossession: 50,
            awayPossession: 50,
        }),
    }));
    const submission = createTacticalSubmission(() => state);
    await submission.confirmAllChanges();
    assertAtmosphereInvariant(state.events, label);
}

// =============================================================================
// Generators — produce random-but-deterministic event lists
// =============================================================================

function generateRandomFirstHalfEvents(rng) {
    const events = [];
    const yellowMinute = pickInt(rng, 15, 35);
    const redMinute = pickInt(rng, yellowMinute + 1, 44);

    events.push({
        minute: yellowMinute,
        type: 'yellow_card',
        gamePlayerId: 'away-cm1',
        teamId: 'away-1',
        playerName: 'away cm1',
        metadata: {},
    });
    events.push({
        minute: redMinute,
        type: 'red_card',
        gamePlayerId: 'away-cm1',
        teamId: 'away-1',
        playerName: 'away cm1',
        metadata: { second_yellow: true },
    });
    if (rng() < 0.5) {
        events.push({
            minute: pickInt(rng, redMinute + 1, 45),
            type: 'goal',
            gamePlayerId: 'home-fw1',
            teamId: 'home-1',
            playerName: 'home fw1',
            metadata: {},
        });
    }
    return events.sort((a, b) => a.minute - b.minute);
}

function generateRandomSecondHalfNewEvents(rng, afterMinute) {
    const events = [];
    // 50% chance the second half adds another red card on the same team.
    if (rng() < 0.5) {
        events.push({
            minute: pickInt(rng, Math.max(46, afterMinute + 1), 80),
            type: 'red_card',
            gamePlayerId: 'home-cm1',
            teamId: 'home-1',
            playerName: 'home cm1',
            metadata: {},
        });
    }
    // 50% chance of a goal scorer.
    if (rng() < 0.5) {
        events.push({
            minute: pickInt(rng, afterMinute + 1, 89),
            type: 'goal',
            gamePlayerId: 'away-fw1',
            teamId: 'away-1',
            playerName: 'away fw1',
            metadata: {},
        });
    }
    return events.sort((a, b) => a.minute - b.minute);
}

// =============================================================================
// Tests
// =============================================================================

describe('atmosphere invariant — initial load', () => {
    it('holds for empty events', () => {
        runInitialLoadScenario([], 'empty');
    });

    it('holds when a player is red-carded mid-first-half', () => {
        const events = [
            { minute: 32, type: 'yellow_card', gamePlayerId: 'away-cm1', teamId: 'away-1', playerName: 'away cm1', metadata: {} },
            { minute: 40, type: 'red_card', gamePlayerId: 'away-cm1', teamId: 'away-1', playerName: 'away cm1', metadata: { second_yellow: true } },
        ];
        for (let i = 0; i < 30; i++) runInitialLoadScenario(events, `red-card-iter-${i}`);
    });

    it('holds across 50 random seeded scenarios', () => {
        for (let seed = 1; seed <= 50; seed++) {
            const rng = makeRng(seed);
            const events = generateRandomFirstHalfEvents(rng);
            runInitialLoadScenario(events, `seed-${seed}`);
        }
    });
});

describe('atmosphere invariant — tactical-change resimulation', () => {
    it('holds when the resimulation emits a red card at minute 40, tactical change at minute 10', async () => {
        const newEvents = [
            { minute: 40, type: 'red_card', gamePlayerId: 'away-cm1', teamId: 'away-1', playerName: 'away cm1', metadata: { second_yellow: true } },
        ];
        for (let i = 0; i < 20; i++) {
            await runTacticalChangeScenario([], 10, newEvents, `iter-${i}`);
        }
    });

    it('holds when both pre-existing and resimulated events contain red cards', async () => {
        const initial = [
            { minute: 20, type: 'red_card', gamePlayerId: 'home-cm1', teamId: 'home-1', playerName: 'home cm1', metadata: {} },
        ];
        const newEvents = [
            { minute: 50, type: 'red_card', gamePlayerId: 'away-cm1', teamId: 'away-1', playerName: 'away cm1', metadata: {} },
        ];
        for (let i = 0; i < 20; i++) {
            await runTacticalChangeScenario(initial, 30, newEvents, `dual-red-iter-${i}`);
        }
    });

    it('holds across 30 random seeded scenarios', async () => {
        for (let seed = 1; seed <= 30; seed++) {
            const rng = makeRng(seed);
            const initial = generateRandomFirstHalfEvents(rng);
            const tacticalMinute = pickInt(rng, 1, 5); // before the red card
            const newEvents = generateRandomSecondHalfNewEvents(rng, tacticalMinute);
            await runTacticalChangeScenario(initial, tacticalMinute, newEvents, `seed-${seed}`);
        }
    });
});

describe('atmosphere invariant — extra time', () => {
    it('holds when a player is red-carded in extra time', () => {
        const regularEvents = [];
        const etEvents = [
            { minute: 100, type: 'red_card', gamePlayerId: 'home-cm1', teamId: 'home-1', playerName: 'home cm1', metadata: {} },
        ];
        for (let i = 0; i < 20; i++) runETInjectionScenario(regularEvents, etEvents, `et-red-iter-${i}`);
    });

    it('honors a regular-time red card when generating ET atmosphere', () => {
        const regularEvents = [
            { minute: 70, type: 'red_card', gamePlayerId: 'away-fw1', teamId: 'away-1', playerName: 'away fw1', metadata: {} },
        ];
        const etEvents = [];
        for (let i = 0; i < 20; i++) runETInjectionScenario(regularEvents, etEvents, `et-cross-iter-${i}`);
    });
});
