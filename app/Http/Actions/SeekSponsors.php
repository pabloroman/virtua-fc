<?php

namespace App\Http\Actions;

use App\Http\Actions\Concerns\HandlesCommercialCommit;
use App\Models\Game;
use App\Modules\Stadium\Services\NamingRightsService;

/**
 * Proactively seek naming-rights sponsors from the Commercial page: charges
 * the agency fee and tops the offer board up with fresh offers. All gating
 * (window, active deal, board full, cooldown, affordability) lives in
 * NamingRightsService::seekSponsors and surfaces as a flash error.
 */
class SeekSponsors
{
    use HandlesCommercialCommit;

    public function __construct(
        private readonly NamingRightsService $namingRightsService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $count = 0;
        if ($redirect = $this->safeCommit($gameId, function () use ($game, &$count) {
            $count = count($this->namingRightsService->seekSponsors($game));
        })) {
            return $redirect;
        }

        // Pluralised count → trans_choice rather than the simple commercialSuccess.
        return redirect()->route('game.club.commercial', $gameId)
            ->with('success', trans_choice('messages.naming_rights_search_complete', $count, ['count' => $count]));
    }
}
