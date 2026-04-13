/**
 * Tests for live-match.js exported helpers.
 *
 * These test the critical decision logic that routes tactical changes
 * to the correct backend handler (regular time vs extra time).
 *
 * The stoppage-time goal bug was caused by a sub at minute 90 during
 * going_to_extra_time being treated as a regular-time resimulation,
 * which deleted the stoppage-time goal and changed the score.
 */
import { describe, it, expect } from 'vitest';
import { resolveMinuteForTacticalChange } from '@/live-match.js';

describe('resolveMinuteForTacticalChange', () => {
    // ========================================================================
    // Regular time — should pass minute through unchanged
    // ========================================================================

    it('passes through minute during first_half', () => {
        expect(resolveMinuteForTacticalChange(30, 'first_half')).toBe(30);
    });

    it('passes through minute during second_half', () => {
        expect(resolveMinuteForTacticalChange(75, 'second_half')).toBe(75);
    });

    it('passes through minute 90 during second_half', () => {
        expect(resolveMinuteForTacticalChange(90, 'second_half')).toBe(90);
    });

    it('floors fractional minutes', () => {
        expect(resolveMinuteForTacticalChange(45.7, 'first_half')).toBe(45);
    });

    // ========================================================================
    // ET phases — must clamp to >= 93 to protect stoppage-time events
    // ========================================================================

    it('clamps minute 90 to 93 during going_to_extra_time', () => {
        // This is the exact bug scenario: enterRegularTimeEnd sets
        // currentMinute=90, user makes a sub, backend would treat
        // minute=90 as regular time and delete stoppage-time goals.
        expect(resolveMinuteForTacticalChange(90, 'going_to_extra_time')).toBe(93);
    });

    it('clamps minute 90 to 93 during extra_time_first_half', () => {
        expect(resolveMinuteForTacticalChange(90, 'extra_time_first_half')).toBe(93);
    });

    it('clamps minute 90 to 93 during extra_time_half_time', () => {
        expect(resolveMinuteForTacticalChange(90, 'extra_time_half_time')).toBe(93);
    });

    it('clamps minute 90 to 93 during extra_time_second_half', () => {
        expect(resolveMinuteForTacticalChange(90, 'extra_time_second_half')).toBe(93);
    });

    // ========================================================================
    // ET phases with minutes > 93 — should pass through unchanged
    // ========================================================================

    it('passes through minute 100 during extra_time_first_half', () => {
        expect(resolveMinuteForTacticalChange(100, 'extra_time_first_half')).toBe(100);
    });

    it('passes through minute 110 during extra_time_second_half', () => {
        expect(resolveMinuteForTacticalChange(110, 'extra_time_second_half')).toBe(110);
    });

    it('passes through minute 105 during extra_time_half_time', () => {
        expect(resolveMinuteForTacticalChange(105, 'extra_time_half_time')).toBe(105);
    });

    // ========================================================================
    // Edge cases
    // ========================================================================

    it('does not clamp non-ET phases even at boundary minutes', () => {
        expect(resolveMinuteForTacticalChange(93, 'second_half')).toBe(93);
    });

    it('clamps minute 91 to 93 during ET phase', () => {
        // Minute 91-93 are stoppage time; they must not be sent to backend
        // as ET resimulation start points that would delete the events.
        expect(resolveMinuteForTacticalChange(91, 'going_to_extra_time')).toBe(93);
    });

    it('clamps minute 93 to 93 during ET phase (boundary)', () => {
        expect(resolveMinuteForTacticalChange(93, 'going_to_extra_time')).toBe(93);
    });

    it('handles pre_match phase normally', () => {
        expect(resolveMinuteForTacticalChange(0, 'pre_match')).toBe(0);
    });

    it('handles full_time phase normally', () => {
        expect(resolveMinuteForTacticalChange(90, 'full_time')).toBe(90);
    });
});
