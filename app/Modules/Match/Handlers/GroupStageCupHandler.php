<?php

namespace App\Modules\Match\Handlers;

use App\Models\Competition;
use App\Modules\Competition\Services\WorldCupKnockoutGenerator;
use App\Modules\Match\Services\CupTieResolver;
use App\Modules\Squad\Services\EligibilityService;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;

/**
 * Handler for group stage + knockout competitions (World Cup).
 *
 * Group phase: teams play round-robin within groups (league-style, batched by round_number).
 * Knockout phase: single-leg ties with extra time & penalties, generated progressively.
 *
 * FIFA 2026 format: 48 teams, 12 groups → R32 → R16 → QF → SF → 3rd place + Final.
 */
class GroupStageCupHandler extends CupCompetitionHandler
{
    public function __construct(
        CupTieResolver $tieResolver,
        EligibilityService $eligibilityService,
        private readonly WorldCupKnockoutGenerator $knockoutGenerator,
    ) {
        parent::__construct($tieResolver, $eligibilityService);
    }

    public function getType(): string
    {
        return 'group_stage_cup';
    }

    public function getMatchBatch(string $gameId, GameMatch $nextMatch): Collection
    {
        return $this->getHybridMatchBatch($gameId, $nextMatch, filterCupTieNull: true);
    }

    public function beforeMatches(Game $game, string $targetDate): void
    {
        $competitions = Competition::where('handler_type', 'group_stage_cup')->get();

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
     * Check if group stage is complete and generate knockout rounds as needed.
     */
    private function maybeGenerateKnockoutRound(Game $game, string $competitionId): void
    {
        if (!$this->isGroupStageComplete($game->id, $competitionId)) {
            return;
        }

        // Don't generate while a group-stage match is pending finalization —
        // its standings haven't been applied yet, so seedings would be wrong
        if ($game->hasPendingFinalizationForCompetition($competitionId)) {
            return;
        }

        $currentRound = $this->getCurrentRound($game->id, $competitionId);
        $finalRound = $this->knockoutGenerator->getFinalRound($competitionId);

        if ($currentRound === 0) {
            // No knockout rounds yet — generate the first one
            $qualifiedTeams = $this->knockoutGenerator->getQualifiedTeams($game->id, $competitionId);
            $firstRound = $this->knockoutGenerator->getFirstKnockoutRound(count($qualifiedTeams));

            if (!$this->roundExists($game->id, $competitionId, $firstRound)) {
                $this->generateKnockoutRound($game, $competitionId, $firstRound);
            }

            return;
        }

        // Check if current round is complete
        $currentRoundComplete = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', $currentRound)
            ->where('completed', false)
            ->doesntExist();

        if (!$currentRoundComplete) {
            return;
        }

        // Reset yellow cards if the completed round matches the reset threshold
        $rules = $this->eligibilityService->rulesForHandlerType('group_stage_cup');
        if ($rules->yellowCardResetAfterRound === $currentRound) {
            $this->eligibilityService->resetYellowCardsForCompetition($game->id, $competitionId);
        }

        // After semi-finals, generate both 3rd-place match AND final
        if ($currentRound === WorldCupKnockoutGenerator::ROUND_SEMI_FINALS) {
            $thirdPlaceRound = WorldCupKnockoutGenerator::ROUND_THIRD_PLACE;
            $finalRoundNum = WorldCupKnockoutGenerator::ROUND_FINAL;

            if (!$this->roundExists($game->id, $competitionId, $thirdPlaceRound)) {
                $this->generateKnockoutRound($game, $competitionId, $thirdPlaceRound);
            }
            if (!$this->roundExists($game->id, $competitionId, $finalRoundNum)) {
                $this->generateKnockoutRound($game, $competitionId, $finalRoundNum);
            }

            return;
        }

        // For other rounds, generate the next one sequentially
        $nextRound = $currentRound + 1;

        // Skip third-place round in sequential progression (it's generated with the final after SF)
        if ($nextRound === WorldCupKnockoutGenerator::ROUND_THIRD_PLACE) {
            return;
        }

        if ($nextRound > $finalRound) {
            return;
        }

        if (!$this->roundExists($game->id, $competitionId, $nextRound)) {
            $this->generateKnockoutRound($game, $competitionId, $nextRound);
        }
    }

    private function generateKnockoutRound(Game $game, string $competitionId, int $round): void
    {
        $config = $this->knockoutGenerator->getRoundConfig($round, $competitionId, $game->season);
        $matchups = $this->knockoutGenerator->generateMatchups($game, $competitionId, $round);

        foreach ($matchups as [$homeTeamId, $awayTeamId, $bracketPosition]) {
            $this->createTie($game, $competitionId, $homeTeamId, $awayTeamId, $config, $bracketPosition);
        }
    }

    private function isGroupStageComplete(string $gameId, string $competitionId): bool
    {
        return !GameMatch::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists();
    }
}
