<?php

namespace App\Modules\Transfer\Exceptions;

use App\Models\TransferOffer;

/**
 * A player held in place by a locking deal (TransferOffer::locksPlayer())
 * was found at a different club when that deal came to complete.
 *
 * This is an invariant violation, not a game event: some code path moved a
 * locked player without going through his deal. TransferCompletionService
 * still fails the deal gracefully for the user (rejects the offer, releases
 * any escrow, sends a notification), but it reports this exception so the
 * offending path is visible in the logs — and in the test suite, where the
 * base TestCase rethrows it so any test that trips the invariant fails.
 */
class LockedPlayerMovedException extends \RuntimeException
{
    public static function forOffer(TransferOffer $offer, ?string $playerTeamId): self
    {
        return new self(sprintf(
            'Locked player %s (offer %s, %s, %s) was expected at team %s but is at %s. '
            . 'Some path moved him without completing or rejecting the deal.',
            $offer->game_player_id,
            $offer->id,
            $offer->offer_type,
            $offer->status,
            $offer->selling_team_id ?? 'null',
            $playerTeamId ?? 'null',
        ));
    }
}
