<?php

namespace App\Modules\Match\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\TeamReputation;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\LineupService;
use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\DTOs\MatchResult;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Support\Collection;

/**
 * Full match simulation for player-involved batches.
 *
 * Handles lineup generation, tactical instruction selection, forfeit checks,
 * and delegates to MatchSimulator for the actual match engine. This is the
 * "rich" simulation path used when the user's team is involved — as opposed
 * to AIMatchResolver which handles AI-vs-AI matches statistically.
 */
class FullMatchSimulationService
{
    public function __construct(
        private readonly MatchSimulator $matchSimulator,
        private readonly LineupService $lineupService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Resolve matches for a batch that may involve the player's team.
     *
     * Handles lineup generation, forfeit checks, and full match simulation.
     *
     * @param  Collection<GameMatch>  $matches
     * @param  Game  $game
     * @param  Collection  $allPlayers  Players grouped by team_id
     * @param  array<string, array<string>>  $suspendedByCompetition
     * @return array{matchResults: array, playerMatch: ?GameMatch}
     */
    public function resolveMatches(Collection $matches, Game $game, $allPlayers, array $suspendedByCompetition): array
    {
        $teamIds = $matches->pluck('home_team_id')
            ->merge($matches->pluck('away_team_id'))
            ->push($game->team_id)
            ->unique()
            ->values();

        $clubProfiles = TeamReputation::where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)->get()->keyBy('team_id');

        $this->lineupService->ensureLineupsForMatches($matches, $game, $allPlayers, $suspendedByCompetition, $clubProfiles);

        // --- Check for forfeit (user's team has < 7 available players) ---
        $playerMatch = $matches->first(fn ($m) => $m->involvesTeam($game->team_id));
        $forfeitResult = null;

        if ($playerMatch) {
            $isUserHome = $playerMatch->isHomeTeam($game->team_id);
            $userLineupField = $isUserHome ? 'home_lineup' : 'away_lineup';
            $userLineupCount = count($playerMatch->$userLineupField ?? []);
            $userSquadSize = $allPlayers->get($game->team_id, collect())->count();

            // Only forfeit if the team actually has players but too few available.
            // A squad of 0 means the game is in a test/setup state — let the simulator handle it.
            if ($userSquadSize > 0 && $userLineupCount < 7) {
                $forfeitResult = [
                    'matchId' => $playerMatch->id,
                    'homeTeamId' => $playerMatch->home_team_id,
                    'awayTeamId' => $playerMatch->away_team_id,
                    'homeScore' => $isUserHome ? 0 : 3,
                    'awayScore' => $isUserHome ? 3 : 0,
                    'homePossession' => 50,
                    'awayPossession' => 50,
                    'competitionId' => $playerMatch->competition_id,
                    'events' => [],
                ];

                $this->notificationService->notifyMatchForfeit($game);
            }
        }

        // --- Simulate matches (skip forfeited match) ---
        $forfeitedMatchId = $forfeitResult ? $playerMatch->id : null;
        $matchesToSimulate = $forfeitedMatchId
            ? $matches->reject(fn ($m) => $m->id === $forfeitedMatchId)
            : $matches;

        $matchResults = [];
        foreach ($matchesToSimulate as $match) {
            $matchResults[] = $this->simulateMatch($match, $allPlayers, $game);
        }

        if ($forfeitResult) {
            $matchResults[] = $forfeitResult;
            // Forfeited match is not a live match — process all effects immediately
            $playerMatch = null;
        }

        return ['matchResults' => $matchResults, 'playerMatch' => $playerMatch];
    }

