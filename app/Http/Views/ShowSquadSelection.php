<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\Player;
use App\Support\PositionMapper;

class ShowSquadSelection
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Only for tournament mode during onboarding
        if (!$game->isTournamentMode() || !$game->needsOnboarding()) {
            return redirect()->route('show-game', $gameId);
        }

        // Wait for background setup to finish
        if (!$game->isSetupComplete()) {
            return redirect()->route('game.onboarding', $gameId);
        }

        $candidates = $this->loadCandidates($game);

        return view('squad-selection', [
            'game' => $game,
            'candidatesByGroup' => $candidates,
        ]);
    }

    private function loadCandidates(Game $game): array
    {
        $transfermarktId = $game->team->transfermarkt_id;
        $jsonPath = base_path("data/2025/WC/teams/{$transfermarktId}.json");

        if (!file_exists($jsonPath)) {
            return ['goalkeepers' => [], 'defenders' => [], 'midfielders' => [], 'forwards' => []];
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        $jsonPlayers = $data['players'] ?? [];

        // Get transfermarkt IDs and look up Player models for abilities
        $tmIds = array_column($jsonPlayers, 'id');
        $playerModels = Player::whereIn('transfermarkt_id', $tmIds)->get()->keyBy('transfermarkt_id');

        $groups = ['goalkeepers' => [], 'defenders' => [], 'midfielders' => [], 'forwards' => []];

        foreach ($jsonPlayers as $jp) {
            $tmId = $jp['id'] ?? null;
            if (!$tmId) {
                continue;
            }

            $player = $playerModels->get($tmId);
            if (!$player) {
                continue;
            }

            $position = $jp['position'] ?? 'Central Midfield';
            $positionGroup = PositionMapper::getPositionGroup($position);
            $positionDisplay = PositionMapper::getPositionDisplay($position);
            $technical = $player->technical_ability;
            $physical = $player->physical_ability;
            $overall = (int) round(($technical + $physical) / 2);

            $candidate = [
                'transfermarkt_id' => $tmId,
                'player_id' => $player->id,
                'name' => $player->name,
                'position' => $position,
                'position_group' => $positionGroup,
                'position_abbreviation' => $positionDisplay['abbreviation'],
                'position_bg' => $positionDisplay['bg'],
                'position_text' => $positionDisplay['text'],
                'age' => $player->age,
                'height' => $jp['height'] ?? null,
                'technical' => $technical,
                'physical' => $physical,
                'overall' => $overall,
            ];

            $groupKey = match ($positionGroup) {
                'Goalkeeper' => 'goalkeepers',
                'Defender' => 'defenders',
                'Midfielder' => 'midfielders',
                'Forward' => 'forwards',
                default => 'midfielders',
            };

            $groups[$groupKey][] = $candidate;
        }

        // Sort each group by overall descending
        foreach ($groups as &$group) {
            usort($group, fn ($a, $b) => $b['overall'] <=> $a['overall']);
        }

        return $groups;
    }
}
