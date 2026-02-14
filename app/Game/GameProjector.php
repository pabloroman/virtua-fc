<?php

namespace App\Game;

use App\Game\Events\CupDrawConducted;
use App\Game\Events\CupTieCompleted;
use App\Game\Events\GameCreated;
use App\Game\Events\NewSeasonStarted;
use App\Game\Events\SeasonDevelopmentProcessed;
use App\Game\Services\LeagueFixtureGenerator;
use App\Game\Services\PlayerDevelopmentService;
use App\Game\Services\SeasonGoalService;
use App\Jobs\SetupNewGame;
use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\CupTie;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use Carbon\Carbon;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class GameProjector extends Projector
{
    public function __construct(
        private readonly PlayerDevelopmentService $developmentService,
        private readonly SeasonGoalService $seasonGoalService,
    ) {}

    public function onGameCreated(GameCreated $event): void
    {
        $gameId = $event->aggregateRootUuid();
        $teamId = $event->teamId;

        // Find competition for the selected team (prefer tier 1 league)
        $competitionTeam = CompetitionTeam::where('team_id', $teamId)
            ->whereHas('competition', fn($q) => $q->where('role', Competition::ROLE_PRIMARY)->where('tier', 1))
            ->first()
            ?? CompetitionTeam::where('team_id', $teamId)->first();

        $competitionId = $competitionTeam?->competition_id ?? 'ESP1';
        $season = $competitionTeam?->season ?? '2024';

        // Load matchday calendar for initial current_date
        $matchdays = LeagueFixtureGenerator::loadMatchdays($competitionId, $season);
        $firstDate = Carbon::createFromFormat('d/m/y', $matchdays[0]['date']);

        // Determine initial season goal based on team reputation
        $team = Team::with('clubProfile')->find($teamId);
        $competition = Competition::find($competitionId);
        $seasonGoal = $this->seasonGoalService->determineGoalForTeam($team, $competition);

        // Create game record (setup not yet complete)
        Game::create([
            'id' => $gameId,
            'user_id' => $event->userId,
            'game_mode' => $event->gameMode,
            'player_name' => $event->playerName,
            'team_id' => $teamId,
            'competition_id' => $competitionId,
            'season' => $season,
            'current_date' => $firstDate->toDateString(),
            'current_matchday' => 0,
            'season_goal' => $seasonGoal,
            'setup_completed_at' => null,
        ]);

        // Dispatch heavy initialization to a queued job
        SetupNewGame::dispatch(
            gameId: $gameId,
            teamId: $teamId,
            competitionId: $competitionId,
            season: $season,
            gameMode: $event->gameMode,
        );
    }

    public function onCupDrawConducted(CupDrawConducted $event): void
    {
        // Cup draws are recorded via events for audit trail.
        // Cup status is now derived per-competition from CupTie data.
    }

    public function onCupTieCompleted(CupTieCompleted $event): void
    {
        $gameId = $event->aggregateRootUuid();
        $game = Game::find($gameId);

        // Award cup prize money if player's team won
        if ($event->winnerId === $game->team_id) {
            $this->awardCupPrizeMoney($game, $event->competitionId, $event->roundNumber);
        }
    }

    /**
     * Award prize money for advancing in a cup competition.
     */
    private function awardCupPrizeMoney(Game $game, string $competitionId, int $roundNumber): void
    {
        // Prize money increases with each round (in cents)
        // Round 1: €100K, Round 2: €200K, QF: €500K, SF: €1M, Final: €2M
        $prizeAmounts = [
            1 => 10_000_000,      // €100K - Round of 64/32
            2 => 20_000_000,      // €200K - Round of 32/16
            3 => 30_000_000,      // €300K - Round of 16
            4 => 50_000_000,      // €500K - Quarter-finals
            5 => 100_000_000,     // €1M - Semi-finals
            6 => 200_000_000,     // €2M - Final
        ];

        $amount = $prizeAmounts[$roundNumber] ?? $prizeAmounts[1];

        // Get competition name for description
        $competition = Competition::find($competitionId);
        $competitionName = $competition?->name ?? 'Cup';

        // Get round name from the tie
        $tie = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', $roundNumber)
            ->first();
        $roundName = $tie?->round_name ?? "Round $roundNumber";

        // Record the income transaction
        // Cup prizes are tracked via transactions and calculated at season end
        FinancialTransaction::recordIncome(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_CUP_BONUS,
            amount: $amount,
            description: "{$competitionName} - {$roundName} advancement",
            transactionDate: $game->current_date->toDateString(),
        );
    }

    public function onSeasonDevelopmentProcessed(SeasonDevelopmentProcessed $event): void
    {
        // Apply development changes to each player
        foreach ($event->playerChanges as $change) {
            $player = GamePlayer::find($change['playerId']);
            if (!$player) {
                continue;
            }

            $this->developmentService->applyDevelopment(
                $player,
                $change['techAfter'],
                $change['physAfter']
            );
        }
    }

    public function onNewSeasonStarted(NewSeasonStarted $event): void
    {
        // The SeasonEndPipeline processors have already updated the game state.
        // This event handler exists for:
        // 1. Audit trail - the event records the season transition
        // 2. Event replay - if events are replayed, this would need to restore state
        //
        // Note: For event replay to work fully, we would need to store more data
        // in the event and replay all processor actions here. For now, the pipeline
        // handles all mutations before the event is recorded.

        // Set current date to the first match of the new season
        $game = Game::find($event->aggregateRootUuid());
        if ($game) {
            $firstMatch = $game->getFirstCompetitiveMatch();
            if ($firstMatch) {
                $game->update(['current_date' => $firstMatch->scheduled_date]);
            }
        }
    }
}
