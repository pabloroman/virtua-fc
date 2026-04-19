<?php

namespace App\Modules\Competition\Enums;

/**
 * Lifecycle state of a division's promotion playoff for a specific game.
 *
 * Distinguishes the three cases that have historically been conflated in
 * promotion logic, causing the "playoff loser promoted" class of bug:
 *
 *  - NotStarted: no CupTie rows exist for this playoff in this game. Either
 *    the playoff isn't needed (league without one) or closing pipeline ran
 *    on a simulated league that never triggered the mid-season bracket. Safe
 *    to fall back to simulated stand-ins.
 *
 *  - InProgress: at least one CupTie exists but the final is not completed.
 *    Promotion cannot be resolved yet — the season transition must be
 *    blocked until the final resolves. Anything that sees this state should
 *    throw PlayoffInProgressException rather than guess.
 *
 *  - Completed: the generator's isComplete() returns true. Real playoff
 *    winners must exist and be used.
 */
enum PlayoffState: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
