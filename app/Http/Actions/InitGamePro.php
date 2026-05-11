<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Manager\Services\JobOfferService;
use Illuminate\Http\Request;

/**
 * Entry point for the pro-manager career: generates three Local-tier
 * Primera RFEF offers for the user and forwards them to the offer-picker
 * page. The actual Game record is not created here — that happens once
 * the user accepts an offer in AcceptInitialOffer.
 */
class InitGamePro
{
    public function __construct(
        private readonly JobOfferService $jobOfferService,
    ) {}

    public function __invoke(Request $request)
    {
        if (! $request->user()->canPlayCareerMode()) {
            return back()->withErrors(['game_mode' => __('messages.career_mode_requires_invite')]);
        }

        $gameCount = Game::where('user_id', $request->user()->id)
            ->whereNull('deleting_at')
            ->count();

        if ($gameCount >= 3) {
            return back()->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        $this->jobOfferService->generateInitialOffers((int) $request->user()->id);

        return redirect()->route('new-game-pro.offers');
    }
}
