<?php

namespace App\Http\Actions\LiveDuel;

use App\Models\LiveMatchSession;
use App\Models\Team;
use App\Modules\LiveMatch\Exceptions\LiveMatchStateException;
use App\Modules\LiveMatch\Exceptions\NoEligibleSquadException;
use App\Modules\LiveMatch\Services\LiveMatchOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PickGuestTeam
{
    public function __construct(
        private readonly LiveMatchOrchestrator $orchestrator,
    ) {}

    public function __invoke(Request $request, string $session)
    {
        $data = $request->validate([
            'team_id' => ['required', 'uuid'],
        ]);

        $live = LiveMatchSession::findOrFail($session);
        // Authorize before any DB or service work — the orchestrator also
        // checks but we'd rather not even resolve the team for a non-guest.
        abort_unless($live->isGuest(Auth::id()), 403);

        $team = Team::query()->worldCupEligible()->find($data['team_id']);
        if ($team === null) {
            return back()->withErrors(['team_id' => __('live_duel.unknown_nation')]);
        }
        if ($live->host_team_id === $team->id) {
            return back()->withErrors(['team_id' => __('live_duel.team_already_picked')]);
        }

        try {
            $this->orchestrator->pickGuestTeam($live, Auth::user(), $team);
        } catch (NoEligibleSquadException|LiveMatchStateException $e) {
            return back()->withErrors(['team_id' => $e->getMessage()]);
        }

        return redirect()->route('live.duel.show', ['session' => $live->id]);
    }
}
