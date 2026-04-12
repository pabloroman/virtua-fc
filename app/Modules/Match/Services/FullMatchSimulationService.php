<?php

namespace App\Modules\Match\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\TeamReputation;
use App\Modules\Lineup\Services\LineupService;
use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\DTOs\TacticalConfig;
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
                    'performances' => [],
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

        $tc = TacticalConfig::fromMatch($match);
        if (! $isUserMatch) {
            $tc = $tc->neutralized();
        }

        $output = $this->matchSimulator->simulate(
            $match->homeTeam,
            $match->awayTeam,
            $homePlayers,
            $awayPlayers,
            $tc->homeFormation,
            $tc->awayFormation,
            $tc->homeMentality,
            $tc->awayMentality,
            $game,
            $tc->homePlayingStyle,
            $tc->awayPlayingStyle,
            $tc->homePressing,
            $tc->awayPressing,
            $tc->homeDefLine,
            $tc->awayDefLine,
            $homeBenchPlayers,
            $awayBenchPlayers,
            matchSeed: $match->id,
            neutralVenue: $match->isNeutralVenue(),
        );

        $result = $output->result;
        $performances = $output->performances;
        $mvpPlayerId = MvpCalculator::calculate(
            $performances,
            $homePlayers,
            $awayPlayers,
            $match->home_team_id,
            $match->away_team_id,
            $result->homeScore,
            $result->awayScore,
            $result->events,
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
            'performances' => $performances,
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

}
