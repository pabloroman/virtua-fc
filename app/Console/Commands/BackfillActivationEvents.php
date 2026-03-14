<?php

namespace App\Console\Commands;

use App\Models\ActivationEvent;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\SeasonArchive;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillActivationEvents extends Command
{
    protected $signature = 'app:backfill-activation-events';

    protected $description = 'Backfill activation events for existing users and games';

    public function handle(): int
    {
        $this->backfillRegistered();
        $this->backfillGameCreated();
        $this->backfillSetupCompleted();
        $this->backfillWelcomeCompleted();
        $this->backfillOnboardingCompleted();
        $this->backfillFirstMatchPlayed();
        $this->backfillMatchday5Reached();
        $this->backfillSeasonCompleted();
        $this->backfillTournamentCompleted();

        $this->info('Backfill complete.');

        return self::SUCCESS;
    }

    private function backfillRegistered(): void
    {
        $rows = User::all()->map(fn (User $user) => [
            'user_id' => $user->id,
            'game_id' => null,
            'game_mode' => null,
            'event' => ActivationEvent::EVENT_REGISTERED,
            'occurred_at' => $user->created_at,
        ])->toArray();

        $count = $this->insertIgnore($rows);
        $this->info("Registered: {$count} events inserted.");
    }

    private function backfillGameCreated(): void
    {
        $rows = Game::all()->map(fn (Game $game) => [
            'user_id' => $game->user_id,
            'game_id' => $game->id,
            'game_mode' => $game->game_mode,
            'event' => ActivationEvent::EVENT_GAME_CREATED,
            'occurred_at' => $game->created_at,
        ])->toArray();

        $count = $this->insertIgnore($rows);
        $this->info("Game created: {$count} events inserted.");
    }

    private function backfillSetupCompleted(): void
    {
        $rows = Game::whereNotNull('setup_completed_at')
            ->get()
            ->map(fn (Game $game) => [
                'user_id' => $game->user_id,
                'game_id' => $game->id,
                'game_mode' => $game->game_mode,
                'event' => ActivationEvent::EVENT_SETUP_COMPLETED,
                'occurred_at' => $game->setup_completed_at,
            ])->toArray();

        $count = $this->insertIgnore($rows);
        $this->info("Setup completed: {$count} events inserted.");
    }

    private function backfillWelcomeCompleted(): void
    {
        $rows = Game::where('needs_welcome', false)
            ->where('game_mode', Game::MODE_CAREER)
            ->whereNotNull('setup_completed_at')
            ->get()
            ->map(fn (Game $game) => [
                'user_id' => $game->user_id,
                'game_id' => $game->id,
                'game_mode' => Game::MODE_CAREER,
                'event' => ActivationEvent::EVENT_WELCOME_COMPLETED,
                'occurred_at' => $game->setup_completed_at->addMinute(),
            ])->toArray();

        $count = $this->insertIgnore($rows);
        $this->info("Welcome completed: {$count} events inserted.");
    }

    private function backfillOnboardingCompleted(): void
    {
        $rows = Game::where('needs_onboarding', false)
            ->where('game_mode', Game::MODE_CAREER)
            ->whereNotNull('setup_completed_at')
            ->get()
            ->map(fn (Game $game) => [
                'user_id' => $game->user_id,
                'game_id' => $game->id,
                'game_mode' => Game::MODE_CAREER,
                'event' => ActivationEvent::EVENT_ONBOARDING_COMPLETED,
                'occurred_at' => $game->setup_completed_at->addMinutes(2),
            ])->toArray();

        $count = $this->insertIgnore($rows);
        $this->info("Onboarding completed: {$count} events inserted.");
    }

    private function backfillFirstMatchPlayed(): void
    {
        $games = Game::all();
        $rows = [];

        foreach ($games as $game) {
            $firstMatch = GameMatch::where('game_id', $game->id)
                ->where('played', true)
                ->where(function ($q) use ($game) {
                    $q->where('home_team_id', $game->team_id)
                        ->orWhere('away_team_id', $game->team_id);
                })
                ->orderBy('scheduled_date')
                ->first();

            if ($firstMatch) {
                $rows[] = [
                    'user_id' => $game->user_id,
                    'game_id' => $game->id,
                    'game_mode' => $game->game_mode,
                    'event' => ActivationEvent::EVENT_FIRST_MATCH_PLAYED,
                    'occurred_at' => $firstMatch->scheduled_date ?? $game->setup_completed_at?->addMinutes(5),
                ];
            }
        }

        $count = $this->insertIgnore($rows);
        $this->info("First match played: {$count} events inserted.");
    }

    private function backfillMatchday5Reached(): void
    {
        $rows = Game::where('current_matchday', '>=', 5)
            ->get()
            ->map(fn (Game $game) => [
                'user_id' => $game->user_id,
                'game_id' => $game->id,
                'game_mode' => $game->game_mode,
                'event' => ActivationEvent::EVENT_MATCHDAY_5_REACHED,
                'occurred_at' => $game->setup_completed_at?->addMinutes(10) ?? $game->created_at,
            ])->toArray();

        $count = $this->insertIgnore($rows);
        $this->info("Matchday 5 reached: {$count} events inserted.");
    }

    private function backfillSeasonCompleted(): void
    {
        $gameIds = SeasonArchive::distinct()->pluck('game_id');
        $games = Game::whereIn('id', $gameIds)
            ->where('game_mode', Game::MODE_CAREER)
            ->get();

        $rows = $games->map(fn (Game $game) => [
            'user_id' => $game->user_id,
            'game_id' => $game->id,
            'game_mode' => Game::MODE_CAREER,
            'event' => ActivationEvent::EVENT_SEASON_COMPLETED,
            'occurred_at' => $game->setup_completed_at?->addMinutes(30) ?? $game->created_at,
        ])->toArray();

        $count = $this->insertIgnore($rows);
        $this->info("Season completed: {$count} events inserted.");
    }

    private function backfillTournamentCompleted(): void
    {
        // Tournament games with no unplayed matches are completed
        $games = Game::where('game_mode', Game::MODE_TOURNAMENT)
            ->whereNotNull('setup_completed_at')
            ->get()
            ->filter(function (Game $game) {
                return ! $game->matches()->where('played', false)->exists();
            });

        $rows = $games->map(fn (Game $game) => [
            'user_id' => $game->user_id,
            'game_id' => $game->id,
            'game_mode' => Game::MODE_TOURNAMENT,
            'event' => ActivationEvent::EVENT_TOURNAMENT_COMPLETED,
            'occurred_at' => $game->setup_completed_at?->addMinutes(30) ?? $game->created_at,
        ])->toArray();

        $count = $this->insertIgnore($rows);
        $this->info("Tournament completed: {$count} events inserted.");
    }

    private function insertIgnore(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $count = 0;
        foreach (array_chunk($rows, 100) as $chunk) {
            $count += ActivationEvent::insertOrIgnore($chunk);
        }

        return $count;
    }
}
