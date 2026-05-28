<?php

namespace App\Http\Actions\LiveDuel;

use App\Models\LiveMatchSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Returns the current state of a live duel as JSON. Used as the canonical
 * read after each Reverb event and as a recovery path when the WebSocket
 * is unreachable.
 */
class GetLiveDuelState
{
    public function __invoke(Request $request, string $session): JsonResponse
    {
        $live = LiveMatchSession::find($session);
        // Collapse "missing" and "not a participant" into the same response
        // so an unauthenticated attacker can't enumerate session UUIDs by
        // probing the endpoint.
        if ($live === null || ! $live->isParticipant(Auth::id())) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        return response()->json([
            'session_id' => $live->id,
            'phase' => $live->phase->value,
            'home_score' => $live->home_score,
            'away_score' => $live->away_score,
            'current_minute' => $live->current_minute,
            'pause_reason' => $live->pause_reason,
            'pause_acked_by_host' => $live->pause_acked_by_host,
            'pause_acked_by_guest' => $live->pause_acked_by_guest,
            'host_bot' => $live->host_bot,
            'guest_bot' => $live->guest_bot,
            'host_team_id' => $live->host_team_id,
            'guest_team_id' => $live->guest_team_id,
            'event_log' => $live->event_log ?? [],
            'host_subs_used' => $live->context_state['home_subs_used'] ?? 0,
            'guest_subs_used' => $live->context_state['away_subs_used'] ?? 0,
            'home_on_pitch_ids' => $live->context_state['home_on_pitch_ids'] ?? [],
            'away_on_pitch_ids' => $live->context_state['away_on_pitch_ids'] ?? [],
        ]);
    }
}
