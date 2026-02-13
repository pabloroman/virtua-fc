<?php

namespace App\Game;

use App\Game\Events\CupDrawConducted;
use App\Game\Events\CupTieCompleted;
use App\Game\Events\GameCreated;
use App\Game\Events\MatchdayAdvanced;
use App\Game\Events\MatchResultRecorded;
use App\Game\Events\NewSeasonStarted;
use App\Game\Events\SeasonDevelopmentProcessed;
use App\Game\Services\EligibilityService;
use App\Game\Services\LeagueFixtureGenerator;
use App\Game\Services\NotificationService;
use App\Game\Services\PlayerConditionService;
use App\Game\Services\PlayerDevelopmentService;
use App\Game\Services\SeasonGoalService;
use App\Game\Services\StandingsCalculator;
use App\Jobs\SetupNewGame;
use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\CupTie;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\PlayerSuspension;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class GameProjector extends Projector
{
    public function __construct(
        private readonly StandingsCalculator $standingsCalculator,
        private readonly EligibilityService $eligibilityService,
        private readonly PlayerDevelopmentService $developmentService,
        private readonly PlayerConditionService $conditionService,
        private readonly SeasonGoalService $seasonGoalService,
        private readonly NotificationService $notificationService,
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

    public function onMatchdayAdvanced(MatchdayAdvanced $event): void
    {
        Game::where('id', $event->aggregateRootUuid())
            ->update([
                'current_matchday' => $event->matchday,
                'current_date' => Carbon::parse($event->currentDate)->toDateString(),
            ]);
    }

    public function onMatchResultRecorded(MatchResultRecorded $event): void
    {
        $gameId = $event->aggregateRootUuid();
        $match = GameMatch::find($event->matchId);

        // Update match record
        $match->update([
            'home_score' => $event->homeScore,
            'away_score' => $event->awayScore,
            'played' => true,
        ]);

        // Serve suspensions for players who missed this match due to suspension
        // Must run BEFORE processMatchEvents so that new suspensions from this
        // match's cards aren't immediately served (they should apply from the next match)
        $this->serveSuspensions($gameId, $match, $event->competitionId);

        // Store match events and update player stats
        $this->processMatchEvents($gameId, $event->matchId, $event->events, $event->competitionId, $match->scheduled_date);

        // Update appearances for players in the lineup
        $this->updateAppearances($match);

        // Update fitness and morale for players
        $this->updatePlayerCondition($match, $event->events);

        // Update goalkeeper stats (goals conceded, clean sheets)
        $this->updateGoalkeeperStats($match, $event->homeScore, $event->awayScore);

        // Only update standings for league phase matches (not cups or knockout ties)
        $competition = Competition::find($event->competitionId);
        $isCupTie = $match?->cup_tie_id !== null;
        if ($competition?->isLeague() && !$isCupTie) {
            $this->standingsCalculator->updateAfterMatch(
                gameId: $gameId,
                competitionId: $event->competitionId,
                homeTeamId: $event->homeTeamId,
                awayTeamId: $event->awayTeamId,
                homeScore: $event->homeScore,
                awayScore: $event->awayScore,
            );
        }
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

    /**
     * Process match events: store them and update player stats.
     */
    private function processMatchEvents(string $gameId, string $matchId, array $events, string $competitionId, $matchDate): void
    {
        // Bulk insert all match events in a single query
        $now = now();
        $matchEventRows = array_map(fn ($eventData) => [
            'id' => Str::uuid()->toString(),
            'game_id' => $gameId,
            'game_match_id' => $matchId,
            'game_player_id' => $eventData['game_player_id'],
            'team_id' => $eventData['team_id'],
            'minute' => $eventData['minute'],
            'event_type' => $eventData['event_type'],
            'metadata' => isset($eventData['metadata']) ? json_encode($eventData['metadata']) : null,
            'created_at' => $now,
        ], $events);

        // Insert in chunks to respect SQLite variable limits
        foreach (array_chunk($matchEventRows, 50) as $chunk) {
            MatchEvent::insert($chunk);
        }

        // Aggregate stat increments per player to minimize queries
        $statIncrements = []; // [player_id => [goals => N, assists => N, ...]]
        $specialEvents = [];  // Events requiring individual processing (cards, injuries)

        foreach ($events as $eventData) {
            $playerId = $eventData['game_player_id'];
            $type = $eventData['event_type'];

            if (!isset($statIncrements[$playerId])) {
                $statIncrements[$playerId] = [];
            }

            switch ($type) {
                case 'goal':
                case 'own_goal':
                case 'assist':
                    $column = match ($type) {
                        'goal' => 'goals',
                        'own_goal' => 'own_goals',
                        'assist' => 'assists',
                    };
                    $statIncrements[$playerId][$column] = ($statIncrements[$playerId][$column] ?? 0) + 1;
                    break;

                case 'yellow_card':
                    $statIncrements[$playerId]['yellow_cards'] = ($statIncrements[$playerId]['yellow_cards'] ?? 0) + 1;
                    $specialEvents[] = $eventData;
                    break;

                case 'red_card':
                    $statIncrements[$playerId]['red_cards'] = ($statIncrements[$playerId]['red_cards'] ?? 0) + 1;
                    $specialEvents[] = $eventData;
                    break;

                case 'injury':
                    $specialEvents[] = $eventData;
                    break;
            }
        }

        // Batch-load all affected players in a single query
        $playerIds = array_keys($statIncrements);
        $specialPlayerIds = array_column($specialEvents, 'game_player_id');
        $allPlayerIds = array_unique(array_merge($playerIds, $specialPlayerIds));
        $players = GamePlayer::whereIn('id', $allPlayerIds)->get()->keyBy('id');

        // Apply stat increments per player (one save per player instead of one per event)
        foreach ($statIncrements as $playerId => $increments) {
            $player = $players->get($playerId);
            if (!$player) {
                continue;
            }

            foreach ($increments as $column => $amount) {
                $player->{$column} += $amount;
            }
            $player->save();
        }

        // Load game for notification creation (only for user's team players)
        $game = Game::find($gameId);

        // Process special events that need individual handling (cards, injuries)
        foreach ($specialEvents as $eventData) {
            $player = $players->get($eventData['game_player_id']);
            if (!$player) {
                continue;
            }

            // Check if player belongs to user's team (for notifications)
            $isUserTeamPlayer = $game && $player->team_id === $game->team_id;

            switch ($eventData['event_type']) {
                case 'yellow_card':
                    $suspension = $this->eligibilityService->checkYellowCardAccumulation($player->fresh());
                    if ($suspension) {
                        $this->eligibilityService->applySuspension($player, $suspension, $competitionId);

                        // Create notification for user's team player
                        if ($isUserTeamPlayer) {
                            $this->notificationService->notifySuspension(
                                $game,
                                $player,
                                $suspension,
                                __('notifications.reason_yellow_accumulation')
                            );
                        }
                    }
                    break;

                case 'red_card':
                    $isSecondYellow = $eventData['metadata']['second_yellow'] ?? false;
                    $this->eligibilityService->processRedCard($player, $isSecondYellow, $competitionId);

                    // Create notification for user's team player
                    if ($isUserTeamPlayer) {
                        $suspensionMatches = $isSecondYellow ? 1 : 1;
                        $this->notificationService->notifySuspension(
                            $game,
                            $player,
                            $suspensionMatches,
                            __('notifications.reason_red_card')
                        );
                    }
                    break;

                case 'injury':
                    $injuryType = $eventData['metadata']['injury_type'] ?? 'Unknown injury';
                    $weeksOut = $eventData['metadata']['weeks_out'] ?? 2;
                    $this->eligibilityService->applyInjury(
                        $player,
                        $injuryType,
                        $weeksOut,
                        Carbon::parse($matchDate)
                    );

                    // Create notification for user's team player
                    if ($isUserTeamPlayer) {
                        $this->notificationService->notifyInjury($game, $player, $injuryType, $weeksOut);
                    }
                    break;
            }
        }
    }

    /**
     * Update appearances for players in the match lineup.
     * Increments both regular appearances and season_appearances (for development tracking).
     */
    private function updateAppearances(GameMatch $match): void
    {
        // Get lineup player IDs from both teams
        $homeLineupIds = $match->home_lineup ?? [];
        $awayLineupIds = $match->away_lineup ?? [];
        $allLineupIds = array_merge($homeLineupIds, $awayLineupIds);

        if (empty($allLineupIds)) {
            return;
        }

        // Increment both appearances and season_appearances for lineup players
        GamePlayer::whereIn('id', $allLineupIds)
            ->increment('appearances');

        GamePlayer::whereIn('id', $allLineupIds)
            ->increment('season_appearances');
    }

    /**
     * Serve suspensions for players who missed this match due to suspension.
     * This decrements the matches_remaining for any suspended player on either team.
     */
    private function serveSuspensions(string $gameId, GameMatch $match, string $competitionId): void
    {
        // Query suspensions directly for players on either team in this competition
        $suspensions = PlayerSuspension::where('competition_id', $competitionId)
            ->where('matches_remaining', '>', 0)
            ->whereHas('gamePlayer', function ($query) use ($gameId, $match) {
                $query->where('game_id', $gameId)
                    ->whereIn('team_id', [$match->home_team_id, $match->away_team_id]);
            })
            ->get();

        foreach ($suspensions as $suspension) {
            $suspension->serveMatch();
        }
    }

    /**
     * Update fitness and morale for players after a match.
     */
    private function updatePlayerCondition(GameMatch $match, array $events): void
    {
        // Get previous match dates for each team to calculate recovery
        $homePreviousDate = $this->conditionService->getPreviousMatchDate(
            $match->game_id,
            $match->home_team_id,
            $match->id
        );

        $awayPreviousDate = $this->conditionService->getPreviousMatchDate(
            $match->game_id,
            $match->away_team_id,
            $match->id
        );

        // Use the more recent of the two for a combined update
        // (The service handles per-player calculations internally)
        $previousDate = null;
        if ($homePreviousDate && $awayPreviousDate) {
            $previousDate = $homePreviousDate->gt($awayPreviousDate) ? $homePreviousDate : $awayPreviousDate;
        } else {
            $previousDate = $homePreviousDate ?? $awayPreviousDate;
        }

        $this->conditionService->updateAfterMatch($match, $events, $previousDate);
    }

    /**
     * Update goalkeeper stats after a match (goals conceded and clean sheets).
     */
    private function updateGoalkeeperStats(GameMatch $match, int $homeScore, int $awayScore): void
    {
        // Find home goalkeeper in lineup
        $homeLineupIds = $match->home_lineup ?? [];
        if (!empty($homeLineupIds)) {
            $homeGoalkeeper = GamePlayer::whereIn('id', $homeLineupIds)
                ->where('position', 'Goalkeeper')
                ->first();

            if ($homeGoalkeeper) {
                // Home goalkeeper conceded away team's goals
                $homeGoalkeeper->increment('goals_conceded', $awayScore);

                if ($awayScore === 0) {
                    $homeGoalkeeper->increment('clean_sheets');
                }
            }
        }

        // Find away goalkeeper in lineup
        $awayLineupIds = $match->away_lineup ?? [];
        if (!empty($awayLineupIds)) {
            $awayGoalkeeper = GamePlayer::whereIn('id', $awayLineupIds)
                ->where('position', 'Goalkeeper')
                ->first();

            if ($awayGoalkeeper) {
                // Away goalkeeper conceded home team's goals
                $awayGoalkeeper->increment('goals_conceded', $homeScore);

                if ($homeScore === 0) {
                    $awayGoalkeeper->increment('clean_sheets');
                }
            }
        }
    }
}
