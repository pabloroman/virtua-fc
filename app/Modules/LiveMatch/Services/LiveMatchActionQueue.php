<?php

namespace App\Modules\LiveMatch\Services;

use App\Models\LiveMatchAction;
use App\Models\LiveMatchSession;
use App\Models\User;
use App\Modules\LiveMatch\Enums\LiveMatchPhase;
use App\Modules\LiveMatch\Enums\LiveMatchSide;
use App\Modules\LiveMatch\Enums\QueuedActionType;
use App\Modules\LiveMatch\Exceptions\InvalidLiveActionException;

class LiveMatchActionQueue
{
    public const MAX_SUBS_PER_SIDE = 3;

    /**
     * Validate and persist a queued action. Returns the persisted row.
     *
     * Validation rules:
     *  - Session must be in Live or Paused phase.
     *  - User must be a participant.
     *  - Subs require the target player to be on pitch and the incoming
     *    player to be on bench.
     *  - Sub count must be under MAX_SUBS_PER_SIDE.
     *  - Formation/mentality changes are only allowed during a halftime pause.
     */
    public function queue(
        LiveMatchSession $session,
        User $user,
        QueuedActionType $type,
        array $payload,
    ): LiveMatchAction {
        if (! $session->phase->isInPlay()) {
            throw new InvalidLiveActionException('Match is not in play.');
        }

        if (! $session->isParticipant($user->id)) {
            throw new InvalidLiveActionException('You are not a participant in this match.');
        }

        $side = $session->isHost($user->id) ? LiveMatchSide::Home : LiveMatchSide::Away;
        $state = $session->context_state ?? [];
        $sideKey = $side->value;

        if ($type === QueuedActionType::Substitution) {
            $this->validateSub($state, $sideKey, $payload);
        } else {
            // Formation / mentality changes are halftime-only.
            if ($session->pause_reason !== 'halftime') {
                throw new InvalidLiveActionException('Tactical changes are only allowed at halftime.');
            }
            $this->validateTacticalChange($type, $payload);
        }

        return LiveMatchAction::create([
            'session_id' => $session->id,
            'user_id' => $user->id,
            'side' => $side,
            'action_type' => $type,
            'payload' => $payload,
            'queued_at_minute' => $session->current_minute,
            'status' => 'queued',
        ]);
    }

    private function validateSub(array $state, string $sideKey, array $payload): void
    {
        $out = $payload['player_out_id'] ?? null;
        $in = $payload['player_in_id'] ?? null;

        if (! is_string($out) || ! is_string($in)) {
            throw new InvalidLiveActionException('Substitution requires player_out_id and player_in_id.');
        }
        $onPitch = $state["{$sideKey}_on_pitch_ids"] ?? [];
        $bench = $state["{$sideKey}_bench_ids"] ?? [];
        if (! in_array($out, $onPitch, true)) {
            throw new InvalidLiveActionException('Selected player is not on the pitch.');
        }
        if (! in_array($in, $bench, true)) {
            throw new InvalidLiveActionException('Selected replacement is not on the bench.');
        }
        $used = $state["{$sideKey}_subs_used"] ?? 0;
        if ($used >= self::MAX_SUBS_PER_SIDE) {
            throw new InvalidLiveActionException('No substitutions remaining.');
        }
    }

    private function validateTacticalChange(QueuedActionType $type, array $payload): void
    {
        if ($type === QueuedActionType::Formation && empty($payload['formation'])) {
            throw new InvalidLiveActionException('Formation change requires a formation code.');
        }
        if ($type === QueuedActionType::Mentality && empty($payload['mentality'])) {
            throw new InvalidLiveActionException('Mentality change requires a mentality code.');
        }
    }
}
