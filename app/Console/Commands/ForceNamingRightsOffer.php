<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Modules\Stadium\Services\NamingRightsService;
use App\Support\Money;
use Illuminate\Console\Command;

/**
 * Forces stadium naming-rights offers onto a game so the feature can be
 * inspected without waiting on the per-tick probability roll. Honours the
 * same gates as the real mechanic (open window, no active deal, pending cap,
 * sponsor dedupe), so it can't manufacture impossible states.
 */
class ForceNamingRightsOffer extends Command
{
    protected $signature = 'app:force-naming-rights-offer
                            {game : Game ID}
                            {--count=1 : Number of offers to force}';

    protected $description = 'Force stadium naming-rights offers onto a game (debugging)';

    public function handle(NamingRightsService $service): int
    {
        $game = Game::find($this->argument('game'));

        if (! $game) {
            $this->error("Game not found: {$this->argument('game')}");

            return self::FAILURE;
        }

        if (! $service->windowOpen($game)) {
            $this->warn('The stadium identity window is closed (league under way). Forced offers will be created but will not show in the stadium panel until pre-season / the first matchday.');
        }

        $count = max(1, (int) $this->option('count'));
        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            $deal = $service->forceOffer($game);

            if ($deal === null) {
                $this->warn('No further offer created (window closed, a deal is active, the pending cap is full, or every sponsor is already on the table).');
                break;
            }

            $created++;
            $this->info(sprintf(
                'Offer: %s → "%s" — %s/season for %d season(s)',
                $deal->sponsor_name,
                $deal->proposed_stadium_name,
                Money::format($deal->annual_value_cents),
                $deal->contract_seasons,
            ));
        }

        $this->line("Created {$created} offer(s).");

        return self::SUCCESS;
    }
}
