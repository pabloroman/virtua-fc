<?php

namespace App\Modules\Transfer\Exceptions;

use App\Models\TransferOffer;

/**
 * A code path tried to move a TransferOffer to a status its current status
 * cannot legally reach (see TransferOffer::TRANSITIONS) — re-agreeing an
 * agreed deal, resurrecting a rejected one, completing one twice. This is a
 * programming error, never a game event, so it is thrown rather than reported.
 */
class IllegalOfferTransitionException extends \LogicException
{
    public static function forOffer(TransferOffer $offer, string $to): self
    {
        return new self(sprintf(
            'Offer %s (%s) cannot move from %s to %s.',
            $offer->id,
            $offer->offer_type,
            $offer->status,
            $to,
        ));
    }
}