    private function simulateMatch(GameMatch $match, $allPlayers, Game $game): array
    {
        $homePlayers = $this->getLineupPlayers($match, $allPlayers, 'home');
        $awayPlayers = $this->getLineupPlayers($match, $allPlayers, 'away');

        // Don't pass bench players for the user's team — they make their own
        // substitution decisions during the live match. The simulator already
        // guards with `$benchPlayers !== null`, so injury events are still
        // generated but no auto-substitution follows.
        $isUserMatch = $match->involvesTeam($game->team_id);
        $isUserHome = $isUserMatch && $match->isHomeTeam($game->team_id);

        $homeBenchPlayers = $isUserHome ? null : $this->getBenchPlayers($match, $allPlayers, 'home', $game);
        $awayBenchPlayers = ($isUserMatch && ! $isUserHome) ? null : $this->getBenchPlayers($match, $allPlayers, 'away', $game);

        $homeFormation = Formation::tryFrom($match->home_formation) ?? Formation::F_4_3_3;
        $awayFormation = Formation::tryFrom($match->away_formation) ?? Formation::F_4_3_3;
        $homeMentality = Mentality::tryFrom($match->home_mentality ?? '') ?? Mentality::BALANCED;
        $awayMentality = Mentality::tryFrom($match->away_mentality ?? '') ?? Mentality::BALANCED;

        $homePlayingStyle = PlayingStyle::tryFrom($match->home_playing_style ?? '') ?? PlayingStyle::BALANCED;
        $awayPlayingStyle = PlayingStyle::tryFrom($match->away_playing_style ?? '') ?? PlayingStyle::BALANCED;
        $homePressing = PressingIntensity::tryFrom($match->home_pressing ?? '') ?? PressingIntensity::STANDARD;
        $awayPressing = PressingIntensity::tryFrom($match->away_pressing ?? '') ?? PressingIntensity::STANDARD;
        $homeDefLine = DefensiveLineHeight::tryFrom($match->home_defensive_line ?? '') ?? DefensiveLineHeight::NORMAL;
        $awayDefLine = DefensiveLineHeight::tryFrom($match->away_defensive_line ?? '') ?? DefensiveLineHeight::NORMAL;

        // Neutralize tactical instructions for AI-vs-AI matches — these only
        // add meaningful complexity for user-involved matches. Formation and
        // mentality still apply to differentiate AI teams.
        if (! $isUserMatch) {
            $homePlayingStyle = PlayingStyle::BALANCED;
            $awayPlayingStyle = PlayingStyle::BALANCED;
            $homePressing = PressingIntensity::STANDARD;
            $awayPressing = PressingIntensity::STANDARD;
            $homeDefLine = DefensiveLineHeight::NORMAL;
            $awayDefLine = DefensiveLineHeight::NORMAL;
        }

        $output = $this->matchSimulator->simulate(
            $match->homeTeam,
            $match->awayTeam,
            $homePlayers,
            $awayPlayers,
            $homeFormation,
            $awayFormation,
            $homeMentality,
            $awayMentality,
            $game,
            $homePlayingStyle,
            $awayPlayingStyle,
            $homePressing,
            $awayPressing,
            $homeDefLine,
            $awayDefLine,
            $homeBenchPlayers,
            $awayBenchPlayers,
            matchSeed: $match->id,
            neutralVenue: $match->competition_id === 'WC2026',
        );

        $result = $output->result;
        $performances = $output->performances;
        $mvpPlayerId = $this->calculateMvp(
            $result,
            $performances,
            $homePlayers,
            $awayPlayers,
            $match->home_team_id,
            $match->away_team_id,
        );

        return [
            'matchId' => $match->id,
            'homeTeamId' => $match->home_team_id,
            'awayTeamId' => $match->away_team_id,
            'homeScore' => $result->homeScore,
            'awayScore' => $result->awayScore,
            'homePossession' => $result->homePossession,
            'awayPossession' => $result->awayPossession,
            'competitionId' => $match->competition_id,
            'mvpPlayerId' => $mvpPlayerId,
            'events' => $result->events->map(fn (MatchEventData $e) => $e->toArray())->all(),
        ];
    }

    private function getBenchPlayers(GameMatch $match, $allPlayers, string $side, Game $game): Collection
    {
        $lineupField = $side . '_lineup';
        $teamIdField = $side . '_team_id';

        $lineupIds = $match->$lineupField ?? [];
        $teamPlayers = $allPlayers->get($match->$teamIdField, collect());

        return $teamPlayers
            ->reject(fn ($player) => in_array($player->id, $lineupIds))
            ->reject(fn ($player) => $player->isInjured($game->current_date))
            ->values();
    }

    private function getLineupPlayers(GameMatch $match, $allPlayers, string $side): Collection
    {
        $lineupField = $side . '_lineup';
        $teamIdField = $side . '_team_id';

        $lineupIds = $match->$lineupField ?? [];
        $teamPlayers = $allPlayers->get($match->$teamIdField, collect());

        if (empty($lineupIds)) {
            return collect();
        }

        return $teamPlayers->filter(fn ($p) => in_array($p->id, $lineupIds));
    }

