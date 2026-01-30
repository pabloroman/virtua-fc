<?php

namespace App\Http\Views;

use App\Game\Services\DevelopmentCurve;
use App\Game\Services\PlayerDevelopmentService;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Http\Request;

/**
 * View controller for squad development screen.
 *
 * Displays player potential, development status, and projections.
 */
class ShowSquadDevelopment
{
    public function __construct(
        private readonly PlayerDevelopmentService $developmentService,
    ) {}

    public function __invoke(string $gameId, Request $request)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Get all players for the user's team with development data
        $players = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->get()
            ->map(function ($player) {
                // Add projection data
                $player->projection = $this->developmentService->getNextSeasonProjection($player);
                $player->development_status = DevelopmentCurve::getStatus($player->age);
                return $player;
            })
            ->sortBy(fn ($p) => $this->sortOrder($p));

        // Apply filter if specified
        $filter = $request->query('filter', 'all');
        $filteredPlayers = $this->applyFilter($players, $filter);

        // Calculate stats for filter badges
        $stats = [
            'high_potential' => $players->filter(fn ($p) => $this->isHighPotential($p))->count(),
            'growing' => $players->filter(fn ($p) => $p->development_status === 'growing')->count(),
            'declining' => $players->filter(fn ($p) => $p->development_status === 'declining')->count(),
            'all' => $players->count(),
        ];

        return view('squad-development', [
            'game' => $game,
            'players' => $filteredPlayers,
            'filter' => $filter,
            'stats' => $stats,
        ]);
    }

    /**
     * Apply filter to players collection.
     */
    private function applyFilter($players, string $filter)
    {
        return match ($filter) {
            'high_potential' => $players->filter(fn ($p) => $this->isHighPotential($p)),
            'growing' => $players->filter(fn ($p) => $p->development_status === 'growing'),
            'declining' => $players->filter(fn ($p) => $p->development_status === 'declining'),
            default => $players,
        };
    }

    /**
     * Check if player has high potential (significant gap between current and potential).
     */
    private function isHighPotential($player): bool
    {
        if (!$player->potential_high) {
            return false;
        }

        $currentAbility = (int) round(
            ($player->current_technical_ability + $player->current_physical_ability) / 2
        );

        // High potential = at least 8 points gap to potential ceiling
        return ($player->potential_high - $currentAbility) >= 8;
    }

    /**
     * Get sort order for players (by projection desc, then age asc).
     */
    private function sortOrder($player): string
    {
        // Sort by projection (descending - highest growth first)
        // Then by age (ascending - youngest first)
        $projection = 100 - ($player->projection + 50); // Normalize to make desc sort work
        $age = str_pad($player->age, 2, '0', STR_PAD_LEFT);

        return "{$projection}-{$age}";
    }
}
