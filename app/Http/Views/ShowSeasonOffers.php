<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Manager\Services\JobOfferService;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Modules\Report\Services\SeasonSummaryService;

/**
 * Renders the pro-manager between-seasons decision screen: pending job
 * offers from other clubs plus the option to stay at the current club
 * (suppressed when the manager has been fired). The user's choice on this
 * page is what kicks off the season-closing pipeline — see
 * AcceptSeasonOffer and DeclineSeasonOffers, which both re-enter
 * StartNewSeason after persisting the decision.
 *
 * Visiting this page generates the offers if they don't yet exist. The
 * service-side idempotency guard makes refresh/back-nav safe.
 */
class ShowSeasonOffers
{
    public function __construct(
        private readonly SeasonSummaryService $seasonSummaryService,
        private readonly JobOfferService $jobOfferService,
        private readonly MatchFinalizationService $finalizationService,
        private readonly PlayoffGeneratorFactory $playoffFactory,
    ) {}

    public function __invoke(string $gameId)
    {
        // Same defensive finalize as ShowSeasonEnd: a match abandoned on
        // the live screen would otherwise leave standings stale, which
        // would feed a wrong grade into ensureEndOfSeasonOffersGenerated.
        $this->finalizationService->finalizePendingIfAny($gameId);

        $game = Game::with('team')->findOrFail($gameId);

        abort_unless($game->isProManagerMode(), 404);
        abort_if($game->isTournamentMode(), 404);

        if ($game->isTransitioningSeason()) {
            return redirect()->route('show-game', $gameId);
        }

        $unplayedMatches = $game->matches()
            ->where('played', false)
            ->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', __('messages.season_not_complete'));
        }

        foreach ($this->playoffFactory->all() as $generator) {
            if ($generator->state($game) === PlayoffState::InProgress) {
                return redirect()->route('show-game', $gameId)
                    ->with('error', __('messages.season_not_complete'));
            }
        }

        $this->jobOfferService->ensureEndOfSeasonOffersGenerated($game);

        [$jobOffers, $pendingTeamSwitchOffer] = $this->seasonSummaryService->buildProManagerOffers($game);

        return view('season-offers', [
            'game' => $game,
            'jobOffers' => $jobOffers,
            'pendingTeamSwitchOffer' => $pendingTeamSwitchOffer,
            'firedAtSeasonEnd' => $game->wasFiredThisSeason(),
            'positionsByOfferId' => $this->seasonSummaryService->lastSeasonPositionsByOfferId($game, $jobOffers),
            'goalLabelsByOfferId' => $this->seasonSummaryService->seasonGoalLabelsByOfferId($jobOffers),
            'nextSeasonLabel' => Game::formatSeason((string) ((int) $game->season + 1)),
        ]);
    }
}
