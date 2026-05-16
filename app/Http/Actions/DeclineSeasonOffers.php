<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\ManagerJobOffer;
use Illuminate\Http\Request;

/**
 * Mark all of the user's pending end-of-season offers as rejected. Fired
 * managers are not allowed to decline — their pending offers are forced
 * choices and the firing guard on StartNewSeason refuses to advance
 * without an accepted post-firing offer.
 */
class DeclineSeasonOffers
{
    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::where('id', $gameId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless($game->isProManagerMode(), 404);

        if ($game->wasFiredThisSeason()) {
            return back()->withErrors(['decline' => __('manager.cannot_decline_after_firing')]);
        }

        ManagerJobOffer::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('status', ManagerJobOffer::STATUS_PENDING)
            ->update(['status' => ManagerJobOffer::STATUS_REJECTED]);

        $game->update(['pending_team_switch' => null]);

        // Staying is also a resolution — kick off the closing pipeline by
        // re-entering StartNewSeason. The pro-manager router sees no
        // pending offers and falls through to dispatch.
        return app(StartNewSeason::class)($game->id);
    }
}
