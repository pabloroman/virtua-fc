<?php

namespace Tests\Traits;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use Illuminate\Support\Collection;

trait CreatesLineups
{
    /**
     * Create a lineup of GamePlayers for a team.
     */
    private function createLineup(Game $game, Team $team, int $count = 11, int $ability = 70): Collection
    {
        $positions = [
            'Goalkeeper',
            'Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back',
            'Central Midfield', 'Central Midfield', 'Defensive Midfield',
            'Right Winger', 'Left Winger',
            'Centre-Forward',
        ];

        $players = collect();
        for ($i = 0; $i < $count; $i++) {
            $player = GamePlayer::factory()
                ->forGame($game)
                ->forTeam($team)
                ->create([
                    'position' => $positions[$i] ?? 'Central Midfield',
                    'game_technical_ability' => $ability,
                    'game_physical_ability' => $ability,
                    'fitness' => 95,
                    'morale' => 80,
                ]);

            $player->setRelation('game', $game);
            $players->push($player);
        }

        return $players;
    }
}
