<?php

namespace App\Console\Commands;

use App\Game\Services\FinancialService;
use App\Models\Game;
use Illuminate\Console\Command;

class BackfillGameFinances extends Command
{
    protected $signature = 'app:backfill-game-finances';

    protected $description = 'Backfill finances for existing games that do not have them';

    public function __construct(
        private readonly FinancialService $financialService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $games = Game::whereDoesntHave('finances')->get();

        if ($games->isEmpty()) {
            $this->info('All games already have finances initialized.');
            return self::SUCCESS;
        }

        $this->info("Found {$games->count()} game(s) without finances.");

        $bar = $this->output->createProgressBar($games->count());
        $bar->start();

        foreach ($games as $game) {
            $finances = $this->financialService->initializeFinances($game);

            $this->line('');
            $this->info("Game {$game->id} ({$game->team->name}):");
            $this->line("  Squad Value: {$this->formatMoney($this->financialService->calculateSquadValue($game))}");
            $this->line("  TV Revenue:  {$finances->formatted_tv_revenue}");
            $this->line("  Wage Budget: {$finances->formatted_wage_budget}");
            $this->line("  Transfer Budget: {$finances->formatted_transfer_budget}");
            $this->line("  Balance: {$finances->formatted_balance}");

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info('Done!');

        return self::SUCCESS;
    }

    private function formatMoney(int $cents): string
    {
        return FinancialService::formatMoney($cents);
    }
}
