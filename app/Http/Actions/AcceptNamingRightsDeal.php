<?php

namespace App\Http\Actions;

use App\Http\Actions\Concerns\HandlesStadiumProjectCommit;
use App\Models\Game;
use App\Modules\Stadium\Services\NamingRightsService;
use Illuminate\Http\Request;

class AcceptNamingRightsDeal
{
    use HandlesStadiumProjectCommit;

    public function __construct(
        private readonly NamingRightsService $namingRightsService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $validated = $request->validate([
            'deal_id' => 'required|string',
        ]);

        $sponsorName = null;
        if ($redirect = $this->safeCommit($gameId, function () use ($game, $validated, &$sponsorName) {
            $deal = $this->namingRightsService->acceptOffer($game, $validated['deal_id']);
            $sponsorName = $deal->sponsor_name;
        })) {
            return $redirect;
        }

        return $this->stadiumSuccess($gameId, 'messages.naming_rights_accepted', [
            'sponsor' => $sponsorName,
        ]);
    }
}
