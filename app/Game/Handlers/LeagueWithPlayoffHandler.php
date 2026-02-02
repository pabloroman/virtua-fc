<?php

namespace App\Game\Handlers;

use App\Game\Contracts\CompetitionHandler;
use App\Game\Contracts\PlayoffGenerator;
use App\Game\DTO\PlayoffRoundConfig;
use App\Game\Playoffs\PlayoffGeneratorFactory;
use App\Game\Services\CupTieResolver;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Handler for league competitions that have end-of-season playoffs.
 *
 * Combines regular league handling with knockout playoff generation
 * and resolution after the regular season ends.
 */
class LeagueWithPlayoffHandler implements CompetitionHandler
{
    public function __construct(
        private PlayoffGeneratorFactory $playoffFactory,
        private CupTieResolver $tieResolver,
    ) {}

    public function getType(): string
    {
        return 'league_with_playoff';
    }

    public function getMatchBatch(string $gameId, GameMatch $nextMatch): Collection
    {
        // Return all matches for the same matchday (league) or date (playoffs)
        return GameMatch::with(['homeTeam', 'awayTeam', 'cupTie'])
            ->where('game_id', $gameId)
            ->where('competition_id', $nextMatch->competition_id)
            ->where('played', false)
            ->where(function ($query) use ($nextMatch) {
                if ($nextMatch->cup_tie_id) {
                    // Playoff match - get all matches on same date
                    $query->whereDate('scheduled_date', $nextMatch->scheduled_date->toDateString());
                } else {
                    // League match - get all matches in same matchday
                    $query->where('matchday', $nextMatch->matchday);
                }
            })
            ->get();
    }

    public function beforeMatches(Game $game, string $targetDate): void
    {
        $generator = $this->playoffFactory->forCompetition($game->competition_id);
        if (!$generator) {
            return;
        }

        // Check if we should generate the next playoff round
        if ($this->shouldGeneratePlayoffRound($game, $generator)) {
            $nextRound = $this->getCurrentPlayoffRound($game, $generator) + 1;
            $this->generatePlayoffRound($game, $generator, $nextRound);
        }
    }

    public function afterMatches(Game $game, Collection $matches, Collection $allPlayers): void
    {
        // Resolve any completed playoff ties
        $playoffMatches = $matches->filter(fn ($m) => $m->cup_tie_id !== null);

        if ($playoffMatches->isNotEmpty()) {
            $this->resolvePlayoffTies($game, $playoffMatches, $allPlayers);
        }
    }

    public function getRedirectRoute(Game $game, Collection $matches, int $matchday): string
    {
        return route('game.results', [
            'gameId' => $game->id,
            'matchday' => $matchday,
        ]);
    }

    /**
     * Check if the season is complete (including playoffs if applicable).
     */
    public function isSeasonComplete(Game $game): bool
    {
        // First check if all league matches are played
        $unplayedLeague = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists();

        if ($unplayedLeague) {
            return false;
        }

        // Then check playoff completion
        $generator = $this->playoffFactory->forCompetition($game->competition_id);
        if (!$generator) {
            return true; // No playoffs configured
        }

        return $generator->isComplete($game);
    }

    /**
     * Determine if we should generate the next playoff round.
     */
    private function shouldGeneratePlayoffRound(Game $game, PlayoffGenerator $generator): bool
    {
        // Check if regular season is complete
        $regularSeasonComplete = !GameMatch::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists();

        if (!$regularSeasonComplete) {
            return false;
        }

        $currentRound = $this->getCurrentPlayoffRound($game, $generator);
        $nextRound = $currentRound + 1;

        // Check if we've already generated all rounds
        if ($nextRound > $generator->getTotalRounds()) {
            return false;
        }

        // For round 1, generate if it doesn't exist
        if ($nextRound === 1) {
            return !$this->playoffRoundExists($game, $generator, 1);
        }

        // For later rounds, check if previous round is complete
        $previousRoundComplete = CupTie::where('game_id', $game->id)
            ->where('competition_id', $generator->getCompetitionId())
            ->where('round_number', $currentRound)
            ->where('completed', false)
            ->doesntExist();

        return $previousRoundComplete && !$this->playoffRoundExists($game, $generator, $nextRound);
    }

    /**
     * Generate fixtures for a playoff round.
     */
    private function generatePlayoffRound(Game $game, PlayoffGenerator $generator, int $round): void
    {
        // Season year is the year the season started (playoffs happen the following year)
        $seasonYear = $game->current_date->year - 1;
        $config = $generator->getRoundConfig($round, $seasonYear);
        $matchups = $generator->generateMatchups($game, $round);

        foreach ($matchups as [$homeTeamId, $awayTeamId]) {
            $this->createPlayoffTie($game, $generator, $homeTeamId, $awayTeamId, $config);
        }
    }

    /**
     * Create a playoff tie with its matches.
     */
    private function createPlayoffTie(
        Game $game,
        PlayoffGenerator $generator,
        string $homeTeamId,
        string $awayTeamId,
        PlayoffRoundConfig $config,
    ): void {
        $tie = CupTie::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'competition_id' => $generator->getCompetitionId(),
            'round_number' => $config->round,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
        ]);

        // Create first leg match
        $firstLeg = GameMatch::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'competition_id' => $generator->getCompetitionId(),
            'round_name' => $config->name,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'scheduled_date' => $config->firstLegDate,
            'cup_tie_id' => $tie->id,
        ]);

        $tie->update(['first_leg_match_id' => $firstLeg->id]);

        // Create second leg if two-legged
        if ($config->twoLegged && $config->secondLegDate) {
            $secondLeg = GameMatch::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'competition_id' => $generator->getCompetitionId(),
                'round_name' => $config->name . ' (Vuelta)',
                'home_team_id' => $awayTeamId, // Teams swap for second leg
                'away_team_id' => $homeTeamId,
                'scheduled_date' => $config->secondLegDate,
                'cup_tie_id' => $tie->id,
            ]);

            $tie->update(['second_leg_match_id' => $secondLeg->id]);
        }
    }

    /**
     * Resolve completed playoff ties.
     */
    private function resolvePlayoffTies(Game $game, Collection $playoffMatches, Collection $allPlayers): void
    {
        $tieIds = $playoffMatches->pluck('cup_tie_id')->unique()->filter();

        foreach ($tieIds as $tieId) {
            $tie = CupTie::with(['firstLegMatch', 'secondLegMatch'])->find($tieId);

            if (!$tie || $tie->completed) {
                continue;
            }

            $winnerId = $this->tieResolver->resolve($tie, $allPlayers);

            if ($winnerId) {
                $tie->update([
                    'winner_id' => $winnerId,
                    'completed' => true,
                    'resolution' => $tie->fresh()->resolution ?? [],
                ]);
            }
        }
    }

    /**
     * Get the current (highest) playoff round that has been generated.
     */
    private function getCurrentPlayoffRound(Game $game, PlayoffGenerator $generator): int
    {
        return CupTie::where('game_id', $game->id)
            ->where('competition_id', $generator->getCompetitionId())
            ->max('round_number') ?? 0;
    }

    /**
     * Check if a playoff round already exists.
     */
    private function playoffRoundExists(Game $game, PlayoffGenerator $generator, int $round): bool
    {
        return CupTie::where('game_id', $game->id)
            ->where('competition_id', $generator->getCompetitionId())
            ->where('round_number', $round)
            ->exists();
    }
}
