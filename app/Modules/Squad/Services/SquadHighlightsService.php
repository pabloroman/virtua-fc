<?php

namespace App\Modules\Squad\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Support\PositionMapper;

class SquadHighlightsService
{
    /**
     * Compute squad highlights by comparing user's picks against the full candidate roster.
     *
     * "Bold picks" = selected players with overall below median for their position group
     * who performed well in the tournament.
     * "Key omissions" = unselected players with overall above 80th percentile.
     *
     * @return array{bold_picks: array, omissions: array, top_scorer: array|null}
     */
    public function compute(Game $game): array
    {
        $transfermarktId = $game->team->transfermarkt_id;
        $jsonPath = base_path("data/2025/WC2026/teams/{$transfermarktId}.json");

        if (!file_exists($jsonPath)) {
            return ['bold_picks' => [], 'omissions' => [], 'top_scorer' => null];
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        $jsonPlayers = collect($data['players'] ?? []);

        // Load Player models for ability data
        $tmIds = $jsonPlayers->pluck('id')->toArray();
        $playerModels = Player::whereIn('transfermarkt_id', $tmIds)->get()->keyBy('transfermarkt_id');

        // Build candidates with overall ratings grouped by position
        $candidates = [];
        foreach ($jsonPlayers as $jp) {
            $tmId = $jp['id'] ?? null;
            $player = $playerModels->get($tmId);
            if (!$player) {
                continue;
            }

            $overall = (int) round(($player->technical_ability + $player->physical_ability) / 2);
            $positionGroup = PositionMapper::getPositionGroup($jp['position'] ?? 'Central Midfield');

            $candidates[$tmId] = [
                'transfermarkt_id' => (string) $tmId,
                'name' => $player->name,
                'position' => $jp['position'] ?? 'Central Midfield',
                'position_group' => $positionGroup,
                'overall' => $overall,
            ];
        }

        // Load user's selected GamePlayers with performance stats
        $gamePlayers = GamePlayer::with('player')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get();

        $selectedTmIds = $gamePlayers->pluck('player.transfermarkt_id')->filter()->toArray();

        // Compute median overall per position group
        $byGroup = collect($candidates)->groupBy('position_group');
        $medians = [];
        $percentile80 = [];
        foreach ($byGroup as $group => $players) {
            $overalls = $players->pluck('overall')->sort()->values();
            $count = $overalls->count();
            if ($count > 0) {
                $medians[$group] = $overalls->get((int) floor($count / 2));
                $percentile80[$group] = $overalls->get((int) floor($count * 0.8));
            }
        }

        // Find bold picks: selected players below median who performed
        $boldPicks = [];
        foreach ($gamePlayers as $gp) {
            $tmId = $gp->player?->transfermarkt_id;
            if (!$tmId || !isset($candidates[$tmId])) {
                continue;
            }

            $candidate = $candidates[$tmId];
            $median = $medians[$candidate['position_group']] ?? 70;

            if ($candidate['overall'] < $median && $gp->appearances > 0) {
                $impactScore = ($gp->goals * 3) + ($gp->assists * 2) + $gp->appearances;
                $boldPicks[] = [
                    'name' => $candidate['name'],
                    'position' => $candidate['position'],
                    'overall' => $candidate['overall'],
                    'goals' => $gp->goals,
                    'assists' => $gp->assists,
                    'appearances' => $gp->appearances,
                    'impact_score' => $impactScore,
                ];
            }
        }

        // Sort by impact and take top 3
        usort($boldPicks, fn ($a, $b) => $b['impact_score'] <=> $a['impact_score']);
        $boldPicks = array_slice($boldPicks, 0, 3);

        // Find key omissions: unselected players above 80th percentile
        $omissions = [];
        foreach ($candidates as $tmId => $candidate) {
            if (in_array($tmId, $selectedTmIds)) {
                continue;
            }

            $threshold = $percentile80[$candidate['position_group']] ?? 80;
            if ($candidate['overall'] >= $threshold) {
                $omissions[] = [
                    'name' => $candidate['name'],
                    'position' => $candidate['position'],
                    'overall' => $candidate['overall'],
                ];
            }
        }

        // Sort omissions by overall desc, take top 3
        usort($omissions, fn ($a, $b) => $b['overall'] <=> $a['overall']);
        $omissions = array_slice($omissions, 0, 3);

        // Find user's top scorer
        $topScorer = $gamePlayers->sortByDesc('goals')->first();
        $topScorerData = null;
        if ($topScorer && $topScorer->goals > 0) {
            $topScorerData = [
                'name' => $topScorer->player?->name ?? '?',
                'goals' => $topScorer->goals,
                'assists' => $topScorer->assists,
            ];
        }

        return [
            'bold_picks' => $boldPicks,
            'omissions' => $omissions,
            'top_scorer' => $topScorerData,
        ];
    }

    /**
     * Get the transfermarkt IDs of all selected squad players for a game.
     */
    public function getSquadTransfermarktIds(Game $game): array
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->with('player')
            ->get()
            ->pluck('player.transfermarkt_id')
            ->filter()
            ->values()
            ->toArray();
    }
}
