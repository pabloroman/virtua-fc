<?php

namespace App\Console\Commands;

use App\Application\Services\InitializeSeasonService;
use Illuminate\Console\Command;

class InitializeGame extends Command
{
    protected $signature = 'app:initialize-game';
    protected $description = 'Initialize the first season of the game from JSON data';

    /**
     * Execute the console command.
     */
    public function handle(InitializeSeasonService $initializeSeasonService)
    {
        $initializeSeasonService->initializeSeasonFromJson(
            'ESP1/2024/competition.json',
            'ESP1/2024/teams.json',
            'ESP1/2024/fixtures.json',
        );

        $initializeSeasonService->initializeSeasonFromJson(
            'ESP2/2024/competition.json',
            'ESP2/2024/teams.json',
            'ESP2/2024/fixtures.json',
        );
    }
}
