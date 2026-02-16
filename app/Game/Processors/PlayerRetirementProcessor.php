<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\PlayerRetirementService;
use App\Models\Game;
use App\Models\GamePlayer;

/**
 * Handles player retirements at the end of the season.
 *
 * Two phases:
 * 1. Retire players who announced retirement last season (retiring_at_season == oldSeason)
 *    - For non-user teams: generate a younger replacement of similar characteristics
 *    - For user's team: just remove (user had one season of warning)
 * 2. Announce new retirements for the coming season based on probability algorithm
 *
 * Priority: 7 (after contract expiration/renewal, before player development)
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
            ->where('retiring_at_season', $data->oldSeason)
            ->get();

        $retiredPlayers = [];

        foreach ($retiringPlayers as $player) {
            $isUserTeam = $player->team_id === $game->team_id;
            $replacementInfo = null;

            // Generate replacement for non-user teams
            if (!$isUserTeam) {
                $replacement = $this->retirementService->generateReplacementPlayer(
                    $game,
                    $player,
                    $data->newSeason
                );
                $replacementInfo = [
                    'id' => $replacement->id,
                    'name' => $replacement->player->name,
                    'age' => $replacement->player->age,
                    'position' => $replacement->position,
                ];
            }

            $retiredPlayers[] = [
                'playerId' => $player->id,
                'playerName' => $player->name,
                'age' => $player->age,
                'position' => $player->position,
                'teamId' => $player->team_id,
                'teamName' => $player->team->name,
                'wasUserTeam' => $isUserTeam,
                'replacement' => $replacementInfo,
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
        // Find players old enough to consider retirement who haven't announced yet
        $candidates = GamePlayer::with(['player', 'team'])
            ->where('game_id', $game->id)
            ->whereNull('retiring_at_season')
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
                'teamName' => $player->team->name,
                'wasUserTeam' => $player->team_id === $game->team_id,
            ];
        }

        return $announcements;
    }
}
