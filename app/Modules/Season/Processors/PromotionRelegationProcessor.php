<?php

namespace App\Modules\Season\Processors;

use App\Modules\Competition\Contracts\SelfSwappingPromotionRule;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Exceptions\PlayoffInProgressException;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\SeasonSimulationProcessor;
use App\Modules\Competition\Promotions\PromotionRelegationFactory;
use App\Models\Game;
use App\Models\CompetitionEntry;
use App\Models\GameStanding;
use Illuminate\Support\Facades\DB;

/**
 * Handles promotion and relegation between divisions.
 *
 * Uses PromotionRelegationFactory to get the rules for each country/league system.
 * Rules define which positions are relegated/promoted and whether playoffs are involved.
 *
 * Priority: 85 (runs after supercup qualification, before reputation update)
 */
class PromotionRelegationProcessor implements SeasonProcessor
{
    public function __construct(
        private PromotionRelegationFactory $ruleFactory,
        private SeasonSimulationProcessor $simulationProcessor,
        private PlayoffGeneratorFactory $playoffFactory,
    ) {}

    public function priority(): int
    {
        return 85;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Pre-flight invariant: no playoff may be in progress when the closing
        // pipeline runs promotion/relegation. Rules that see an in-progress
        // playoff would otherwise throw mid-loop, leaving the transaction
        // rolled back but no clear signal to the caller. Fail loudly and
        // explicitly upfront so ProcessSeasonTransition can handle it cleanly.
        $this->assertNoPlayoffInProgress($game);

        return DB::transaction(function () use ($game, $data) {
            $userPromoted = [];
            $userRelegated = [];
            $affectedCompetitionIds = [];

            // Identify the user's promotion/relegation rule (if any)
            $userRule = $this->ruleFactory->forCompetition($data->competitionId);

            // Two-pass approach: gather all promoted/relegated teams while
            // standings are pristine, then execute swaps. This prevents an
            // earlier rule's swap from corrupting standings that a later rule
            // reads — e.g. the ESP1↔ESP2 swap inserting teams at the bottom
            // of ESP2 before the ESP2↔ESP3 rule reads positions 19–22.

            // Pass 1: Read — collect promoted/relegated teams from every rule.
            $ruleData = [];
            foreach ($this->ruleFactory->all() as $rule) {
                $promoted = $rule->getPromotedTeams($game);
                $relegated = $rule->getRelegatedTeams($game);

                if (empty($promoted) && empty($relegated)) {
                    continue;
                }

                if (count($promoted) !== count($relegated)) {
                    throw new \RuntimeException(
                        "Promotion/relegation imbalance between {$rule->getTopDivision()} and {$rule->getBottomDivision()}: " .
                        count($promoted) . ' promoted vs ' . count($relegated) . ' relegated. ' .
                        'Cannot proceed with unbalanced swap.'
                    );
                }

                $ruleData[] = compact('rule', 'promoted', 'relegated');
            }

            // Pass 2: Write — execute swaps now that all reads are done.
            foreach ($ruleData as ['rule' => $rule, 'promoted' => $promoted, 'relegated' => $relegated]) {
                if ($rule instanceof SelfSwappingPromotionRule) {
                    $rule->performSwap($game, $promoted, $relegated);

                    $affectedCompetitionIds[] = $rule->getTopDivision();
                    foreach ($promoted as $entry) {
                        if (!empty($entry['origin'])) {
                            $affectedCompetitionIds[] = $entry['origin'];
                        }
                    }
                } else {
                    $this->swapTeams(
                        promoted: $promoted,
                        relegated: $relegated,
                        topDivision: $rule->getTopDivision(),
                        bottomDivision: $rule->getBottomDivision(),
                        gameId: $game->id,
                    );

                    $affectedCompetitionIds[] = $rule->getTopDivision();
                    $affectedCompetitionIds[] = $rule->getBottomDivision();
                }

                // Track user-relevant promotions/relegations for the transition log
                if ($userRule
                    && $rule->getTopDivision() === $userRule->getTopDivision()
                    && $rule->getBottomDivision() === $userRule->getBottomDivision()
                ) {
                    $userPromoted = array_merge($userPromoted, $promoted);
                    $userRelegated = array_merge($userRelegated, $relegated);
                }
            }

            // Update competition in transition data if the player's team moved
            $game->refresh();
            if ($game->competition_id !== $data->competitionId) {
                $data->competitionId = $game->competition_id;
            }

            // Re-simulate only the leagues that had roster changes from promotion/relegation
            if (!empty($affectedCompetitionIds)) {
                $this->resimulateAffectedLeagues($game, array_unique($affectedCompetitionIds));
            }

            // Store only the user's division promotions/relegations for the transition log
            $data->setMetadata('promotedTeams', $userPromoted);
            $data->setMetadata('relegatedTeams', $userRelegated);

            return $data;
        });
    }

