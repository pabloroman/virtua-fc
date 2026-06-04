<?php

namespace App\Modules\LiveMatch\Services;

use App\Models\LiveMatchSession;
use App\Models\Team;
use App\Models\User;
use App\Modules\LiveMatch\Enums\LiveMatchPhase;
use App\Modules\LiveMatch\Events\LiveMatchBotTakeoverBroadcast;
use App\Modules\LiveMatch\Events\LiveMatchEndedBroadcast;
use App\Modules\LiveMatch\Events\LiveMatchEventBroadcast;
use App\Modules\LiveMatch\Events\LiveMatchGuestJoinedBroadcast;
use App\Modules\LiveMatch\Events\LiveMatchPausedBroadcast;
use App\Modules\LiveMatch\Events\LiveMatchResumedBroadcast;
use App\Modules\LiveMatch\Events\LiveMatchStartedBroadcast;
use App\Modules\LiveMatch\Events\LiveMatchTeamPickedBroadcast;
use App\Modules\LiveMatch\Exceptions\LiveMatchStateException;
use App\Modules\LiveMatch\Jobs\AdvanceLiveMatchWindowJob;
use App\Modules\LiveMatch\Jobs\MarkAsBotJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LiveMatchOrchestrator
{
    /**
     * Each live match is split into this many simulation windows. ~10 minute
     * slices keeps decisions (subs, tactical changes) responsive while
     * avoiding too many job dispatches.
     */
    public const WINDOW_SIZE_MINUTES = 10;

    public const TOTAL_MATCH_MINUTES = 93;

    /**
     * Wall-clock delay (seconds) between window jobs. Approximates a
     * 60-second real-time match: 9 windows × ~6.7s = ~60s.
     */
    public const WINDOW_DELAY_SECONDS = 6;

    public const BOT_TAKEOVER_TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly NationalSquadBuilder $squadBuilder,
        private readonly AutoLineupBuilder $autoLineupBuilder,
        private readonly LiveMatchEngineAdapter $engineAdapter,
    ) {}

    /**
     * Host picks a national team. Creates the session and snapshots the
     * host's squad in one shot. The session UUID is the share token.
     */
    public function createSession(User $host, Team $team): LiveMatchSession
    {
        $build = $this->squadBuilder->buildFor($host, $team);
        $rehydrated = $this->squadBuilder->rehydrate($build['players']);
        $snapshot = $this->autoLineupBuilder->build($team, $rehydrated);

        return LiveMatchSession::create([
            'phase' => LiveMatchPhase::Lobby,
            'host_user_id' => $host->id,
            'host_team_id' => $team->id,
            'host_squad' => $snapshot->toArray(),
            'match_seed' => Str::random(16),
        ]);
    }

    /**
     * First authenticated non-host visitor claims the guest slot. Idempotent
     * for the existing guest. Rejects host (self-duel) and rejects when slot
     * is already taken by someone else.
     */
    public function claimGuestSlot(LiveMatchSession $session, User $user): LiveMatchSession
    {
        return DB::transaction(function () use ($session, $user) {
            $fresh = LiveMatchSession::lockForUpdate()->findOrFail($session->id);

            if ($fresh->isHost($user->id)) {
                throw new LiveMatchStateException('You cannot duel yourself.');
            }
            if ($fresh->guest_user_id !== null && $fresh->guest_user_id !== $user->id) {
                throw new LiveMatchStateException('This match is already full.');
            }
            if ($fresh->phase->isTerminal()) {
                throw new LiveMatchStateException('This match has ended.');
            }
            if ($fresh->guest_user_id === null) {
                $fresh->update(['guest_user_id' => $user->id]);
            }

            return $fresh;
        });
    }

    /**
     * Guest picks their national team. Snapshots their squad and, since the
     * guest must be on the page to make this call, kicks off immediately
     * rather than wait for a presence-channel handshake.
     */
    public function pickGuestTeam(LiveMatchSession $session, User $user, Team $team): LiveMatchSession
    {
        if (! $session->isGuest($user->id)) {
            throw new LiveMatchStateException('Only the guest can set the guest team.');
        }
        if ($session->phase !== LiveMatchPhase::Lobby) {
            throw new LiveMatchStateException('Cannot change team once the match has started.');
        }

        $build = $this->squadBuilder->buildFor($user, $team);
        $rehydrated = $this->squadBuilder->rehydrate($build['players']);
        $snapshot = $this->autoLineupBuilder->build($team, $rehydrated);

        $session->update([
            'guest_team_id' => $team->id,
            'guest_squad' => $snapshot->toArray(),
        ]);

        $session->refresh();
        LiveMatchTeamPickedBroadcast::dispatch($session);

        $this->attemptKickoff($session, bothPresent: true);

        return $session->refresh();
    }

    /**
     * Try to kick off the match. Called both after a team pick and after a
     * presence-channel member_added event. Idempotent — does nothing unless
     * both teams are picked AND both members are on the channel.
     */
    public function attemptKickoff(LiveMatchSession $session, bool $bothPresent): void
    {
        if (! $bothPresent) {
            return;
        }
        if ($session->phase !== LiveMatchPhase::Lobby) {
            return;
        }
        if (! $session->bothTeamsPicked()) {
            return;
        }

        $initial = $this->engineAdapter->buildInitialContextState($session);

        // Row lock + DB-side increment so two concurrent kickoff calls (e.g.
        // simultaneous presence and pick-team triggers) can't both transition
        // Lobby → Live and dispatch two AdvanceLiveMatchWindowJobs.
        $newVersion = DB::transaction(function () use ($session, $initial) {
            $locked = LiveMatchSession::lockForUpdate()->findOrFail($session->id);
            if ($locked->phase !== LiveMatchPhase::Lobby) {
                return null;
            }
            $locked->update([
                'phase' => LiveMatchPhase::Live,
                'context_state' => $initial,
                'current_minute' => 0,
                'clock_version' => DB::raw('clock_version + 1'),
            ]);

            return $locked->fresh()->clock_version;
        });

        if ($newVersion === null) {
            return;
        }

        LiveMatchStartedBroadcast::dispatch($session->fresh());

        AdvanceLiveMatchWindowJob::dispatch($session->id, $newVersion, 0);
    }

    /**
     * Advance the match by one window. Called by AdvanceLiveMatchWindowJob.
     * Returns true if the match continues, false if it has ended (caller
     * should not dispatch the next window).
     */
    public function advanceWindow(LiveMatchSession $session, int $from): bool
    {
        if ($session->phase !== LiveMatchPhase::Live) {
            return false;
        }

        $to = min($from + self::WINDOW_SIZE_MINUTES, self::TOTAL_MATCH_MINUTES);
        $window = $this->engineAdapter->runWindow($session, $from, $to);
        $session->refresh();

        // Broadcast new events for this window.
        $homeTeamId = $this->engineAdapter->homeTeamIdFor($session);
        foreach ($window->newEvents as $event) {
            LiveMatchEventBroadcast::dispatch($session, [
                'minute' => $event->minute,
                'type' => $event->type,
                'team_id' => $event->teamId,
                'side' => $event->teamId === $homeTeamId ? 'home' : 'away',
                'game_player_id' => $event->gamePlayerId,
                'metadata' => $event->metadata,
            ]);
        }

        // Reached full time.
        if ($to >= self::TOTAL_MATCH_MINUTES) {
            $session->update([
                'phase' => LiveMatchPhase::Finished,
                'finished_at' => now(),
            ]);
            LiveMatchEndedBroadcast::dispatch($session->fresh());

            return false;
        }

        // Trigger halftime pause once we cross minute 45.
        if ($from < 45 && $to >= 45) {
            $this->pause($session, 'halftime');

            return false;
        }

        // Check for goal/red-card/injury that should pause the match.
        $significantEvent = $window->newEvents->first(fn ($e) => in_array($e->type, ['goal', 'red_card', 'injury'], true));
        if ($significantEvent !== null) {
            $this->pause($session, $significantEvent->type);

            return false;
        }

        return true;
    }

    private function pause(LiveMatchSession $session, string $reason): void
    {
        $session->update([
            'phase' => LiveMatchPhase::Paused,
            'pause_reason' => $reason,
            'pause_acked_by_host' => false,
            'pause_acked_by_guest' => false,
            'paused_at' => now(),
        ]);
        LiveMatchPausedBroadcast::dispatch($session->fresh());
    }

    /**
     * One side acknowledges the pause. When both acks land (or one side is
     * bot), resumes and dispatches the next window.
     */
    public function acknowledgePause(LiveMatchSession $session, User $user): LiveMatchSession
    {
        if (! $session->isParticipant($user->id)) {
            throw new LiveMatchStateException('You are not a participant.');
        }

        // Lock to serialize concurrent acks — without it, both sides can read
        // each other as not-yet-acked and both trigger resume(), dispatching
        // duplicate AdvanceLiveMatchWindowJobs.
        [$resumePayload, $session] = DB::transaction(function () use ($session, $user) {
            $locked = LiveMatchSession::lockForUpdate()->findOrFail($session->id);

            if ($locked->phase !== LiveMatchPhase::Paused) {
                throw new LiveMatchStateException('Match is not paused.');
            }

            $patch = $locked->isHost($user->id)
                ? ['pause_acked_by_host' => true]
                : ['pause_acked_by_guest' => true];
            $locked->update($patch);
            $locked->refresh();

            $hostReady = $locked->pause_acked_by_host || $locked->host_bot;
            $guestReady = $locked->pause_acked_by_guest || $locked->guest_bot;

            if ($hostReady && $guestReady) {
                $payload = $this->applyResume($locked);

                return [$payload, $locked->fresh()];
            }

            return [null, $locked];
        });

        if ($resumePayload !== null) {
            LiveMatchResumedBroadcast::dispatch($session);
            AdvanceLiveMatchWindowJob::dispatch(
                $resumePayload['session_id'],
                $resumePayload['clock_version'],
                $resumePayload['from_minute'],
            );
        }

        return $session;
    }

    /**
     * Transition Paused → Live and bump clock_version atomically. Caller must
     * hold the row lock; returns the payload the post-commit dispatcher needs.
     *
     * @return array{session_id: string, clock_version: int, from_minute: int}
     */
    private function applyResume(LiveMatchSession $session): array
    {
        $session->update([
            'phase' => LiveMatchPhase::Live,
            'pause_reason' => null,
            'pause_acked_by_host' => false,
            'pause_acked_by_guest' => false,
            'paused_at' => null,
            'clock_version' => DB::raw('clock_version + 1'),
        ]);
        $session->refresh();

        return [
            'session_id' => $session->id,
            'clock_version' => $session->clock_version,
            'from_minute' => $session->current_minute,
        ];
    }

    /**
     * Member left the presence channel. Start a takeover timer; if they
     * don't reconnect within BOT_TAKEOVER_TIMEOUT_SECONDS, MarkAsBotJob
     * flips that side to bot and the match continues.
     */
    public function handleDisconnect(LiveMatchSession $session, int $userId): void
    {
        if (! $session->isParticipant($userId)) {
            return;
        }
        if ($session->phase->isTerminal()) {
            return;
        }

        MarkAsBotJob::dispatch($session->id, $userId, $session->clock_version)
            ->delay(now()->addSeconds(self::BOT_TAKEOVER_TIMEOUT_SECONDS));
    }

    public function markAsBot(LiveMatchSession $session, int $userId): void
    {
        // Flip the bot flag + maybe-resume under the same row lock as the ack
        // path; if the displaced user's pause-ack was the only thing blocking
        // resume, this transition fires it.
        $resumePayload = DB::transaction(function () use ($session, $userId) {
            $locked = LiveMatchSession::lockForUpdate()->findOrFail($session->id);

            if ($locked->isHost($userId)) {
                $locked->update(['host_bot' => true]);
            } elseif ($locked->isGuest($userId)) {
                $locked->update(['guest_bot' => true]);
            } else {
                return null;
            }
            $locked->refresh();

            if ($locked->phase !== LiveMatchPhase::Paused) {
                return null;
            }
            $hostReady = $locked->pause_acked_by_host || $locked->host_bot;
            $guestReady = $locked->pause_acked_by_guest || $locked->guest_bot;
            if (! ($hostReady && $guestReady)) {
                return null;
            }

            return $this->applyResume($locked);
        });

        LiveMatchBotTakeoverBroadcast::dispatch($session->fresh(), $userId);

        if ($resumePayload !== null) {
            LiveMatchResumedBroadcast::dispatch($session->fresh());
            AdvanceLiveMatchWindowJob::dispatch(
                $resumePayload['session_id'],
                $resumePayload['clock_version'],
                $resumePayload['from_minute'],
            );
        }
    }

    public function broadcastGuestJoined(LiveMatchSession $session): void
    {
        LiveMatchGuestJoinedBroadcast::dispatch($session);
    }
}
