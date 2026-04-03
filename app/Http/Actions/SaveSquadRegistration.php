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

        return back()->with('success', __('squad.registration_saved'));
    }
}
