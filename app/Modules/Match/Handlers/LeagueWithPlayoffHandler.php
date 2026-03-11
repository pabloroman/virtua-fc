<?php

namespace App\Modules\Match\Handlers;

use App\Modules\Competition\Contracts\PlayoffGenerator;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Match\Services\CupTieResolver;
use App\Modules\Squad\Services\EligibilityService;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;

/**
 * Handler for league competitions that have end-of-season playoffs.
 *
 * Combines regular league handling with knockout playoff generation
 * and resolution after the regular season ends.
 */
class LeagueWithPlayoffHandler extends CupCompetitionHandler
{
    public function __construct(
        CupTieResolver $tieResolver,
        EligibilityService $eligibilityService,
        private readonly PlayoffGeneratorFactory $playoffFactory,
    ) {
        parent::__construct($tieResolver, $eligibilityService);
    }

    public function getType(): string
    {
        return 'league_with_playoff';
    }

    public function getMatchBatch(string $gameId, GameMatch $nextMatch): Collection
    {
        return $this->getHybridMatchBatch($gameId, $nextMatch);
    }

    public function beforeMatches(Game $game, string $targetDate): void
    {
        $generator = $this->playoffFactory->forCompetition($game->competition_id);
        if (!$generator) {
            return;
        }

        if ($this->shouldGeneratePlayoffRound($game, $generator)) {
            $nextRound = $this->getCurrentRound($game->id, $generator->getCompetitionId()) + 1;
            $this->generatePlayoffRound($game, $generator, $nextRound);
        }
    }

    public function afterMatches(Game $game, Collection $matches, Collection $allPlayers): void
    {
        $playoffMatches = $matches->filter(fn ($m) => $m->cup_tie_id !== null);

        if ($playoffMatches->isNotEmpty()) {
            $this->resolveCompletedTies($game, $playoffMatches, $allPlayers);
        }
    }

    /**
     * Check if the season is complete (including playoffs if applicable).
     */
    public function isSeasonComplete(Game $game): bool
    {
        $unplayedLeague = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists();

        if ($unplayedLeague) {
            return false;
        }

        $generator = $this->playoffFactory->forCompetition($game->competition_id);
        if (!$generator) {
            return true;
        }

        return $generator->isComplete($game);
    }

    /**
     * Determine if we should generate the next playoff round.
     */
    private function shouldGeneratePlayoffRound(Game $game, PlayoffGenerator $generator): bool
    {
        $regularSeasonComplete = !GameMatch::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists();

        if (!$regularSeasonComplete) {
            return false;
        }

        // Don't generate while a league match is pending finalization —
        // its standings haven't been applied yet, so seedings would be wrong
        if ($game->hasPendingFinalizationForCompetition($game->competition_id)) {
            return false;
        }

        $competitionId = $generator->getCompetitionId();
        $currentRound = $this->getCurrentRound($game->id, $competitionId);
        $nextRound = $currentRound + 1;

        if ($nextRound > $generator->getTotalRounds()) {
            return false;
        }

        // For round 1, generate if it doesn't exist
        if ($nextRound === 1) {
            return !$this->roundExists($game->id, $competitionId, 1);
        }

        // For later rounds, check if previous round is complete
        $previousRoundComplete = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', $currentRound)
            ->where('completed', false)
            ->doesntExist();

        return $previousRoundComplete && !$this->roundExists($game->id, $competitionId, $nextRound);
    }

    private function generatePlayoffRound(Game $game, PlayoffGenerator $generator, int $round): void
    {
        $competitionId = $generator->getCompetitionId();
        $config = $generator->getRoundConfig($round, $game->season);
        $matchups = $generator->generateMatchups($game, $round);

        foreach ($matchups as [$homeTeamId, $awayTeamId]) {
            $this->createTie($game, $competitionId, $homeTeamId, $awayTeamId, $config);
        }
    }
}
