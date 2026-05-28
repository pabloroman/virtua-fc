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
        // Real implementation (mirrors match-simulation's exported helper) so
        // the single-writer invariant for substitutionsMade is exercised here
        // rather than masked behind a vi.fn().
        trackSubstitutionIfNeeded(event) {
            if (event.type !== 'substitution' || event.teamId !== this.userTeamId) return;
            this.substitutionsMade.push({
                playerOutId: event.gamePlayerId,
                playerInId: event.metadata?.player_in_id ?? '',
                minute: event.minute,
                playerOutName: event.playerName ?? '',
                playerInName: event.playerInName ?? '',
            });
            const playerInId = event.metadata?.player_in_id;
            if (playerInId) {
                const benchPlayer = this.benchPlayers.find(p => p.id === playerInId);
                if (benchPlayer) {
                    benchPlayer.minuteEntered = event.minute;
                }
            }
        },
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

    it('half-time sub: lastRevealedIndex includes the sub event so the next tick does not re-reveal it', async () => {
        // Regression for #1130's interaction with the derived-atmosphere
        // model. At half-time enterHalfTime snaps currentMinute back to 45,
        // but the user has already watched events through 45+fhs. The sub
        // event the backend stamps at phase=SECOND_HALF/base=45 has
        // absolute minute = 45+fhs. recomputeRegularAtmosphere rebuilds
        // c.events with the sub at minute=45+fhs; lastRevealedIndex must
        // be computed against the effective submission minute (also 45+fhs),
        // NOT c.currentMinute (45), otherwise the next 2H tick re-reveals
        // the sub event and the user sees it twice.
        const fhs = 4;
        const submissionMinute = 45 + fhs; // effective minute at half-time
        const state = createMockState({
            phase: 'half_time',
            currentMinute: 45,
            firstHalfStoppage: fhs,
        });

        globalThis.fetch = vi.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({
                isExtraTime: false,
                substitutions: [{
                    playerOutId: 'home-fw1',
                    playerInId: 'home-sub1',
                    playerOutName: 'home fw1',
                    playerInName: 'Sub Player',
                    teamId: 'home-1',
                    minute: submissionMinute,
                    displayMinute: "45'",
                    phase: 'second_half',
                }],
                newEvents: [],
                newScore: { home: 1, away: 0 },
                homePossession: 50,
                awayPossession: 50,
            }),
        }));

        // Pretend the user added a pending sub (the real flow gates the
        // POST on `c.pendingSubs.length > 0`, but the mocked fetch ignores
        // the payload — we just need the function to push through).
        state.pendingSubs = [{
            playerOut: { id: 'home-fw1' },
            playerIn: { id: 'home-sub1' },
        }];

        const submission = createTacticalSubmission(() => state);
        await submission.confirmAllChanges();

        const subEvent = state.events.find(e =>
            e.type === 'substitution' && e.gamePlayerId === 'home-fw1'
        );
        expect(subEvent, 'sub event should be in the merged events array').toBeDefined();
        expect(subEvent.minute).toBe(submissionMinute);

        const subIdx = state.events.indexOf(subEvent);
        expect(
            state.lastRevealedIndex,
            'lastRevealedIndex must include the half-time sub event so it is not re-revealed when 2H starts',
        ).toBeGreaterThanOrEqual(subIdx);
    });

    it('records a confirmed user sub in substitutionsMade exactly once', async () => {
        // Regression for the duplicated row in the "SUSTITUCIONES REALIZADAS"
        // panel: a user-confirmed sub at minute 95 (2H stoppage) used to be
        // pushed once by confirmAllChanges' eager response handler AND a
        // second time when the reveal loop later processed the same event.
        // Now trackSubstitutionIfNeeded is the only writer; the eager path
        // calls it inline at injection time.
        const shs = 5;
        const submissionMinute = 90 + shs;
        const state = createMockState({
            phase: 'second_half',
            currentMinute: submissionMinute,
            secondHalfStoppage: shs,
        });

        globalThis.fetch = vi.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({
                isExtraTime: false,
                substitutions: [{
                    playerOutId: 'home-cb1',
                    playerInId: 'home-sub1',
                    playerOutName: 'home cb1',
                    playerInName: 'Sub Player',
                    teamId: 'home-1',
                    minute: submissionMinute,
                    displayMinute: "90+5'",
                    phase: 'second_half_stoppage',
                }],
                newEvents: [],
                newScore: { home: 1, away: 0 },
                homePossession: 50,
                awayPossession: 50,
            }),
        }));

        state.pendingSubs = [{
            playerOut: { id: 'home-cb1' },
            playerIn: { id: 'home-sub1' },
        }];

        const submission = createTacticalSubmission(() => state);
        await submission.confirmAllChanges();

        const homeSubs = state.substitutionsMade.filter(s => s.playerOutId === 'home-cb1');
        expect(homeSubs.length, 'sub should be recorded exactly once').toBe(1);
        expect(homeSubs[0].playerInId).toBe('home-sub1');
        expect(homeSubs[0].minute).toBe(submissionMinute);

        // Simulate the reveal-loop helper firing again for the same event
        // (which is what happens when enterRegularTimeEnd / enterFullTime /
        // skipToFullTimeImmediate walk c.events). With the single-writer
        // invariant intact, lastRevealedIndex covers this event so the
        // helper would never actually be called here in production — but
        // exercising it directly catches a regression where the eager path
        // and the reveal path both populate the array unconditionally.
        const subEvent = state.events.find(e =>
            e.type === 'substitution' && e.gamePlayerId === 'home-cb1'
        );
        expect(subEvent, 'sub event should be in the merged events array').toBeDefined();
        // The production fix relies on lastRevealedIndex covering this event;
        // verify that contract so the reveal loop's `for (i = lastRevealedIndex + 1; ...)`
        // genuinely skips it on subsequent ticks.
        const subIdx = state.events.indexOf(subEvent);
        expect(state.lastRevealedIndex).toBeGreaterThanOrEqual(subIdx);
    });

    it('half-time formation change pins the full displayed XI so kickoff is not re-solved', async () => {
        // Regression for #1161. Changing formation at half-time used to send
        // only the partial drag-swap pins (or none), letting the server
        // re-solve the new shape — so the kickoff XI could differ from the
        // arrangement the user was looking at. confirmAllChanges now sends the
        // FULL previewSlotMap (the rendered preview) as manual_slot_pins, so
        // FormationRecommender reproduces it verbatim.
        const previewSlotMap = {
            0: 'home-gk',
            1: 'home-lb', 2: 'home-cb1', 3: 'home-cb2', 4: 'home-rb',
            5: 'home-cm1', 6: 'home-cm2', 7: 'home-cm3',
            8: 'home-fw1', 9: 'home-fw2', 10: 'home-fw3',
        };
        const state = createMockState({
            phase: 'half_time',
            currentMinute: 45,
            firstHalfStoppage: 0,
            hasTacticalChanges: true,
            pendingFormation: '5-3-2',
            activeFormation: '4-3-3',
            previewSlotMap,
            // A partial drag-swap pin that must be SUPERSEDED by the full map.
            _manualSlotPins: { 4: 'home-rb', 1: 'home-lb' },
        });

        let capturedPayload = null;
        globalThis.fetch = vi.fn((url, opts) => {
            capturedPayload = JSON.parse(opts.body);
            return Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    isExtraTime: false,
                    formation: '5-3-2',
                    slot_assignments: previewSlotMap,
                    substitutions: [],
                    newEvents: [],
                    newScore: { home: 1, away: 0 },
                    homePossession: 50,
                    awayPossession: 50,
                }),
            });
        });

        const submission = createTacticalSubmission(() => state);
        await submission.confirmAllChanges();

        expect(capturedPayload).not.toBeNull();
        expect(capturedPayload.formation).toBe('5-3-2');
        expect(capturedPayload.is_half_time).toBe(true);
        // The full displayed map is pinned — not just the two drag-swapped slots.
        expect(capturedPayload.manual_slot_pins).toEqual(previewSlotMap);
    });
});
