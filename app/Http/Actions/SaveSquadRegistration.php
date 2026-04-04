<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Squad\Services\RegistrationException;
use App\Modules\Squad\Services\SquadRegistrationService;
use Illuminate\Http\Request;

class SaveSquadRegistration
{
    public function __construct(
        private readonly SquadRegistrationService $registrationService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if (! $game->isTransferWindowOpen() && ! $game->hasPendingAction('squad_registration')) {
            return redirect()->route('game.squad', $gameId);
        }

        $validated = $request->validate([
            'assignments' => 'present|array|max:99',
            'assignments.*.player_id' => 'required|string',
            'assignments.*.number' => 'required|integer|min:1|max:99',
        ]);

        try {
            $this->registrationService->save($game, collect($validated['assignments'] ?? []));
        } catch (RegistrationException $e) {
            return back()->with('error', $e->getMessage());
        }

        $hadPendingAction = $game->hasPendingAction('squad_registration');
        $game->removePendingAction('squad_registration');

        if ($hadPendingAction) {
            return redirect()->route('show-game', $gameId)->with('success', __('squad.registration_saved'));
        }

        return back()->with('success', __('squad.registration_saved'));
    }
}
