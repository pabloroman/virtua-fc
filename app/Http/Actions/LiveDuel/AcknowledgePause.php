<?php

namespace App\Http\Actions\LiveDuel;

use App\Models\LiveMatchSession;
use App\Modules\LiveMatch\Exceptions\LiveMatchStateException;
use App\Modules\LiveMatch\Services\LiveMatchOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AcknowledgePause
{
    public function __construct(
        private readonly LiveMatchOrchestrator $orchestrator,
    ) {}

    public function __invoke(Request $request, string $session): JsonResponse
    {
        $live = LiveMatchSession::findOrFail($session);

        try {
            $live = $this->orchestrator->acknowledgePause($live, Auth::user());
        } catch (LiveMatchStateException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'phase' => $live->phase->value,
            'host_acked' => $live->pause_acked_by_host,
            'guest_acked' => $live->pause_acked_by_guest,
        ]);
    }
}
