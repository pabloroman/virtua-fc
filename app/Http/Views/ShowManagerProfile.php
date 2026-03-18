<?php

namespace App\Http\Views;

use App\Models\User;
use App\Services\ManagerProfileService;

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

        $user->load(['games.team', 'games.competition']);

        return view('profile.show', [
            'user' => $user,
            'trophies' => $this->profileService->getTrophies($user),
            'careerStats' => $this->profileService->getCareerStats($user),
        ]);
    }
}
