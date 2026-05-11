<?php

namespace App\Http\Views;

use App\Models\ManagerJobOffer;
use Illuminate\Http\Request;

/**
 * Renders the three Primera RFEF Local-tier offers a brand-new pro
 * manager picks from. If the user has no pending initial offers the
 * page bounces back to the team-selection screen so they can re-roll
 * via /new-game-pro.
 */
final class ShowInitialOffers
{
    public function __invoke(Request $request)
    {
        $offers = ManagerJobOffer::with(['team.clubProfile', 'competition'])
            ->where('user_id', $request->user()->id)
            ->where('offer_type', ManagerJobOffer::TYPE_INITIAL)
            ->where('status', ManagerJobOffer::STATUS_PENDING)
            ->whereNull('game_id')
            ->get();

        if ($offers->isEmpty()) {
            return redirect()->route('select-team');
        }

        return view('game.pro.initial-offers', [
            'offers' => $offers,
        ]);
    }
}
