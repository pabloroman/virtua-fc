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
        private readonly AIMatchResolver $aiMatchResolver = new AIMatchResolver,
    ) {}

    /**
     * Resolve matches for a batch that may involve the player's team.
     *
     * Handles lineup generation, forfeit checks, and full match simulation.
     *
     * When $fastForward is true the user's team is treated like any AI-
     * controlled team for in-match decisions (AISubstitutionService also
     * runs their subs). Tactics, formation and lineup still come from the
     * user's saved defaults via LineupService::ensureLineupsForMatches.
     *
     * @param  Collection<GameMatch>  $matches
     * @param  Game  $game
     * @param  Collection  $allPlayers  Players grouped by team_id
     * @param  array<string, array<string>>  $suspendedByCompetition
     * @return array{matchResults: array, playerMatch: ?GameMatch}
     */
    public function resolveMatches(Collection $matches, Game $game, $allPlayers, array $suspendedByCompetition, bool $fastForward = false): array
    {
        $teamIds = $matches->pluck('home_team_id')
            ->merge($matches->pluck('away_team_id'))
            ->push($game->team_id)
            ->unique()
            ->values();

        $clubProfiles = TeamReputation::where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)->get()->keyBy('team_id');

        $playerMatch = $matches->first(fn ($m) => $m->involvesTeam($game->team_id));
        // Only the user's match pays the full minute-by-minute MatchSimulator
        // cost; sibling AI matches in the same batch go through the fast
        // statistical AIMatchResolver (still emits goal/card events so the
        // live-match "other scores" ticker has real data).
        $useSplit = $playerMatch && config('match_simulation.ai_resolver_enabled', false);

        $lineupMatches = $useSplit ? collect([$playerMatch]) : $matches;
        $this->lineupService->ensureLineupsForMatches($lineupMatches, $game, $allPlayers, $suspendedByCompetition, $clubProfiles);

        // --- Check for forfeit (user's team has < 7 available players) ---
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
        if ($useSplit) {
            $userMatchToSimulate = $matchesToSimulate->first(fn ($m) => $m->id === $playerMatch->id);
            $siblingMatches = $matchesToSimulate->reject(fn ($m) => $m->id === $playerMatch->id);

            if ($userMatchToSimulate) {
                $suspendedForCompetition = $suspendedByCompetition[$userMatchToSimulate->competition_id] ?? [];
                $matchResults[] = $this->simulateMatch($userMatchToSimulate, $allPlayers, $game, $fastForward, $suspendedForCompetition);
            }

            if ($siblingMatches->isNotEmpty()) {
                $matchResults = array_merge(
                    $matchResults,
                    $this->aiMatchResolver->resolveMatches($siblingMatches, $allPlayers, $game, $suspendedByCompetition)
                );
            }
        } else {
            foreach ($matchesToSimulate as $match) {
                $suspendedForCompetition = $suspendedByCompetition[$match->competition_id] ?? [];
                $matchResults[] = $this->simulateMatch($match, $allPlayers, $game, $fastForward, $suspendedForCompetition);
            }
        }

        if ($forfeitResult) {
            $matchResults[] = $forfeitResult;
            // Forfeited match is not a live match — process all effects immediately
            $playerMatch = null;
        }

        return ['matchResults' => $matchResults, 'playerMatch' => $playerMatch];
    }

    /**
     * @param  array<int, string>  $suspendedPlayerIds  Players suspended for this match's competition
     */
    private function simulateMatch(GameMatch $match, $allPlayers, Game $game, bool $fastForward = false, array $suspendedPlayerIds = []): array
    {
        $homePlayers = $this->getLineupPlayers($match, $allPlayers, 'home');
        $awayPlayers = $this->getLineupPlayers($match, $allPlayers, 'away');

        // Always pass both benches so injury auto-subs fire for the user's
        // team too. The user team is excluded from tactical AI substitution
        // windows (those only trigger via "Skip to end"); see
        // simulateWithAISubstitutions() in MatchSimulator.
        $isUserMatch = $match->involvesTeam($game->team_id);

        $homeBenchPlayers = $this->getBenchPlayers($match, $allPlayers, 'home', $game, $suspendedPlayerIds);
        $awayBenchPlayers = $this->getBenchPlayers($match, $allPlayers, 'away', $game, $suspendedPlayerIds);

        $tc = TacticalConfig::fromMatch($match);
        if (! $isUserMatch) {
            $tc = $tc->neutralized();
        }

        $homePlayerSlots = $match->playerSlotMap('home');
        $awayPlayerSlots = $match->playerSlotMap('away');

        // In fast mode the assistant coach also makes the user's in-match
        // substitutions — pass userTeamId = null so the AI sub helper covers
        // both teams, same as if the user had clicked "Skip to end" from
        // minute 0. Tactics stay intact above.
        $simulatorUserTeamId = ($isUserMatch && ! $fastForward) ? $game->team_id : null;

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
            userTeamId: $simulatorUserTeamId,
            homePlayerSlots: $homePlayerSlots,
            awayPlayerSlots: $awayPlayerSlots,
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

    /**
     * Build the bench for one side of a match.
     *
     * Excludes lineup players, injured players, and players suspended for the
     * match's competition. The suspension filter matters because injury subs
     * and AI tactical subs draw from this collection — letting a suspended
     * player enter the match would both circumvent the ban and, on
     * finalization, cause their own suspension not to be served (the
     * serve-suspensions query excludes any player who received a card in the
     * match).
     *
     * @param  array<int, string>  $suspendedPlayerIds
     */
    private function getBenchPlayers(GameMatch $match, $allPlayers, string $side, Game $game, array $suspendedPlayerIds = []): Collection
    {
        $lineupField = $side . '_lineup';
        $teamIdField = $side . '_team_id';

        $lineupIds = $match->$lineupField ?? [];
        $teamPlayers = $allPlayers->get($match->$teamIdField, collect());

        return $teamPlayers
            ->reject(fn ($player) => in_array($player->id, $lineupIds))
            ->reject(fn ($player) => $player->isInjured($game->current_date))
            ->reject(fn ($player) => in_array($player->id, $suspendedPlayerIds))
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
