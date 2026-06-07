<?php

namespace App\Http\Actions\Concerns;

use Closure;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

/**
 * Shared try/catch + redirect pattern for Commercial-page actions (seeking
 * sponsors, accepting a naming-rights deal). The service layer signals
 * user-facing errors with InvalidArgumentException whose message is the
 * translation key — the trait turns that into a flash redirect back to the
 * Commercial hub. Mirrors HandlesStadiumProjectCommit, but lands on the
 * Commercial page rather than the stadium page.
 */
trait HandlesCommercialCommit
{
    /**
     * Runs the service call and converts any InvalidArgumentException into a
     * flash redirect. Returns null on success so the caller can build its own
     * success redirect with the right translation key + params.
     */
    protected function safeCommit(string $gameId, Closure $action): ?RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return redirect()->route('game.club.commercial', $gameId)
                ->with('error', __($e->getMessage()));
        }

        return null;
    }

    protected function commercialSuccess(string $gameId, string $messageKey, array $params = []): RedirectResponse
    {
        return redirect()->route('game.club.commercial', $gameId)
            ->with('success', __($messageKey, $params));
    }
}
