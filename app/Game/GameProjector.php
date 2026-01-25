<?php

namespace App\Game;

use App\Game\Events\CupDrawConducted;
use App\Game\Events\CupTieCompleted;
use App\Game\Events\GameCreated;
use App\Game\Events\MatchdayAdvanced;
use App\Game\Events\MatchResultRecorded;
use App\Game\Services\EligibilityService;
use App\Game\Services\StandingsCalculator;
use App\Models\CompetitionTeam;
use App\Models\CupTie;
use App\Models\FixtureTemplate;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class GameProjector extends Projector
{
    public function __construct(
        private readonly StandingsCalculator $standingsCalculator,
        private readonly EligibilityService $eligibilityService,
    ) {}

    public function onGameCreated(GameCreated $event): void
    {
        $gameId = $event->aggregateRootUuid();
        $teamId = $event->teamId;

        // Find competition for the selected team
        $competitionTeam = CompetitionTeam::where('team_id', $teamId)->first();
        $competitionId = $competitionTeam?->competition_id ?? 'ESP1';
        $season = $competitionTeam?->season ?? '2024';

        // Get first fixture date for initial current_date
        $firstFixture = FixtureTemplate::where('competition_id', $competitionId)
            ->where('season', $season)
            ->orderBy('scheduled_date')
            ->first();

        // Create game record
        Game::create([
            'id' => $gameId,
            'user_id' => $event->userId,
            'player_name' => $event->playerName,
            'team_id' => $teamId,
            'season' => $season,
            'current_date' => $firstFixture?->scheduled_date?->toDateString(),
            'current_matchday' => 0,
        ]);

        // Copy fixture templates to game matches
        $this->copyFixturesToGame($gameId, $competitionId, $season);

        // Initialize standings for all teams
        $this->initializeStandings($gameId, $competitionId, $season);

        // Initialize game players for all teams in the competition
        $this->initializeGamePlayers($gameId, $competitionId, $season);
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
            'played_at' => now(),
        ]);

        // Store match events and update player stats
        $this->processMatchEvents($gameId, $event->matchId, $event->events, $event->matchday, $match->scheduled_date);

        // Update appearances for all players on both teams
        $this->updateAppearances($gameId, $event->homeTeamId, $event->awayTeamId);

        // Only update standings for league competitions (not cups)
        $competition = \App\Models\Competition::find($event->competitionId);
        if ($competition?->isLeague()) {
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
        $gameId = $event->aggregateRootUuid();

        // Update game's cup round
        Game::where('id', $gameId)
            ->update(['cup_round' => $event->roundNumber]);
    }

    public function onCupTieCompleted(CupTieCompleted $event): void
    {
        $gameId = $event->aggregateRootUuid();

        // Check if the player's team was eliminated
        $game = Game::find($gameId);
        if ($event->loserId === $game->team_id) {
            $game->update(['cup_eliminated' => true]);
        }

        // The tie is already updated by CupTieResolver, this event
        // is mainly for audit trail and potential future reactors
    }

    /**
     * Process match events: store them and update player stats.
     */
    private function processMatchEvents(string $gameId, string $matchId, array $events, int $matchday, $matchDate): void
    {
        foreach ($events as $eventData) {
            // Store the event
            MatchEvent::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $gameId,
                'game_match_id' => $matchId,
                'game_player_id' => $eventData['game_player_id'],
                'team_id' => $eventData['team_id'],
                'minute' => $eventData['minute'],
                'event_type' => $eventData['event_type'],
                'metadata' => $eventData['metadata'] ?? null,
            ]);

            // Update player stats based on event type
            $player = GamePlayer::find($eventData['game_player_id']);
            if (!$player) {
                continue;
            }

            switch ($eventData['event_type']) {
                case 'goal':
                    $player->increment('goals');
                    break;

                case 'own_goal':
                    $player->increment('own_goals');
                    break;

                case 'assist':
                    $player->increment('assists');
                    break;

                case 'yellow_card':
                    $player->increment('yellow_cards');
                    // Check for yellow card accumulation suspension
                    $suspension = $this->eligibilityService->checkYellowCardAccumulation($player->fresh());
                    if ($suspension) {
                        $this->eligibilityService->applySuspension($player, $suspension, $matchday);
                    }
                    break;

                case 'red_card':
                    $player->increment('red_cards');
                    $isSecondYellow = $eventData['metadata']['second_yellow'] ?? false;
                    $this->eligibilityService->processRedCard($player, $isSecondYellow, $matchday);
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
                    break;
            }
        }
    }

    /**
     * Update appearances for all players on both teams.
     * In a real system, this would only be for players in the starting lineup/subs used.
     * For simplicity, we increment appearances for all squad players.
     */
    private function updateAppearances(string $gameId, string $homeTeamId, string $awayTeamId): void
    {
        // For now, we just mark appearances for players who participated in events
        // In a more complete system, we'd track the actual lineup
        // This is a placeholder - appearances will be incremented when we add lineup selection
    }

    /**
     * Copy fixture templates to game-specific matches.
     */
    private function copyFixturesToGame(string $gameId, string $competitionId, string $season): void
    {
        $fixtures = FixtureTemplate::where('competition_id', $competitionId)
            ->where('season', $season)
            ->get();

        foreach ($fixtures as $fixture) {
            GameMatch::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $gameId,
                'competition_id' => $competitionId,
                'round_number' => $fixture->round_number,
                'round_name' => "Matchday {$fixture->round_number}",
                'home_team_id' => $fixture->home_team_id,
                'away_team_id' => $fixture->away_team_id,
                'scheduled_date' => $fixture->scheduled_date,
                'home_score' => null,
                'away_score' => null,
                'played' => false,
            ]);
        }
    }

    /**
     * Initialize standings for all teams in the competition.
     */
    private function initializeStandings(string $gameId, string $competitionId, string $season): void
    {
        $teamIds = CompetitionTeam::where('competition_id', $competitionId)
            ->where('season', $season)
            ->pluck('team_id')
            ->toArray();

        $this->standingsCalculator->initializeStandings($gameId, $competitionId, $teamIds);
    }

    /**
     * Initialize game players for all teams in the competition.
     * Reads career data (position, market value, contract) from JSON files.
     */
    private function initializeGamePlayers(string $gameId, string $competitionId, string $season): void
    {
        // Get competition data path
        $basePath = base_path("data/{$competitionId}/{$season}");
        $playersPath = "{$basePath}/players";

        if (!is_dir($playersPath)) {
            return;
        }

        // Get all teams in this competition
        $teams = Team::whereHas('competitions', function ($query) use ($competitionId, $season) {
            $query->where('competition_id', $competitionId)
                ->where('competition_teams.season', $season);
        })->get();

        foreach ($teams as $team) {
            $this->initializeTeamPlayers($gameId, $team, $playersPath);
        }
    }

    /**
     * Initialize game players for a specific team from JSON data.
     */
    private function initializeTeamPlayers(string $gameId, Team $team, string $playersPath): void
    {
        if (!$team->transfermarkt_id) {
            return;
        }

        $playerFile = "{$playersPath}/{$team->transfermarkt_id}.json";
        if (!file_exists($playerFile)) {
            return;
        }

        $data = json_decode(file_get_contents($playerFile), true);
        $playersData = $data['players'] ?? [];

        foreach ($playersData as $playerData) {
            // Find the reference player by transfermarkt_id
            $player = Player::where('transfermarkt_id', $playerData['id'])->first();
            if (!$player) {
                continue;
            }

            // Parse contract date
            $contractUntil = null;
            if (!empty($playerData['contract'])) {
                try {
                    $contractUntil = Carbon::parse($playerData['contract'])->toDateString();
                } catch (\Exception $e) {
                    // Ignore invalid dates
                }
            }

            // Parse joined date
            $joinedOn = null;
            if (!empty($playerData['joinedOn'])) {
                try {
                    $joinedOn = Carbon::parse($playerData['joinedOn'])->toDateString();
                } catch (\Exception $e) {
                    // Ignore invalid dates
                }
            }

            // Parse market value to cents
            $marketValueCents = $this->parseMarketValue($playerData['marketValue'] ?? null);

            // Create game player with career data snapshot
            GamePlayer::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $gameId,
                'player_id' => $player->id,
                'team_id' => $team->id,
                'position' => $playerData['position'] ?? 'Unknown',
                'market_value' => $playerData['marketValue'] ?? null,
                'market_value_cents' => $marketValueCents,
                'contract_until' => $contractUntil,
                'signed_from' => $playerData['signedFrom'] ?? null,
                'joined_on' => $joinedOn,
                'fitness' => rand(90, 100),
                'morale' => rand(65, 80),
            ]);
        }
    }

    /**
     * Parse market value string to cents (e.g., "€28.00m" -> 2800000000).
     */
    private function parseMarketValue(?string $value): int
    {
        if (!$value) {
            return 0;
        }

        // Remove currency symbol and whitespace
        $value = preg_replace('/[€$£\s]/', '', $value);

        // Extract number and multiplier
        if (preg_match('/^([\d.]+)(m|k)?$/i', $value, $matches)) {
            $number = (float) $matches[1];
            $multiplier = strtolower($matches[2] ?? '');

            // Convert to cents (base unit)
            $amount = match ($multiplier) {
                'm' => $number * 1_000_000,
                'k' => $number * 1_000,
                default => $number,
            };

            // Convert euros to cents
            return (int) ($amount * 100);
        }

        return 0;
    }
}
