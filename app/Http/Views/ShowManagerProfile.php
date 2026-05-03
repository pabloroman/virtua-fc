<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\User;
use App\Modules\Manager\Services\ManagerProfileService;

class ShowManagerProfile
{
    public function __construct(
        private ManagerProfileService $profileService,
    ) {}

    public function __invoke(string $username)
    {
        $user = User::where('username', $username)
            ->where('is_profile_public', true)
            ->firstOrFail();

        // Games live on the tenant plane; resolved as a separate query and
        // attached as a relation so the view's `$user->games` access keeps
        // working. Replaces a `User::load(['games.team', 'games.competition'])`
        // that crossed the control/tenant plane boundary.
        $games = Game::with(['team', 'competition'])
            ->where('user_id', $user->id)
            ->get();

        $user->setRelation('games', $games);

        return view('profile.show', [
            'user' => $user,
            'trophies' => $this->profileService->getTrophies($user),
            'careerStats' => $this->profileService->getCareerStats($user),
        ]);
    }
}
