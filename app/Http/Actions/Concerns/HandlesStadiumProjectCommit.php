<?php

namespace App\Http\Actions\Concerns;

use Closure;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

/**
 * Shared try/catch + redirect pattern for every CommitStadium* action.
 * The service layer signals user-facing errors with InvalidArgumentException
 * whose message is the translation key — the trait turns that into a flash
 * redirect back to the stadium hub. Success flashes vary by action, so each
 * caller still builds its own success redirect via stadiumSuccess().
 */
trait HandlesStadiumProjectCommit
{
    /**
     * Runs the service call and converts any InvalidArgumentException into
     * a flash redirect. Returns null on success so the caller can build its
     * own success redirect with the right translation key + params.
     */
    protected function safeCommit(string $gameId, Closure $action): ?RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return redirect()->route('game.club.stadium', $gameId)
                ->with('error', __($e->getMessage()));
        }

        return null;
    }

    protected function stadiumSuccess(string $gameId, string $messageKey, array $params = []): RedirectResponse
    {
        return redirect()->route('game.club.stadium', $gameId)
            ->with('success', __($messageKey, $params));
    }
}
