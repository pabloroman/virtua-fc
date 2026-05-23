/**
 * Integration tests for tactical-submission.js — drives confirmAllChanges
 * end-to-end with the REAL atmosphere glue wired against a mock state.
 *
 * The historical bug this guards against: when the user triggers a
 * tactical change (or skip-to-end auto-sub), the server may resimulate
 * the remainder and emit new red cards / injuries inside
 * `result.newEvents`. Pre-refactor, atmosphere shots for the remaining
 * period were regenerated BEFORE those new events were merged into the
 * canonical event list, so a sent-off player could still be picked as
 * the actor of a later shot. Post-refactor, atmosphere is derived from
 * `c.realEvents` only after all server events are merged.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createTacticalSubmission } from '@/modules/tactical-submission.js';
import { createAtmosphereGlue } from '@/modules/atmosphere-glue.js';

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

function createMockState(overrides = {}) {
    const state = {
        // Network
        tacticalActionsUrl: '/tactical-actions',
        csrfToken: 'test-token',
        translations: {},
        tacticalError: null,
        applyingChanges: false,

        // Match identity
        homeTeamId: 'home-1',
        awayTeamId: 'away-1',
        homeTeamName: 'Home FC',
        awayTeamName: 'Away FC',
        userTeamId: 'home-1',
        finalHomeScore: 1,
        finalAwayScore: 0,
        homeScore: 0,
        awayScore: 0,

        // Clock
        currentMinute: 10,
        phase: 'first_half',

        // Lineups
        homeLineupRoster: makeRoster('home', 'home-1'),
        awayLineupRoster: makeRoster('away', 'away-1'),
        benchPlayers: [],
        opponentBenchPlayers: [],

        // Pending tactical state
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

        // Atmosphere-glue inputs (the real glue is wired against this
        // state below, so it reads these directly).
        homeArticle: 'el',
        awayArticle: 'el',
        narrativeTemplates: {
            shotOnTarget: ['Shot by :player'],
            shotOffTarget: ['Wide by :player'],
            goalAssisted: ['Goal by :player'],
            goalSolo: ['Goal by :player'],
            goalPrefix: [''],
        },
        isKnockout: false,
        isTwoLeggedTie: false,
        opponentPlayingStyle: 'balanced',
        opponentPressing: 'standard',
        opponentDefLine: 'normal',
        opponentMentality: 'balanced',

        // Legacy mock _atmosphereConfig — overwritten by the real glue
        // wiring below so tests exercise the production code path. Kept
        // as a defensive fallback for callers that read it directly.
        _atmosphereConfig() {
            return {
                homeTeamId: 'home-1',
                awayTeamId: 'away-1',
                homeTeamName: 'Home FC',
                awayTeamName: 'Away FC',
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
                userTeamId: 'home-1',
                tactics: {},
            };
        },

        // Canonical real events + derived atmosphere buckets. The merged
        // `events` / `extraTimeEvents` arrays are rebuilt by the recompute
        // helpers (mocked below).
        realEvents: [],
        realExtraTimeEvents: [],
        atmosphereEvents: [],
        atmosphereExtraTimeEvents: [],
        events: [],
        extraTimeEvents: [],
        revealedEvents: [],
        lastRevealedIndex: -1,
        lastRevealedETIndex: -1,

        // Method stubs
        addPendingSub: vi.fn(),
        closeTacticalPanel: vi.fn(),
        recalculateScore: vi.fn(),
        resetPossessionTarget: vi.fn(),
        recalculatePlayerRatings: vi.fn(),
        synthesizeGoalsIfNeeded: (events) => events,
        ...overrides,
    };

    // Wire the REAL atmosphere glue against this state so recompute
    // exercises the actual shot generator. Stubbing these away would
    // bypass the code path that historically had the bug.
    const ctx = () => state;
    const glue = createAtmosphereGlue(ctx);
    state._atmosphereConfig = glue._atmosphereConfig;
    state.recomputeRegularAtmosphere = glue.recomputeRegularAtmosphere;
    state.recomputeETAtmosphere = glue.recomputeETAtmosphere;

    return state;
}

describe('tactical-submission confirmAllChanges', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it('does not pick a player sent off in the resimulated remainder for later atmosphere shots', async () => {
        // Scenario: user makes a tactical change at minute 10. The server
        // resimulates and emits a red card for the away team's CM1 at
        // minute 40. Atmosphere shots regenerated for minutes 11–90 must
        // not be attributed to away-cm1 after minute 40.
        const tacticalMinute = 10;
        const state = createMockState({ currentMinute: tacticalMinute });

        // Mock fetch — return a resimulation result with a red card.
        globalThis.fetch = vi.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({
                isExtraTime: false,
                substitutions: [],
                newEvents: [
                    {
                        minute: 40,
                        type: 'red_card',
                        gamePlayerId: 'away-cm1',
                        teamId: 'away-1',
                        playerName: 'away cm1',
                        metadata: { second_yellow: true },
                    },
                ],
                newScore: { home: 1, away: 0 },
                homePossession: 50,
                awayPossession: 50,
            }),
        }));

        // The shot generator is random; run multiple times to catch
        // intermittent regressions.
        for (let i = 0; i < 25; i++) {
            const iterState = createMockState({ currentMinute: tacticalMinute });
            const submission = createTacticalSubmission(() => iterState);
            await submission.confirmAllChanges();

            const offendingShot = iterState.events.find(e =>
                (e.type === 'shot_on_target' || e.type === 'shot_off_target')
                && e.gamePlayerId === 'away-cm1'
                && e.minute > 40
            );

            expect(
                offendingShot,
                `iteration ${i}: shot at ${offendingShot?.minute}' attributed to red-carded away-cm1`,
            ).toBeUndefined();
        }
    });
});
