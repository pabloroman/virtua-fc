<?php

namespace App\Modules\Match\Handlers;

use App\Modules\Competition\Contracts\CompetitionHandler;
use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Modules\Match\Services\CupTieResolver;
use App\Modules\Competition\Services\SwissKnockoutGenerator;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Handler for UEFA-style Swiss format competitions (Champions League, Europa League, Conference League).
 *
 * League phase: 36 teams, 8 matchdays, single standings table.
 * Knockout phase: Playoff (9-24) → R16 (top 8 + playoff winners) → QF → SF → Final.
 */
class SwissFormatHandler implements CompetitionHandler
{
    public function __construct(
        private readonly CupTieResolver $tieResolver,
        private readonly SwissKnockoutGenerator $knockoutGenerator,
    ) {}

    public function getType(): string
    {
        return 'swiss_format';
    }

    public function getMatchBatch(string $gameId, GameMatch $nextMatch): Collection
    {
        return GameMatch::with(['homeTeam', 'awayTeam', 'cupTie'])
            ->where('game_id', $gameId)
            ->where('competition_id', $nextMatch->competition_id)
            ->where('played', false)
            ->where(function ($query) use ($nextMatch) {
                if ($nextMatch->cup_tie_id) {
                    // Knockout match - batch by date
                    $query->whereDate('scheduled_date', $nextMatch->scheduled_date->toDateString());
                } else {
                    // League phase match - batch by round_number (matchday)
                    $query->where('round_number', $nextMatch->round_number);
                }
            })
            ->get();
    }

    public function beforeMatches(Game $game, string $targetDate): void
    {
        // Check all swiss format competitions for pending knockout generation
        $competitions = Competition::where('handler_type', 'swiss_format')->get();

        foreach ($competitions as $competition) {
            $hasMatches = GameMatch::where('game_id', $game->id)
                ->where('competition_id', $competition->id)
                ->exists();

            if (!$hasMatches) {
                continue;
            }

            $this->maybeGenerateKnockoutRound($game, $competition->id);
        }
    }

    public function afterMatches(Game $game, Collection $matches, Collection $allPlayers): void
    {
        // Resolve any completed knockout ties
        $knockoutMatches = $matches->filter(fn ($m) => $m->cup_tie_id !== null);

        if ($knockoutMatches->isNotEmpty()) {
            $this->resolveKnockoutTies($game, $knockoutMatches, $allPlayers);
        }
    }

    public function getRedirectRoute(Game $game, Collection $matches, int $matchday): string
    {
        $firstMatch = $matches->first();

        return route('game.results', array_filter([
            'gameId' => $game->id,
            'competition' => $firstMatch->competition_id ?? $game->competition_id,
            'matchday' => $firstMatch->round_number ?? $matchday,
            'round' => $firstMatch?->round_name,
        ]));
    }

    /**
     * Check if the season is complete (league phase + all knockout rounds).
     */
    public function isSeasonComplete(Game $game, string $competitionId): bool
    {
        // Check if all league phase matches are played
        $unplayedLeague = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists();

        if ($unplayedLeague) {
            return false;
        }

        // Check if final has been completed
        $finalTie = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', SwissKnockoutGenerator::ROUND_FINAL)
            ->first();

        return $finalTie->completed ?? false;
    }

    /**
     * Check if league phase is complete and generate knockout rounds as needed.
     */
    private function maybeGenerateKnockoutRound(Game $game, string $competitionId): void
    {
        // Check if league phase is complete
        if (!$this->isLeaguePhaseComplete($game->id, $competitionId)) {
            return;
        }

        // Don't generate while a league-phase match is pending finalization —
        // its standings haven't been applied yet, so seedings would be wrong
        if ($game->hasPendingFinalizationForCompetition($competitionId)) {
            return;
        }

        $currentRound = $this->getCurrentKnockoutRound($game->id, $competitionId);
        $nextRound = $currentRound + 1;

        // Don't generate beyond the final
        if ($nextRound > SwissKnockoutGenerator::ROUND_FINAL) {
            return;
        }

        // For round 1 (knockout playoff), generate if it doesn't exist
        if ($nextRound === SwissKnockoutGenerator::ROUND_KNOCKOUT_PLAYOFF) {
            if (!$this->knockoutRoundExists($game->id, $competitionId, $nextRound)) {
                $this->generateKnockoutRound($game, $competitionId, $nextRound);
            }
            return;
        }

        // For later rounds, check if previous round is complete
        $previousRoundComplete = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', $currentRound)
            ->where('completed', false)
            ->doesntExist();

        if ($previousRoundComplete && !$this->knockoutRoundExists($game->id, $competitionId, $nextRound)) {
            $this->generateKnockoutRound($game, $competitionId, $nextRound);
        }
    }

    /**
     * Generate a knockout round's fixtures.
     */
    private function generateKnockoutRound(Game $game, string $competitionId, int $round): void
    {
        $config = $this->knockoutGenerator->getRoundConfig($round, $competitionId, $game->season);
        $matchups = $this->knockoutGenerator->generateMatchups($game, $competitionId, $round);

        foreach ($matchups as [$homeTeamId, $awayTeamId]) {
            $this->createKnockoutTie($game, $competitionId, $homeTeamId, $awayTeamId, $config);
        }
    }

    /**
     * Create a knockout tie with its match(es).
     */
    private function createKnockoutTie(
        Game $game,
        string $competitionId,
        string $homeTeamId,
        string $awayTeamId,
        PlayoffRoundConfig $config,
    ): void {
        $tie = CupTie::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'competition_id' => $competitionId,
            'round_number' => $config->round,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
        ]);

        // Create first leg match
        $firstLeg = GameMatch::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'competition_id' => $competitionId,
            'round_name' => $config->name,
            'round_number' => $config->round,
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
                'competition_id' => $competitionId,
                'round_name' => $config->name . '_return',
                'round_number' => $config->round,
                'home_team_id' => $awayTeamId,
                'away_team_id' => $homeTeamId,
                'scheduled_date' => $config->secondLegDate,
                'cup_tie_id' => $tie->id,
            ]);

            $tie->update(['second_leg_match_id' => $secondLeg->id]);
        }
    }

    /**
     * Resolve completed knockout ties.
     */
    private function resolveKnockoutTies(Game $game, Collection $knockoutMatches, Collection $allPlayers): void
    {
        $tieIds = $knockoutMatches->pluck('cup_tie_id')->unique()->filter();

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

    private function isLeaguePhaseComplete(string $gameId, string $competitionId): bool
    {
        return !GameMatch::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists();
    }

    private function getCurrentKnockoutRound(string $gameId, string $competitionId): int
    {
        return CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->max('round_number') ?? 0;
    }

    private function knockoutRoundExists(string $gameId, string $competitionId, int $round): bool
    {
        return CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('round_number', $round)
            ->exists();
    }
}
