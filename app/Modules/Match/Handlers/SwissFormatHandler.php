<?php

namespace App\Modules\Match\Handlers;

use App\Models\Competition;
use App\Modules\Competition\Services\SwissKnockoutGenerator;
use App\Modules\Match\Services\CupTieResolver;
use App\Modules\Squad\Services\EligibilityService;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;

/**
 * Handler for UEFA-style Swiss format competitions (Champions League, Europa League, Conference League).
 *
 * League phase: 36 teams, 8 matchdays, single standings table.
 * Knockout phase: Playoff (9-24) → R16 (top 8 + playoff winners) → QF → SF → Final.
 */
class SwissFormatHandler extends CupCompetitionHandler
{
    public function __construct(
        CupTieResolver $tieResolver,
        EligibilityService $eligibilityService,
        private readonly SwissKnockoutGenerator $knockoutGenerator,
    ) {
        parent::__construct($tieResolver, $eligibilityService);
    }

    public function getType(): string
    {
        return 'swiss_format';
    }

    public function getMatchBatch(string $gameId, GameMatch $nextMatch): Collection
    {
        return $this->getHybridMatchBatch($gameId, $nextMatch);
    }

    public function beforeMatches(Game $game, string $targetDate): void
    {
        // Check all swiss format competitions for pending knockout generation
        $competitions = Competition::where('handler_type', 'swiss_format')->get();

        foreach ($competitions as $competition) {
            $hasMatches = GameMatch::where('game_id', $game->id)
                ->where('competition_id', $competition->id)
                ->exists();

            if (!$hasMatches) {
                continue;
            }

            $this->maybeGenerateKnockoutRound($game, $competition->id);
        }
    }

    public function afterMatches(Game $game, Collection $matches, Collection $allPlayers): void
    {
        $knockoutMatches = $matches->filter(fn ($m) => $m->cup_tie_id !== null);

        if ($knockoutMatches->isNotEmpty()) {
            $this->resolveCompletedTies($game, $knockoutMatches, $allPlayers);
        }
    }

    /**
     * Check if the season is complete (league phase + all knockout rounds).
     */
    public function isSeasonComplete(Game $game, string $competitionId): bool
    {
        $unplayedLeague = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists();

        if ($unplayedLeague) {
            return false;
        }

        $finalTie = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', SwissKnockoutGenerator::ROUND_FINAL)
            ->first();

        return $finalTie->completed ?? false;
    }

    /**
     * Check if league phase is complete and generate knockout rounds as needed.
     */
    private function maybeGenerateKnockoutRound(Game $game, string $competitionId): void
    {
        if (!$this->isLeaguePhaseComplete($game->id, $competitionId)) {
            return;
        }

        // Don't generate while a league-phase match is pending finalization —
        // its standings haven't been applied yet, so seedings would be wrong
        if ($game->hasPendingFinalizationForCompetition($competitionId)) {
            return;
        }

        $currentRound = $this->getCurrentRound($game->id, $competitionId);
        $nextRound = $currentRound + 1;

        if ($nextRound > SwissKnockoutGenerator::ROUND_FINAL) {
            return;
        }

        // For round 1 (knockout playoff), generate if it doesn't exist
        if ($nextRound === SwissKnockoutGenerator::ROUND_KNOCKOUT_PLAYOFF) {
            if (!$this->roundExists($game->id, $competitionId, $nextRound)) {
                $this->generateKnockoutRound($game, $competitionId, $nextRound);
            }
            return;
        }

        // For later rounds, check if previous round is complete
        $previousRoundComplete = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', $currentRound)
            ->where('completed', false)
            ->doesntExist();

        if ($previousRoundComplete && !$this->roundExists($game->id, $competitionId, $nextRound)) {
            $this->generateKnockoutRound($game, $competitionId, $nextRound);
        }
    }

    private function generateKnockoutRound(Game $game, string $competitionId, int $round): void
    {
        $config = $this->knockoutGenerator->getRoundConfig($round, $competitionId, $game->season);
        $matchups = $this->knockoutGenerator->generateMatchups($game, $competitionId, $round);

        foreach ($matchups as [$homeTeamId, $awayTeamId]) {
            $this->createTie($game, $competitionId, $homeTeamId, $awayTeamId, $config);
        }
    }

    private function isLeaguePhaseComplete(string $gameId, string $competitionId): bool
    {
        return !GameMatch::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists();
    }
}
