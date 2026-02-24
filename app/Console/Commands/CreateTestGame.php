<?php

namespace App\Console\Commands;

use App\Modules\Season\Services\GameCreationService;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;

class CreateTestGame extends Command
{
    protected $signature = 'app:create-test-game
                            {--user= : User ID or email to create the game for (defaults to first user)}
                            {--team= : Team name to manage (defaults to Real Madrid)}
                            {--seed : Re-seed test data before creating the game}';

    protected $description = 'Create a test game with the test profile (4-team league, 8-team cup) for quick testing';

    public function handle(GameCreationService $gameCreationService): int
    {
        // Optionally re-seed test data
        if ($this->option('seed')) {
            $this->info('Seeding test reference data...');
            $this->call('app:seed-reference-data', [
                '--fresh' => true,
                '--profile' => 'test',
            ]);
            $this->newLine();
        }

        // Find user
        $user = $this->findUser();
        if (!$user) {
            $this->error('No user found. Please create a user first or specify --user option.');
            return Command::FAILURE;
        }

        // Find team to manage
        $teamToManage = $this->findTeamToManage();
        if (!$teamToManage) {
            $this->error('Could not find team. Make sure test data is seeded (run with --seed).');
            return Command::FAILURE;
        }

        // Create the game
        $this->info("Creating game for user: {$user->email}");
        $this->info("Managing team: {$teamToManage->name}");

        $game = $gameCreationService->create(
            userId: (string) $user->id,
            teamId: $teamToManage->id,
        );

        $this->newLine();
        $this->info('Test game created successfully!');
        $this->table(
            ['Property', 'Value'],
            [
                ['Game ID', $game->id],
                ['League', 'TEST1 (4 teams, 6 matchdays)'],
                ['Cup', 'TESTCUP (4 teams, 2 rounds)'],
                ['Team', $teamToManage->name],
                ['URL', url('/game/' . $game->id)],
            ]
        );

        $this->newLine();
        $this->comment('Note: Run "php artisan queue:work --once" to process the game setup job.');

        return Command::SUCCESS;
    }

    private function findUser(): ?User
    {
        $userOption = $this->option('user');

        if ($userOption) {
            $user = User::find($userOption);
            if (!$user) {
                $user = User::where('email', $userOption)->first();
            }
            return $user;
        }

        return User::first();
    }

    private function findTeamToManage(): ?Team
    {
        $teamOption = $this->option('team');

        if ($teamOption) {
            $team = Team::where('name', 'like', "%{$teamOption}%")->first();
            if ($team) {
                return $team;
            }
            $this->warn("Team '{$teamOption}' not found, using default.");
        }

        // Default to Real Madrid (first in test league)
        return Team::where('name', 'Real Madrid')->first();
    }
}
