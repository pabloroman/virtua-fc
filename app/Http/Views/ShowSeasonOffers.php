<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameStanding;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Manager\Services\JobOfferService;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Modules\Report\Services\SeasonSummaryService;
use App\Modules\Season\Services\SeasonGoalService;

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
        private readonly SeasonGoalService $seasonGoalService,
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

        // The generator may flip fired_at_season_end — refresh so the
        // view sees it.
        $game->refresh();

        [$jobOffers, $pendingTeamSwitchOffer] = $this->seasonSummaryService->buildProManagerOffers($game);

        $positionsByOfferId = $this->loadLastSeasonPositions($game, $jobOffers);
        $goalLabelsByOfferId = $this->resolveSeasonGoals($jobOffers);

        return view('season-offers', [
            'game' => $game,
            'jobOffers' => $jobOffers,
            'pendingTeamSwitchOffer' => $pendingTeamSwitchOffer,
            'firedAtSeasonEnd' => (bool) $game->fired_at_season_end,
            'positionsByOfferId' => $positionsByOfferId,
            'goalLabelsByOfferId' => $goalLabelsByOfferId,
        ]);
    }

    /**
     * Resolve the season-goal label each offering club would set if the
     * manager takes the job: based on the club's reputation level in its
     * league config. Returns null for competitions without a HasSeasonGoals
     * config (cups, etc.) so the card can hide the line.
     *
     * @return array<string, string|null>
     */
    private function resolveSeasonGoals(\Illuminate\Support\Collection $offers): array
    {
        $result = [];
        foreach ($offers as $offer) {
            $team = $offer->team;
            $competition = $offer->competition;
            if (!$team || !$competition) {
                $result[$offer->id] = null;
                continue;
            }
            $goal = $this->seasonGoalService->determineGoalForTeam($team, $competition);
            $result[$offer->id] = __($this->seasonGoalService->getGoalLabel($goal, $competition));
        }
        return $result;
    }

    /**
     * Resolve each offer's "last season finishing position" by reading the
     * tenant-side GameStanding rows still holding the just-ended season's
     * values. Returns a map keyed by offer.id; null entries mean we have
     * no published position to show (e.g. foreign league that hasn't been
     * finalized yet because the closing pipeline runs after Accept/Decline).
     *
     * @return array<string, int|null>
     */
    private function loadLastSeasonPositions(Game $game, \Illuminate\Support\Collection $offers): array
    {
        $teamIds = $offers->pluck('team_id')->unique()->values()->all();
        $competitionIds = $offers->pluck('competition_id')->filter()->unique()->values()->all();

        if (empty($teamIds) || empty($competitionIds)) {
            return [];
        }

        $standings = GameStanding::where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->whereIn('competition_id', $competitionIds)
            ->where('played', '>', 0)
            ->get(['team_id', 'competition_id', 'position'])
            ->keyBy(fn ($s) => $s->team_id . ':' . $s->competition_id);

        $result = [];
        foreach ($offers as $offer) {
            $key = $offer->team_id . ':' . $offer->competition_id;
            $result[$offer->id] = $standings->get($key)?->position;
        }
        return $result;
    }
}
