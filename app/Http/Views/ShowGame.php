<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;

class ShowGame
{
    private const LOW_FITNESS_THRESHOLD = 70;
    private const YELLOW_CARD_WARNING_THRESHOLD = 4; // 5 yellows = suspension

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Get next match for player's team
        $nextMatch = $game->next_match;
        if ($nextMatch) {
            $nextMatch->load(['homeTeam', 'awayTeam', 'competition']);
        }

        // Get player's standing
        $playerStanding = GameStanding::with('team')
            ->where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->first();

        // Get leader's points for comparison
        $leaderStanding = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->orderBy('position')
            ->first();

        // Get recent form for player's team (last 5 played matches)
        $playerForm = $this->getTeamForm($gameId, $game->team_id, 5);

        // Get opponent's recent form if there's a next match
        $opponentForm = [];
        if ($nextMatch) {
            $opponentId = $nextMatch->home_team_id === $game->team_id
                ? $nextMatch->away_team_id
                : $nextMatch->home_team_id;
            $opponentForm = $this->getTeamForm($gameId, $opponentId, 5);
        }

        // Get upcoming fixtures (next 5 matches for player's team)
        $upcomingFixtures = GameMatch::with(['homeTeam', 'awayTeam', 'competition'])
            ->where('game_id', $gameId)
            ->where('played', false)
            ->where(function ($query) use ($game) {
                $query->where('home_team_id', $game->team_id)
                    ->orWhere('away_team_id', $game->team_id);
            })
            ->orderBy('scheduled_date')
            ->limit(5)
            ->get();

        // Get squad alerts
        $squadAlerts = $this->getSquadAlerts($game, $nextMatch);

        return view('game', [
            'game' => $game,
            'nextMatch' => $nextMatch,
            'playerStanding' => $playerStanding,
            'leaderStanding' => $leaderStanding,
            'playerForm' => $playerForm,
            'opponentForm' => $opponentForm,
            'upcomingFixtures' => $upcomingFixtures,
            'squadAlerts' => $squadAlerts,
        ]);
    }

    /**
     * Get recent form for a team (W/D/L results).
     */
    private function getTeamForm(string $gameId, string $teamId, int $limit): array
    {
        $matches = GameMatch::where('game_id', $gameId)
            ->where('played', true)
            ->where(function ($query) use ($teamId) {
                $query->where('home_team_id', $teamId)
                    ->orWhere('away_team_id', $teamId);
            })
            ->orderByDesc('played_at')
            ->limit($limit)
            ->get();

        $form = [];
        foreach ($matches as $match) {
            $isHome = $match->home_team_id === $teamId;
            $teamScore = $isHome ? $match->home_score : $match->away_score;
            $opponentScore = $isHome ? $match->away_score : $match->home_score;

            if ($teamScore > $opponentScore) {
                $form[] = 'W';
            } elseif ($teamScore < $opponentScore) {
                $form[] = 'L';
            } else {
                $form[] = 'D';
            }
        }

        // Reverse so oldest is first (left to right chronological)
        return array_reverse($form);
    }

    /**
     * Get squad alerts for the player's team.
     */
    private function getSquadAlerts(Game $game, ?GameMatch $nextMatch): array
    {
        $currentDate = $game->current_date;
        $nextMatchday = $nextMatch?->round_number ?? $game->current_matchday + 1;

        $players = GamePlayer::with('player')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get();

        $alerts = [
            'injured' => [],
            'suspended' => [],
            'lowFitness' => [],
            'yellowCardRisk' => [],
        ];

        foreach ($players as $player) {
            // Injured players
            if ($player->injury_until && $player->injury_until->gt($currentDate)) {
                $daysRemaining = $currentDate->diffInDays($player->injury_until);
                $alerts['injured'][] = [
                    'player' => $player,
                    'reason' => $player->injury_type ?? 'Injury',
                    'returnDate' => $player->injury_until,
                    'daysRemaining' => $daysRemaining,
                ];
            }

            // Suspended players
            if ($player->suspended_until_matchday && $player->suspended_until_matchday > $nextMatchday) {
                $matchesRemaining = $player->suspended_until_matchday - $nextMatchday;
                $alerts['suspended'][] = [
                    'player' => $player,
                    'matchesRemaining' => $matchesRemaining,
                ];
            }

            // Low fitness (only for available players)
            if ($player->fitness < self::LOW_FITNESS_THRESHOLD &&
                !$player->isInjured($currentDate) &&
                !$player->isSuspended($nextMatchday)) {
                $alerts['lowFitness'][] = [
                    'player' => $player,
                    'fitness' => $player->fitness,
                ];
            }

            // Yellow card risk (4 yellows = 1 away from suspension)
            if ($player->yellow_cards >= self::YELLOW_CARD_WARNING_THRESHOLD) {
                $alerts['yellowCardRisk'][] = [
                    'player' => $player,
                    'yellowCards' => $player->yellow_cards,
                ];
            }
        }

        // Sort by severity/urgency
        usort($alerts['injured'], fn($a, $b) => $a['daysRemaining'] <=> $b['daysRemaining']);
        usort($alerts['lowFitness'], fn($a, $b) => $a['fitness'] <=> $b['fitness']);
        usort($alerts['yellowCardRisk'], fn($a, $b) => $b['yellowCards'] <=> $a['yellowCards']);

        return $alerts;
    }
}
