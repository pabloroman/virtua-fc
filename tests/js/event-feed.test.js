/**
 * Tests for event-feed.js — the read-only view layer that partitions
 * the revealedEvents array into per-half buckets for rendering.
 *
 * The core regression we guard against: when `match_events` carries a
 * `phase`, partitioning must follow the phase, not the raw absolute
 * `minute`. A first-half-stoppage event has absolute minute > 45 and
 * would otherwise render above the half-time separator.
 */
import { describe, it, expect } from 'vitest';
import { createEventFeed } from '@/modules/event-feed.js';

function makeFeed(events) {
    const state = { revealedEvents: events };
    return createEventFeed(() => state);
}

describe('event-feed partitioning', () => {
    it('puts first-half-stoppage events in the first half regardless of absolute minute', () => {
        const feed = makeFeed([
            { type: 'shot_on_target', minute: 47, phase: 'first_half_stoppage' },
            { type: 'shot_off_target', minute: 49, phase: 'first_half_stoppage' },
            { type: 'goal',            minute: 30, phase: 'first_half' },
        ]);

        expect(feed.firstHalfEvents).toHaveLength(3);
        expect(feed.secondHalfEvents).toHaveLength(0);
    });

    it('puts second-half-stoppage events in the second half regardless of absolute minute', () => {
        const feed = makeFeed([
            { type: 'goal', minute: 93, phase: 'second_half_stoppage' },
            { type: 'goal', minute: 94, phase: 'second_half' }, // base 90 + fhs=4
        ]);

        expect(feed.firstHalfEvents).toHaveLength(0);
        expect(feed.secondHalfEvents).toHaveLength(2);
        expect(feed.etFirstHalfEvents).toHaveLength(0);
    });

    it('keeps extra-time phases on their respective sides', () => {
        const feed = makeFeed([
            { type: 'goal', minute: 100, phase: 'et_first_half' },
            { type: 'goal', minute: 107, phase: 'et_first_half_stoppage' },
            { type: 'goal', minute: 115, phase: 'et_second_half' },
            { type: 'goal', minute: 123, phase: 'et_second_half_stoppage' },
        ]);

        expect(feed.etFirstHalfEvents).toHaveLength(2);
        expect(feed.etSecondHalfEvents).toHaveLength(2);
    });

    it('falls back to minute thresholds for phase-less client-injected events', () => {
        // Atmosphere second-half-start (minute=45.9), stoppage announcement
        // (minute=45), and a halftime substitution (minute=45) all lack `phase`.
        const feed = makeFeed([
            { type: 'stoppage_announcement', minute: 45, atmosphere: true },
            { type: 'contextual',            minute: 45.9, atmosphere: true },
            { type: 'substitution',          minute: 60, playerName: 'A', playerInName: 'B', teamId: 'home-1' },
        ]);

        expect(feed.firstHalfEvents.map(e => e.type)).toEqual(['stoppage_announcement']);
        expect(feed.secondHalfEvents.map(e => e.type)).toEqual(['contextual', 'substitution']);
    });
});
