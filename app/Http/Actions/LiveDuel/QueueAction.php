<?php

namespace App\Http\Actions\LiveDuel;

use App\Models\LiveMatchSession;
use App\Modules\LiveMatch\Enums\QueuedActionType;
use App\Modules\LiveMatch\Events\LiveMatchActionQueuedBroadcast;
use App\Modules\LiveMatch\Exceptions\InvalidLiveActionException;
use App\Modules\LiveMatch\Services\LiveMatchActionQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QueueAction
{
    public function __construct(
        private readonly LiveMatchActionQueue $queue,
    ) {}

    public function __invoke(Request $request, string $session): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'payload' => ['required', 'array'],
        ]);

        $type = QueuedActionType::tryFrom($data['type']);
        if ($type === null) {
            return response()->json(['error' => 'invalid_action_type'], 422);
        }

        $live = LiveMatchSession::findOrFail($session);

        try {
            $action = $this->queue->queue($live, Auth::user(), $type, $data['payload']);
        } catch (InvalidLiveActionException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        LiveMatchActionQueuedBroadcast::dispatch($live, $action);

        return response()->json([
            'id' => $action->id,
            'status' => $action->status,
            'queued_at_minute' => $action->queued_at_minute,
        ]);
    }
}
