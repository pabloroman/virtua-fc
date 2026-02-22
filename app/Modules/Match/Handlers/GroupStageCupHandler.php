<?php

namespace App\Modules\Match\Handlers;

use App\Modules\Competition\Contracts\CompetitionHandler;
use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Modules\Competition\Services\WorldCupKnockoutGenerator;
use App\Modules\Match\Services\CupTieResolver;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Handler for group stage + knockout competitions (World Cup).
 *
 * Group phase: teams play round-robin within groups (league-style, batched by round_number).
 * Knockout phase: single-leg ties with extra time & penalties, generated progressively.
 */
class GroupStageCupHandler implements CompetitionHandler
{
    public function __construct(
        private readonly CupTieResolver $tieResolver,
        private readonly WorldCupKnockoutGenerator $knockoutGenerator,
    ) {}

    public function getType(): string
    {
        return 'group_stage_cup';
    }

    public function getMatchBatch(string $gameId, GameMatch $nextMatch): Collection
    {
        return GameMatch::with(['homeTeam', 'awayTeam', 'cupTie'])
            ->where('game_id', $gameId)
            ->where('competition_id', $nextMatch->competition_id)
            ->where('played', false)
            ->where(function ($query) use ($nextMatch) {
                if ($nextMatch->cup_tie_id) {
                    // Knockout match — batch by date
                    $query->whereDate('scheduled_date', $nextMatch->scheduled_date->toDateString())
                        ->whereNotNull('cup_tie_id');
                } else {
                    // Group stage match — batch by round_number (matchday)
                    $query->where('round_number', $nextMatch->round_number)
                        ->whereNull('cup_tie_id');
                }
            })
            ->get();
    }

    public function beforeMatches(Game $game, string $targetDate): void
    {
        $competitions = Competition::where('handler_type', 'group_stage_cup')->get();

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
     * Check if group stage is complete and generate knockout rounds as needed.
     */
    private function maybeGenerateKnockoutRound(Game $game, string $competitionId): void
    {
        if (!$this->isGroupStageComplete($game->id, $competitionId)) {
            return;
        }

        // Don't generate while a group-stage match is pending finalization —
        // its standings haven't been applied yet, so seedings would be wrong
        if ($game->hasPendingFinalizationForCompetition($competitionId)) {
            return;
        }

        $currentRound = $this->getCurrentKnockoutRound($game->id, $competitionId);
        $finalRound = $this->knockoutGenerator->getFinalRound($competitionId);

        if ($currentRound === 0) {
            // No knockout rounds yet — generate the first one
            $qualifiedCount = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->whereNotNull('group_label')
                ->where('position', '<=', 2)
                ->count();

            $firstRound = $this->knockoutGenerator->getFirstKnockoutRound($qualifiedCount);

            if (!$this->knockoutRoundExists($game->id, $competitionId, $firstRound)) {
                $this->generateKnockoutRound($game, $competitionId, $firstRound);
            }

            return;
        }

        // Check if current round is complete
        $currentRoundComplete = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', $currentRound)
            ->where('completed', false)
            ->doesntExist();

        if (!$currentRoundComplete) {
            return;
        }

        $nextRound = $currentRound + 1;

        if ($nextRound > $finalRound) {
            return;
        }

        if (!$this->knockoutRoundExists($game->id, $competitionId, $nextRound)) {
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
     * Create a knockout tie with its single-leg match.
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

        $match = GameMatch::create([
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

        $tie->update(['first_leg_match_id' => $match->id]);
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

    private function isGroupStageComplete(string $gameId, string $competitionId): bool
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
