<?php

namespace App\Modules\Match\Handlers;

use App\Modules\Competition\Contracts\CompetitionHandler;
use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Services\CupTieResolver;
use App\Modules\Squad\Services\EligibilityService;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class CupCompetitionHandler implements CompetitionHandler
{
    public function __construct(
        protected readonly CupTieResolver $tieResolver,
        protected readonly EligibilityService $eligibilityService,
    ) {}

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
     * Get match batch for hybrid competitions (league/group phase + knockout phase).
     * Knockout matches are batched by date; league/group matches by round_number.
     */
    protected function getHybridMatchBatch(string $gameId, GameMatch $nextMatch, bool $filterCupTieNull = false): Collection
    {
        return GameMatch::with(['homeTeam', 'awayTeam', 'cupTie'])
            ->where('game_id', $gameId)
            ->where('competition_id', $nextMatch->competition_id)
            ->where('played', false)
            ->where(function ($query) use ($nextMatch, $filterCupTieNull) {
                if ($nextMatch->cup_tie_id) {
                    $query->whereDate('scheduled_date', $nextMatch->scheduled_date->toDateString());
                    if ($filterCupTieNull) {
                        $query->whereNotNull('cup_tie_id');
                    }
                } else {
                    $query->where('round_number', $nextMatch->round_number);
                    if ($filterCupTieNull) {
                        $query->whereNull('cup_tie_id');
                    }
                }
            })
            ->get();
    }

    /**
     * Resolve all completed cup ties from the given matches.
     */
    protected function resolveCompletedTies(Game $game, Collection $matches, Collection $allPlayers): void
    {
        $tieIds = $matches->pluck('cup_tie_id')->unique()->filter();

        $ties = CupTie::with([
                'firstLegMatch.homeTeam', 'firstLegMatch.awayTeam',
                'secondLegMatch.homeTeam', 'secondLegMatch.awayTeam',
                'competition',
            ])
            ->whereIn('id', $tieIds)
            ->where('completed', false)
            ->get();

        foreach ($ties as $tie) {
            $winnerId = $this->tieResolver->resolve($tie, $allPlayers);

            if ($winnerId) {
                $match = $tie->secondLegMatch ?? $tie->firstLegMatch;
                CupTieResolved::dispatch($tie, $winnerId, $match, $game, $tie->competition);
            }
        }
    }

    /**
     * Create a cup tie with its match(es).
     */
    protected function createTie(
        Game $game,
        string $competitionId,
        string $homeTeamId,
        string $awayTeamId,
        PlayoffRoundConfig $config,
        ?int $bracketPosition = null,
    ): void {
        $tie = CupTie::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'competition_id' => $competitionId,
            'round_number' => $config->round,
            'bracket_position' => $bracketPosition,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
        ]);

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
     * Check if a round already exists for the given competition.
     */
    protected function roundExists(string $gameId, string $competitionId, int $round): bool
    {
        return CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('round_number', $round)
            ->exists();
    }

    /**
     * Get the current (highest) round number for the given competition.
     */
    protected function getCurrentRound(string $gameId, string $competitionId): int
    {
        return CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->max('round_number') ?? 0;
    }

    /**
     * Reset yellow cards if the just-completed round matches the reset threshold.
     */
    protected function maybeResetYellowCards(string $gameId, string $competitionId, string $handlerType): void
    {
        $rules = $this->eligibilityService->rulesForHandlerType($handlerType);
        if ($rules->yellowCardResetAfterRound === null) {
            return;
        }

        $resetRound = $rules->yellowCardResetAfterRound;
        $allComplete = CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('round_number', $resetRound)
            ->where('completed', false)
            ->doesntExist();

        $roundExists = CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('round_number', $resetRound)
            ->exists();

        if ($roundExists && $allComplete) {
            // Only reset once — check if a later round already has ties (reset already happened)
            $laterRoundExists = CupTie::where('game_id', $gameId)
                ->where('competition_id', $competitionId)
                ->where('round_number', '>', $resetRound)
                ->exists();

            if (!$laterRoundExists) {
                $this->eligibilityService->resetYellowCardsForCompetition($gameId, $competitionId);
            }
        }
    }
}
