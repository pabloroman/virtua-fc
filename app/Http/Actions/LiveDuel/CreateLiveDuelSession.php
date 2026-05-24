<?php

namespace App\Http\Actions\LiveDuel;

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
            'iso' => ['required', 'string', 'size:2', 'max:3'],
        ]);

        $nation = $this->resolveNation($data['iso']);
        if ($nation === null) {
            return back()->withErrors(['iso' => __('live_duel.unknown_nation')]);
        }

        try {
            $session = $this->orchestrator->createSession(
                Auth::user(),
                $nation['iso'],
                $nation['name'],
            );
        } catch (NoEligibleSquadException $e) {
            return back()->withErrors(['iso' => $e->getMessage()]);
        }

        return redirect()->route('live.duel.show', ['session' => $session->id]);
    }

    private function resolveNation(string $iso): ?array
    {
        foreach (ShowLiveDuelEntry::nationCatalog() as $nation) {
            if ($nation['iso'] === $iso) {
                return $nation;
            }
        }

        return null;
    }
}
