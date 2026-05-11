<?php

namespace App\Http\Actions;

use App\Models\ActivationEvent;
use App\Models\Game;
use App\Models\ManagerJobHistory;
use App\Models\ManagerJobOffer;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\GameCreationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Accept one of the three Primera RFEF starter offers, creating the Game
 * record for the user's new pro-manager career and rejecting the other
 * pending initial offers.
 */
class AcceptInitialOffer
{
    public function __construct(
        private readonly GameCreationService $gameCreationService,
        private readonly ActivationTracker $activationTracker,
    ) {}

    public function __invoke(Request $request, string $offerId)
    {
        $offer = ManagerJobOffer::where('id', $offerId)
            ->where('user_id', $request->user()->id)
            ->where('offer_type', ManagerJobOffer::TYPE_INITIAL)
            ->where('status', ManagerJobOffer::STATUS_PENDING)
            ->whereNull('game_id')
            ->firstOrFail();

        $gameCount = Game::where('user_id', $request->user()->id)
            ->whereNull('deleting_at')
            ->count();

        if ($gameCount >= 3) {
            return back()->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        $game = $this->gameCreationService->create(
            userId: (string) $request->user()->id,
            teamId: $offer->team_id,
            gameMode: Game::MODE_CAREER_PRO,
        );

        DB::transaction(function () use ($offer, $game, $request) {
            // Mark the accepted offer + reject siblings so a repeat visit
            // can't accept a stale option.
            ManagerJobOffer::where('user_id', $request->user()->id)
                ->where('offer_type', ManagerJobOffer::TYPE_INITIAL)
                ->where('status', ManagerJobOffer::STATUS_PENDING)
                ->whereNull('game_id')
                ->where('id', '!=', $offer->id)
                ->update(['status' => ManagerJobOffer::STATUS_REJECTED]);

            $offer->update([
                'status' => ManagerJobOffer::STATUS_ACCEPTED,
                'game_id' => $game->id,
                'season' => $game->season,
                'competition_id' => $game->competition_id,
                'created_on_game_date' => $game->current_date,
            ]);

            ManagerJobHistory::create([
                'game_id' => $game->id,
                'user_id' => $request->user()->id,
                'team_id' => $game->team_id,
                'competition_id' => $game->competition_id,
                'season_start' => $game->season,
                'season_end' => null,
                'end_reason' => ManagerJobHistory::REASON_STILL_ACTIVE,
            ]);
        });

        $this->activationTracker->record(
            $request->user()->id,
            ActivationEvent::EVENT_GAME_CREATED,
            $game->id,
            Game::MODE_CAREER_PRO,
        );

        return redirect()->route('game.welcome', $game->id);
    }
}
