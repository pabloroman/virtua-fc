<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Player\PlayerAge;
use App\Modules\Player\Services\PlayerRetirementService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;

/**
 * Handles player retirements at the end of the season.
 *
 * Two phases:
 * 1. Retire players who announced retirement last season (retiring_at_season == oldSeason)
 *    - All retiring players are removed from the game
 *    - AIFreeAgentSigningProcessor (priority 8) signs free agents to fill AI roster gaps
 *    - SquadReplenishmentProcessor (priority 9) generates new players for remaining gaps
 * 2. Announce new retirements for the coming season based on probability algorithm
 *
 * Priority: 7 (after contract expiration/renewal, before squad replenishment)
 */
class PlayerRetirementProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly PlayerRetirementService $retirementService,
    ) {}

    public function priority(): int
    {
        return 40;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Phase 1: Retire players who announced retirement last season
        $retiredPlayers = $this->processRetirements($game, $data);

        // Phase 2: Announce new retirements for the coming season
        $announcements = $this->processAnnouncements($game, $data);

        $data->setMetadata('retiredPlayers', $retiredPlayers);
        $data->setMetadata('retirementAnnouncements', $announcements);

        return $data;
    }

    /**
     * Phase 1: Retire players whose retiring_at_season matches the ending season.
     */
    private function processRetirements(Game $game, SeasonTransitionData $data): array
    {
        // Include free agents (team_id IS NULL): a player who announced retirement
        // while on a team can have their contract expire in the same season closing
        // (ContractExpirationProcessor runs first), leaving team_id null. We still
        // want those players removed from the game.
        $retiringPlayers = GamePlayer::with(['player', 'team', 'game'])
            ->where('game_id', $game->id)
            ->where('retiring_at_season', $data->oldSeason)
            ->get();

        $retiredPlayers = [];
        $retiringIds = [];

        foreach ($retiringPlayers as $player) {
            $retiredPlayers[] = [
                'playerId' => $player->id,
                'playerName' => $player->name,
                'age' => $player->age($game->current_date),
                'position' => $player->position,
                'teamId' => $player->team_id,
                'teamName' => $player->team?->name ?? 'Unknown',
                'wasUserTeam' => $player->team_id === $game->team_id,
            ];
            $retiringIds[] = $player->id;
        }

        if (!empty($retiringIds)) {
            GamePlayer::whereIn('id', $retiringIds)->delete();
        }

        return $retiredPlayers;
    }

    /**
     * Phase 2: Evaluate all eligible players and announce retirements for next season.
     */
    private function processAnnouncements(Game $game, SeasonTransitionData $data): array
    {
        // Find players old enough to consider retirement who haven't announced yet.
        // Pre-filter by minimum retirement age (33 for outfield) to reduce the candidate
        // set from ~500 to ~20-40 before PHP-side probability evaluation.
        $minRetirementCutoff = PlayerAge::dateOfBirthCutoff(PlayerAge::MIN_RETIREMENT_OUTFIELD, $game->current_date);

        // Resolve eligible biographical players first (control plane), then
        // filter game-side rows by player_id. Replaces a whereHas('player', …)
        // subquery that would cross the control/tenant plane boundary.
        $eligiblePlayerIds = Player::where('date_of_birth', '<=', $minRetirementCutoff)->pluck('id');

        // matchState is eager-loaded because shouldRetire() reads
        // fitness/season_appearances via the GamePlayer accessor delegates.
        // Free agents (team_id IS NULL) are included so they also receive
        // retirement announcements on the same cadence as rostered players.
        $candidates = GamePlayer::with(['player', 'team', 'game', 'matchState'])
            ->where('game_id', $game->id)
            ->whereNull('retiring_at_season')
            ->whereIn('player_id', $eligiblePlayerIds)
            ->get()
            ->filter(fn (GamePlayer $player) => $this->retirementService->shouldRetire($player));

        $announcements = [];
        $announcedIds = [];

        foreach ($candidates as $player) {
            $announcements[] = [
                'playerId' => $player->id,
                'playerName' => $player->name,
                'age' => $player->age($game->current_date),
                'position' => $player->position,
                'teamId' => $player->team_id,
                'teamName' => $player->team?->name ?? 'Unknown',
                'wasUserTeam' => $player->team_id === $game->team_id,
            ];
            $announcedIds[] = $player->id;
        }

        if (!empty($announcedIds)) {
            GamePlayer::whereIn('id', $announcedIds)->update(['retiring_at_season' => $data->newSeason]);
        }

        return $announcements;
    }
}
