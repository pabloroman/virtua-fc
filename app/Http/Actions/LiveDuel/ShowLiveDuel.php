<?php

namespace App\Http\Actions\LiveDuel;

use App\Models\LiveMatchSession;
use App\Modules\LiveMatch\Enums\LiveMatchPhase;
use App\Modules\LiveMatch\Exceptions\LiveMatchStateException;
use App\Modules\LiveMatch\Services\LiveMatchOrchestrator;
use App\Modules\LiveMatch\Services\NationalSquadBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Single shareable page that adapts to viewer + session state:
 *  - host alone:      "waiting for opponent" + share link
 *  - guest, first visit (unauthenticated → login redirect handled by middleware):
 *                     claims the guest slot and shows the team picker
 *  - guest, after pick: "waiting for kickoff"
 *  - host, while guest is picking: "opponent choosing"
 *  - third visitor (different user, session already has a guest): "match full"
 *  - in-play / paused / finished: render the live match view
 */
class ShowLiveDuel
{
    public function __construct(
        private readonly LiveMatchOrchestrator $orchestrator,
        private readonly NationalSquadBuilder $squadBuilder,
    ) {}

    public function __invoke(Request $request, string $session)
    {
        $user = Auth::user();
        $live = LiveMatchSession::findOrFail($session);

        // Third-visitor guard.
        if (! $live->isParticipant($user->id) && $live->guest_user_id !== null) {
            return response()->view('live.duel.full', ['session' => $live], 403);
        }

        // Claim guest slot for the first non-host visitor.
        if (! $live->isParticipant($user->id) && $live->guest_user_id === null) {
            try {
                $live = $this->orchestrator->claimGuestSlot($live, $user);
                $this->orchestrator->broadcastGuestJoined($live);
            } catch (LiveMatchStateException $e) {
                return response()->view('live.duel.full', ['session' => $live, 'reason' => $e->getMessage()], 403);
            }
        }

        $viewerRole = $live->isHost($user->id) ? 'host' : 'guest';

        if ($live->phase === LiveMatchPhase::Lobby) {
            return $this->renderLobby($live, $user, $viewerRole);
        }

        return view('live.duel.show', [
            'session' => $live,
            'viewerRole' => $viewerRole,
            'viewerSide' => $viewerRole === 'host' ? 'home' : 'away',
            'reverbKey' => config('broadcasting.connections.reverb.key'),
            'reverbHost' => config('broadcasting.connections.reverb.options.host'),
            'reverbPort' => config('broadcasting.connections.reverb.options.port'),
            'reverbScheme' => config('broadcasting.connections.reverb.options.scheme'),
        ]);
    }

    private function renderLobby(LiveMatchSession $session, $user, string $viewerRole)
    {
        $iAmReady = $viewerRole === 'host'
            ? $session->host_team_id !== null
            : $session->guest_team_id !== null;

        // Guest needs to pick a team — show the team picker with host's team marked taken.
        if ($viewerRole === 'guest' && ! $iAmReady) {
            $teams = ShowLiveDuelEntry::availableNationalTeams();
            $eligibility = $teams->mapWithKeys(
                fn ($t) => [$t->id => $this->squadBuilder->eligibleCountFor($user, $t)],
            )->all();

            return view('live.duel.entry', [
                'teams' => $teams,
                'eligibility' => $eligibility,
                'role' => 'guest',
                'takenTeamId' => $session->host_team_id,
                'session' => $session,
            ]);
        }

        return view('live.duel.lobby', [
            'session' => $session,
            'viewerRole' => $viewerRole,
            'reverbKey' => config('broadcasting.connections.reverb.key'),
            'reverbHost' => config('broadcasting.connections.reverb.options.host'),
            'reverbPort' => config('broadcasting.connections.reverb.options.port'),
            'reverbScheme' => config('broadcasting.connections.reverb.options.scheme'),
        ]);
    }
}
