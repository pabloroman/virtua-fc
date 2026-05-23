/**
 * Tests for atmosphere-generator.js — client-side cosmetic event generation.
 *
 * The core regression we guard against: a player who was sent off (red card)
 * or substituted should never appear as the actor in atmosphere events
 * generated at a later minute.
 */
import { describe, it, expect } from 'vitest';
import {
    generateAtmosphereForPeriod,
    generateRegularTimeAtmosphere,
    formatAtmosphereDisplayMinute,
} from '@/modules/atmosphere-generator.js';

function makeRoster(prefix) {
    return [
        { id: `${prefix}-gk`, name: `${prefix} GK`,  positionGroup: 'Goalkeeper' },
        { id: `${prefix}-cb1`, name: `${prefix} CB1`, positionGroup: 'Defender' },
        { id: `${prefix}-cb2`, name: `${prefix} CB2`, positionGroup: 'Defender' },
        { id: `${prefix}-lb`,  name: `${prefix} LB`,  positionGroup: 'Defender' },
        { id: `${prefix}-rb`,  name: `${prefix} RB`,  positionGroup: 'Defender' },
        { id: `${prefix}-cm1`, name: `${prefix} CM1`, positionGroup: 'Midfielder' },
        { id: `${prefix}-cm2`, name: `${prefix} CM2`, positionGroup: 'Midfielder' },
        { id: `${prefix}-cm3`, name: `${prefix} CM3`, positionGroup: 'Midfielder' },
        { id: `${prefix}-fw1`, name: `${prefix} FW1`, positionGroup: 'Forward' },
        { id: `${prefix}-fw2`, name: `${prefix} FW2`, positionGroup: 'Forward' },
        { id: `${prefix}-fw3`, name: `${prefix} FW3`, positionGroup: 'Forward' },
    ];
}

function baseConfig(overrides = {}) {
    return {
        homeTeamId: 'home-1',
        awayTeamId: 'away-1',
        homeTeamName: 'Home FC',
        awayTeamName: 'Away FC',
        homeArticle: 'el',
        awayArticle: 'el',
        homePlayers: makeRoster('home'),
        awayPlayers: makeRoster('away'),
        homeScore: 5,
        awayScore: 5,
        narrativeTemplates: {
            shotOnTarget: ['Shot by :player'],
            shotOffTarget: ['Wide by :player'],
        },
        allEvents: [],
        ...overrides,
    };
}

describe('formatAtmosphereDisplayMinute', () => {
    it('renders regular-play minutes as bare integers', () => {
        expect(formatAtmosphereDisplayMinute(1)).toBe("1'");
        expect(formatAtmosphereDisplayMinute(30)).toBe("30'");
        expect(formatAtmosphereDisplayMinute(45)).toBe("45'");
        expect(formatAtmosphereDisplayMinute(60)).toBe("60'");
        expect(formatAtmosphereDisplayMinute(90)).toBe("90'");
    });

    it('renders 1H-stoppage minutes as "45+N\'" using firstHalfStoppage', () => {
        // Regression: a shot at sort-minute 47 with fhs=3 used to render
        // as "47'" because atmosphere events fell through to the
        // Math.floor(minute) fallback in the template. The live clock at
        // that moment shows "45+2'", and the event display should match.
        const stoppage = { firstHalfStoppage: 3 };
        expect(formatAtmosphereDisplayMinute(46, stoppage)).toBe("45+1'");
        expect(formatAtmosphereDisplayMinute(47, stoppage)).toBe("45+2'");
        expect(formatAtmosphereDisplayMinute(48, stoppage)).toBe("45+3'");
        // Just past 1H stoppage — back to bare integer minute.
        expect(formatAtmosphereDisplayMinute(49, stoppage)).toBe("49'");
    });

    it('falls back to bare integer when no stoppage info is provided', () => {
        // fhs=0 default: minute 46 is regular 2H, not 1H stoppage.
        expect(formatAtmosphereDisplayMinute(46)).toBe("46'");
        expect(formatAtmosphereDisplayMinute(47)).toBe("47'");
    });

    it('renders 2H-stoppage / ET / ET-stoppage windows correctly', () => {
        const stoppage = {
            firstHalfStoppage: 2,
            secondHalfStoppage: 4,
            etFirstHalfStoppage: 1,
            etSecondHalfStoppage: 3,
        };
        expect(formatAtmosphereDisplayMinute(91, stoppage)).toBe("90+1'");
        expect(formatAtmosphereDisplayMinute(94, stoppage)).toBe("90+4'");
        expect(formatAtmosphereDisplayMinute(100, stoppage)).toBe("100'");
        expect(formatAtmosphereDisplayMinute(105, stoppage)).toBe("105'");
        expect(formatAtmosphereDisplayMinute(106, stoppage)).toBe("105+1'");
        expect(formatAtmosphereDisplayMinute(120, stoppage)).toBe("120'");
        expect(formatAtmosphereDisplayMinute(123, stoppage)).toBe("120+3'");
    });
});

describe('atmosphere-generator off-pitch player filtering', () => {
    it('never picks a red-carded player for shot events placed after the red card', () => {
        const redCardEvent = {
            minute: 40,
            type: 'red_card',
            gamePlayerId: 'away-cm1',
            teamId: 'away-1',
            metadata: { second_yellow: true },
        };

        for (let i = 0; i < 50; i++) {
            const events = generateRegularTimeAtmosphere(baseConfig({
                allEvents: [redCardEvent],
            }));

            const offendingEvent = events.find(e =>
                (e.type === 'shot_on_target' || e.type === 'shot_off_target')
                && e.gamePlayerId === 'away-cm1'
                && e.minute > 40
            );

            expect(offendingEvent, `iteration ${i}: shot at minute ${offendingEvent?.minute} by red-carded player`).toBeUndefined();
        }
    });

    it('never picks a red-carded player for shots in the same half after the red card', () => {
        const redCardEvent = {
            minute: 40,
            type: 'red_card',
            gamePlayerId: 'away-cm1',
            teamId: 'away-1',
            metadata: {},
        };

        for (let i = 0; i < 50; i++) {
            const firstHalf = generateAtmosphereForPeriod(baseConfig({
                allEvents: [redCardEvent],
                minMinute: 1,
                maxMinute: 45,
            }));

            const offendingEvent = firstHalf.find(e =>
                (e.type === 'shot_on_target' || e.type === 'shot_off_target')
                && e.gamePlayerId === 'away-cm1'
                && e.minute > 40
            );

            expect(offendingEvent, `iteration ${i}: shot at minute ${offendingEvent?.minute} by red-carded player`).toBeUndefined();
        }
    });

    it('never picks a substituted-off player for shots after the substitution', () => {
        const subEvent = {
            minute: 60,
            type: 'substitution',
            gamePlayerId: 'home-fw1',
            teamId: 'home-1',
            playerInName: 'Sub Player',
            metadata: { player_in_id: 'home-sub1' },
        };

        for (let i = 0; i < 50; i++) {
            const events = generateRegularTimeAtmosphere(baseConfig({
                allEvents: [subEvent],
            }));

            const offendingEvent = events.find(e =>
                (e.type === 'shot_on_target' || e.type === 'shot_off_target')
                && e.gamePlayerId === 'home-fw1'
                && e.minute > 60
            );

            expect(offendingEvent, `iteration ${i}: shot by subbed-off player at minute ${offendingEvent?.minute}`).toBeUndefined();
        }
    });
});