    private function calculateMvp(
        MatchResult $result,
        array $performances,
        Collection $homePlayers,
        Collection $awayPlayers,
        string $homeTeamId,
        string $awayTeamId,
    ): ?string {
        if (empty($performances)) {
            return null;
        }

        // Build lookup maps for position group and team membership
        $positionGroups = [];
        $playerTeams = [];
        foreach ($homePlayers as $player) {
            $positionGroups[$player->id] = $player->position_group;
            $playerTeams[$player->id] = $homeTeamId;
        }
        foreach ($awayPlayers as $player) {
            $positionGroups[$player->id] = $player->position_group;
            $playerTeams[$player->id] = $awayTeamId;
        }

        $goalsConceded = [
            $homeTeamId => $result->awayScore,
            $awayTeamId => $result->homeScore,
        ];

        $winningTeamId = match (true) {
            $result->homeScore > $result->awayScore => $homeTeamId,
            $result->awayScore > $result->homeScore => $awayTeamId,
            default => null,
        };

        // Position-scaled event bonuses (rarer contributions score higher)
        $goalBonuses = ['Goalkeeper' => 0.50, 'Defender' => 0.35, 'Midfielder' => 0.25, 'Forward' => 0.20];
        $assistBonuses = ['Goalkeeper' => 0.25, 'Defender' => 0.15, 'Midfielder' => 0.15, 'Forward' => 0.15];

        // Count events per player
        $goals = [];
        $assists = [];
        $yellowCards = [];
        $redCards = [];

        foreach ($result->events as $event) {
            match ($event->type) {
                'goal' => $goals[$event->gamePlayerId] = ($goals[$event->gamePlayerId] ?? 0) + 1,
                'assist' => $assists[$event->gamePlayerId] = ($assists[$event->gamePlayerId] ?? 0) + 1,
                'yellow_card' => $yellowCards[$event->gamePlayerId] = ($yellowCards[$event->gamePlayerId] ?? 0) + 1,
                'red_card' => $redCards[$event->gamePlayerId] = ($redCards[$event->gamePlayerId] ?? 0) + 1,
                default => null,
            };
        }

        // Score each player
        $bestPlayerId = null;
        $bestScore = -INF;
        $bestIsWinner = false;

        foreach ($performances as $playerId => $performance) {
            $group = $positionGroups[$playerId] ?? 'Midfielder';
            $teamId = $playerTeams[$playerId] ?? null;
            $teamConceded = $teamId ? ($goalsConceded[$teamId] ?? 0) : 0;

            // Normalized performance: map 0.70-1.30 to 0.0-1.0
            $score = ($performance - 0.70) / 0.60;

            // Position-scaled goal/assist bonuses
            $score += ($goals[$playerId] ?? 0) * ($goalBonuses[$group] ?? 0.15);
            $score += ($assists[$playerId] ?? 0) * ($assistBonuses[$group] ?? 0.10);

            // Card penalties
            $score -= ($yellowCards[$playerId] ?? 0) * 0.10;
            $score -= ($redCards[$playerId] ?? 0) * 0.30;

            // Clean sheet bonus for goalkeepers and defenders
            if ($teamConceded === 0) {
                $score += match ($group) {
                    'Goalkeeper' => 0.20,
                    'Defender' => 0.15,
                    default => 0.0,
                };
            } elseif ($teamConceded === 1) {
                $score += match ($group) {
                    'Goalkeeper' => 0.05,
                    'Defender' => 0.05,
                    default => 0.0,
                };
            }

            // Goals conceded penalty for goalkeepers
            if ($group === 'Goalkeeper') {
                $score -= match (true) {
                    $teamConceded >= 4 => 0.20,
                    $teamConceded >= 3 => 0.10,
                    default => 0.0,
                };
            }

            // Winning team edge
            $isWinner = $winningTeamId !== null && $teamId === $winningTeamId;
            if ($isWinner) {
                $score += 0.08;
            }

            // Tiebreak: prefer the player from the winning team
            if ($score > $bestScore || ($score === $bestScore && $isWinner && ! $bestIsWinner)) {
                $bestScore = $score;
                $bestPlayerId = $playerId;
                $bestIsWinner = $isWinner;
            }
        }

        return $bestPlayerId;
    }
}
