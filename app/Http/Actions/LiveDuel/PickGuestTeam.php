<?php

namespace App\Http\Actions\LiveDuel;

use App\Models\LiveMatchSession;
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
            'iso' => ['required', 'string', 'max:64'],
        ]);

        $nation = null;
        foreach (ShowLiveDuelEntry::nationCatalog() as $option) {
            if ($option['iso'] === $data['iso']) {
                $nation = $option;
                break;
            }
        }
        if ($nation === null) {
            return back()->withErrors(['iso' => __('live_duel.unknown_nation')]);
        }

        $live = LiveMatchSession::findOrFail($session);
        if ($live->host_iso_code === $nation['iso']) {
            return back()->withErrors(['iso' => __('live_duel.team_already_picked')]);
        }

        try {
            $this->orchestrator->pickGuestTeam(
                $live,
                Auth::user(),
                $nation['iso'],
                $nation['name'],
            );
        } catch (NoEligibleSquadException|LiveMatchStateException $e) {
            return back()->withErrors(['iso' => $e->getMessage()]);
        }

        return redirect()->route('live.duel.show', ['session' => $live->id]);
    }
}
