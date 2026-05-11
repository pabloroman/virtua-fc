<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\ManagerJobOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stage a pro-manager end-of-season offer for application at next setup.
 * The offer is marked `accepted`, sibling offers from the same season are
 * rejected, and Game.pending_team_switch is wired up so that
 * ApplyPendingTeamSwitchProcessor moves the manager when the new season
 * pipeline runs.
 */
class AcceptSeasonOffer
{
    public function __invoke(Request $request, string $gameId, string $offerId)
    {
        $game = Game::where('id', $gameId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless($game->isProManagerMode(), 404);

        $offer = ManagerJobOffer::where('id', $offerId)
            ->where('game_id', $game->id)
            ->where('user_id', $request->user()->id)
            ->where('status', ManagerJobOffer::STATUS_PENDING)
            ->whereIn('offer_type', [
                ManagerJobOffer::TYPE_END_OF_SEASON,
                ManagerJobOffer::TYPE_POST_FIRING,
            ])
            ->firstOrFail();

        DB::transaction(function () use ($game, $offer) {
            ManagerJobOffer::where('game_id', $game->id)
                ->where('season', $offer->season)
                ->where('status', ManagerJobOffer::STATUS_PENDING)
                ->where('id', '!=', $offer->id)
                ->update(['status' => ManagerJobOffer::STATUS_REJECTED]);

            $offer->update(['status' => ManagerJobOffer::STATUS_ACCEPTED]);

            $game->update(['pending_team_switch' => $offer->id]);
        });

        return redirect()->route('game.season-end', $game->id)
            ->with('status', __('manager.offer_accepted_flash'));
    }
}
