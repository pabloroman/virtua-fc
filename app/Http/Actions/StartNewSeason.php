<?php

namespace App\Http\Actions;

use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Modules\Season\Jobs\ProcessSeasonTransition;
use App\Models\Game;

class StartNewSeason
{
    public function __construct(
        private readonly PlayoffGeneratorFactory $playoffFactory,
        private readonly MatchFinalizationService $finalizationService,
    ) {}

    public function __invoke(string $gameId)
    {
        // End-of-season entry point: finalize any match the user abandoned on
        // the live-match screen. Without this, its standings stay unapplied and
        // the closing pipeline reads a stale league table (off-by-one games),
        // which cascades into promotion/relegation imbalance errors.
        $this->finalizationService->finalizePendingIfAny($gameId);

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

        // Pro-manager guard: a fired manager cannot start the next season
        // until they have accepted one of the post-firing offers. Without
        // this they could bypass the whole hiring loop by hitting the CTA.
        if ($game->isProManagerMode() && $game->fired_at_season_end && !$game->pending_team_switch) {
            return redirect()->route('game.season-end', $gameId)
                ->with('error', __('manager.must_accept_offer_to_continue'));
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
