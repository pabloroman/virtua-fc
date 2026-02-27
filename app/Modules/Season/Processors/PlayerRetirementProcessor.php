<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Squad\Services\PlayerRetirementService;
use App\Models\Game;
use App\Models\GamePlayer;

/**
 * Handles player retirements at the end of the season.
 *
 * Two phases:
 * 1. Retire players who announced retirement last season (retiring_at_season == oldSeason)
 *    - All retiring players are removed from the game
 *    - SquadReplenishmentProcessor (priority 8) fills any resulting AI roster gaps
 * 2. Announce new retirements for the coming season based on probability algorithm
 *
 * Priority: 7 (after contract expiration/renewal, before squad replenishment)
 */
class PlayerRetirementProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly PlayerRetirementService $retirementService,
    ) {}

    public function priority(): int
    {
        return 7;
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
        $retiringPlayers = GamePlayer::with(['player', 'team'])
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('retiring_at_season', $data->oldSeason)
            ->get();

        $retiredPlayers = [];

        foreach ($retiringPlayers as $player) {
            $retiredPlayers[] = [
                'playerId' => $player->id,
                'playerName' => $player->name,
                'age' => $player->age,
                'position' => $player->position,
                'teamId' => $player->team_id,
                'teamName' => $player->team?->name ?? 'Unknown',
                'wasUserTeam' => $player->team_id === $game->team_id,
            ];

            $player->delete();
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
        $minRetirementCutoff = now()->subYears(33);

        $candidates = GamePlayer::with(['player', 'team'])
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->whereNull('retiring_at_season')
            ->whereHas('player', fn ($q) => $q->where('date_of_birth', '<=', $minRetirementCutoff))
            ->get()
            ->filter(fn (GamePlayer $player) => $this->retirementService->shouldRetire($player));

        $announcements = [];

        foreach ($candidates as $player) {
            $player->update(['retiring_at_season' => $data->newSeason]);

            $announcements[] = [
                'playerId' => $player->id,
                'playerName' => $player->name,
                'age' => $player->age,
                'position' => $player->position,
                'teamId' => $player->team_id,
                'teamName' => $player->team?->name ?? 'Unknown',
                'wasUserTeam' => $player->team_id === $game->team_id,
            ];
        }

        return $announcements;
    }
}