    /**
     * Swap teams between divisions.
     */
    private function swapTeams(
        array $promoted,
        array $relegated,
        string $topDivision,
        string $bottomDivision,
        string $gameId,
    ): void {
        $promotedIds = array_column($promoted, 'teamId');
        $relegatedIds = array_column($relegated, 'teamId');
        $playerTeamId = Game::where('id', $gameId)->value('team_id');

        // Move relegated teams: top → bottom
        foreach ($relegatedIds as $teamId) {
            $this->moveTeam($teamId, $topDivision, $bottomDivision, $gameId, $playerTeamId);
        }

        // Move promoted teams: bottom → top
        foreach ($promotedIds as $teamId) {
            $this->moveTeam($teamId, $bottomDivision, $topDivision, $gameId, $playerTeamId);
        }

        // Re-sort positions in both divisions
        $this->resortPositions($gameId, $topDivision);
        $this->resortPositions($gameId, $bottomDivision);
    }

    /**
     * Move a team from one division to another.
     */
    private function moveTeam(
        string $teamId,
        string $fromDivision,
        string $toDivision,
        string $gameId,
        string $playerTeamId,
    ): void {
        // Update competition_entries
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $fromDivision)
            ->where('team_id', $teamId)
            ->delete();

        CompetitionEntry::updateOrCreate(
            [
                'game_id' => $gameId,
                'competition_id' => $toDivision,
                'team_id' => $teamId,
            ],
            ['entry_round' => 1]
        );

        // Update game_standings.
        // Delete from source (if exists — simulated leagues have no standings).
        GameStanding::where('game_id', $gameId)
            ->where('competition_id', $fromDivision)
            ->where('team_id', $teamId)
            ->delete();

        $targetHasStandings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $toDivision)
            ->exists();

        $isPlayerTeam = $teamId === $playerTeamId;

        if ($targetHasStandings) {
            // Target division already has real standings — just add this team
            GameStanding::firstOrCreate([
                'game_id' => $gameId,
                'competition_id' => $toDivision,
                'team_id' => $teamId,
            ], [
                'position' => 99, // Will be re-sorted
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }

        // Update game's primary competition if the player's team moved
        if ($isPlayerTeam) {
            Game::where('id', $gameId)
                ->where('team_id', $teamId)
                ->update(['competition_id' => $toDivision]);
        }
    }

    /**
     * Re-simulate only the leagues affected by promotion/relegation roster changes.
     *
     * @param  string[]  $competitionIds  Competition IDs that had team swaps
     */
    private function resimulateAffectedLeagues(Game $game, array $competitionIds): void
    {
        $this->simulationProcessor->simulateNonPlayedLeagues($game, $competitionIds, forceResimulate: true);
    }

    /**
     * Re-sort positions in a competition (1, 2, 3, ...).
     */
    private function resortPositions(string $gameId, string $competitionId): void
    {
        $standings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->orderBy('position')
            ->get();

        if ($standings->isEmpty()) {
            return;
        }

        foreach ($standings->values() as $index => $standing) {
            $newPosition = $index + 1;
            if ($standing->position !== $newPosition) {
                $standing->update(['position' => $newPosition]);
            }
        }
    }

    /**
     * Refuse to run if any configured playoff is still in progress for this
     * game. The per-rule getPromotedTeams() calls also throw on InProgress,
     * but enforcing this upfront avoids starting a DB transaction we're just
     * going to roll back and gives the caller a single clear signal.
     */
    private function assertNoPlayoffInProgress(Game $game): void
    {
        foreach ($this->playoffFactory->all() as $generator) {
            if ($generator->state($game) === PlayoffState::InProgress) {
                throw PlayoffInProgressException::forCompetition($generator->getCompetitionId());
            }
        }
    }
}
