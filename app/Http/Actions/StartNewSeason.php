<?php

namespace App\Http\Actions;

use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Season\Jobs\ProcessSeasonTransition;
use App\Models\Game;

class StartNewSeason
{
    public function __construct(
        private readonly PlayoffGeneratorFactory $playoffFactory,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Verify all scheduled matches have been played. Catches the common
        // case of pending league rounds.
        $unplayedMatches = $game->matches()->where('played', false)->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', __('messages.season_not_complete'));
        }

        // Additional guard: every configured playoff must be resolved (final
        // CupTie.completed + winner_id set). Prevents firing the closing
        // pipeline while a playoff final is still awaiting resolution, which
        // would otherwise promote the wrong team via the "no playoff played"
        // fallback in the promotion rule.
        foreach ($this->playoffFactory->all() as $generator) {
            if ($generator->state($game) === PlayoffState::InProgress) {
                return redirect()->route('show-game', $gameId)
                    ->with('error', __('messages.season_not_complete'));
            }
        }

        // Atomic check-and-set: only one request can win the race
        $updated = Game::where('id', $gameId)
            ->whereNull('season_transitioning_at')
            ->update(['season_transitioning_at' => now()]);

        if (! $updated) {
            return redirect()->route('show-game', $gameId);
        }

        ProcessSeasonTransition::dispatch($gameId);

        return redirect()->route('show-game', $gameId);
    }
}
