<?php

namespace App\Http\Actions\LiveDuel;

use App\Models\Team;
use App\Modules\LiveMatch\Exceptions\NoEligibleSquadException;
use App\Modules\LiveMatch\Services\LiveMatchOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CreateLiveDuelSession
{
    public function __construct(
        private readonly LiveMatchOrchestrator $orchestrator,
    ) {}

    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'team_id' => ['required', 'uuid'],
        ]);

        $team = Team::query()->worldCupEligible()->find($data['team_id']);
        if ($team === null) {
            return back()->withErrors(['team_id' => __('live_duel.unknown_nation')]);
        }

        try {
            $session = $this->orchestrator->createSession(Auth::user(), $team);
        } catch (NoEligibleSquadException $e) {
            return back()->withErrors(['team_id' => $e->getMessage()]);
        }

        return redirect()->route('live.duel.show', ['session' => $session->id]);
    }
}
