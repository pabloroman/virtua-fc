<?php

namespace App\Modules\Transfer\Exceptions;

use App\Models\TransferOffer;

/**
 * An offer the user had fully agreed was still waiting to complete when
 * TransferMarketResetProcessor cleared the market at season close.
 *
 * This is an invariant violation, not a game event. Every agreed deal is
 * meant to be resolved earlier in the closing pipeline — pre-contracts by
 * PreContractTransferProcessor (priority 30), everything else by
 * AgreedTransferCompletionProcessor (priority 35) — either completing or
 * failing loudly with a "transfer fell through" notification. Reaching the
 * reset at priority 70 still agreed means no completion query matched it,
 * and the reset's unconditional delete then erases the only evidence the
 * deal ever existed. That is why "mis fichajes han desaparecido" has been
 * impossible to diagnose after the fact.
 *
 * Reported (not thrown) in production so a save never gets stuck on it; the
 * base TestCase rethrows it so any pipeline test that strands an agreed deal
 * fails instead of surfacing months later as a support ticket.
 *
 * STATUS_FEE_AGREED is deliberately not covered: a deal whose club fee is
 * settled but whose personal terms are not is an unfinished negotiation, and
 * abandoning it at season end is correct.
 */
class AgreedOfferDiscardedException extends \RuntimeException
{
    public static function forOffer(TransferOffer $offer): self
    {
        return new self(sprintf(
            'Agreed offer %s (player %s, %s, direction %s, seller %s, buyer %s) was still '
            . 'awaiting completion when the transfer market was reset at season close. '
            . 'No completion pass picked it up.',
            $offer->id,
            $offer->game_player_id,
            $offer->offer_type,
            $offer->direction ?? 'null',
            $offer->selling_team_id ?? 'null',
            $offer->offering_team_id,
        ));
    }
}
