<?php

namespace App\Http\Actions;

use App\Models\Game;
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

        $validated = $request->validate([
            'assignments' => 'required|array',
            'assignments.*' => 'required|integer|min:1|max:99',
        ]);

        $result = $this->registrationService->registerPlayers($game, $validated['assignments']);

        if (!$result['success']) {
            return back()->with('error', $result['error']);
        }

        return redirect()->route('show-game', $gameId)
            ->with('success', __('messages.registration_saved'));
    }
}
