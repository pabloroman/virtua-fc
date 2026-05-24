<x-app-layout>
    @php
        $hostTeam = $session->host_team_id ? \App\Models\Team::find($session->host_team_id) : null;
        $guestTeam = $session->guest_team_id ? \App\Models\Team::find($session->guest_team_id) : null;
    @endphp

    <div
        x-data="liveDuel({
            sessionId: @js($session->id),
            viewerRole: @js($viewerRole),
            viewerSide: @js($viewerSide),
            csrfToken: @js(csrf_token()),
            reverbKey: @js($reverbKey),
            reverbHost: @js($reverbHost),
            reverbPort: @js($reverbPort),
            reverbScheme: @js($reverbScheme),
            initial: {
                phase: @js($session->phase->value),
                homeScore: @js($session->home_score),
                awayScore: @js($session->away_score),
                currentMinute: @js($session->current_minute),
                pauseReason: @js($session->pause_reason),
                hostBot: @js($session->host_bot),
                guestBot: @js($session->guest_bot),
                eventLog: @js($session->event_log ?? []),
                hostSquad: @js($session->host_squad ?? []),
                guestSquad: @js($session->guest_squad ?? []),
            },
            labels: {
                pauseReasonGoal: @js(__('live_duel.pause_reason_goal')),
                pauseReasonRedCard: @js(__('live_duel.pause_reason_red_card')),
                pauseReasonInjury: @js(__('live_duel.pause_reason_injury')),
                pauseReasonHalftime: @js(__('live_duel.pause_reason_halftime')),
                fulltime: @js(__('live_duel.fulltime')),
                continue: @js(__('live_duel.continue')),
                opponentReady: @js(__('live_duel.opponent_ready')),
                waitingOpponentAck: @js(__('live_duel.waiting_opponent_ack')),
                queueSub: @js(__('live_duel.queue_sub')),
                pickPlayerOut: @js(__('live_duel.pick_player_out')),
                pickPlayerIn: @js(__('live_duel.pick_player_in')),
                opponentPreparingSub: @js(__('live_duel.opponent_preparing_sub')),
                connecting: @js(__('live_duel.connecting')),
            },
        })"
        x-init="init()"
        class="min-h-screen bg-surface-900 text-text-primary"
    >
        <!-- Scoreboard -->
        <div class="bg-surface-800 border-b border-border-default px-4 py-3">
            <div class="max-w-5xl mx-auto flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 flex-1">
                    @if ($hostTeam?->image)
                        <img src="{{ $hostTeam->image }}" alt="{{ $hostTeam->name }}" class="w-8 h-8 object-contain">
                    @endif
                    <div class="font-semibold text-text-primary truncate">{{ $hostTeam?->name ?? '' }}</div>
                    <span x-show="hostBot" class="text-xs px-2 py-0.5 bg-accent-amber/20 text-accent-amber rounded">BOT</span>
                </div>
                <div class="text-center">
                    <div class="text-3xl md:text-4xl font-bold tabular-nums">
                        <span x-text="homeScore"></span>
                        <span class="text-text-muted">-</span>
                        <span x-text="awayScore"></span>
                    </div>
                    <div class="text-xs text-text-muted tabular-nums mt-1">
                        <span x-text="displayMinute + '\''"></span>
                    </div>
                </div>
                <div class="flex items-center gap-3 flex-1 justify-end">
                    <span x-show="guestBot" class="text-xs px-2 py-0.5 bg-accent-amber/20 text-accent-amber rounded">BOT</span>
                    <div class="font-semibold text-text-primary truncate text-right">{{ $guestTeam?->name ?? '' }}</div>
                    @if ($guestTeam?->image)
                        <img src="{{ $guestTeam->image }}" alt="{{ $guestTeam->name }}" class="w-8 h-8 object-contain">
                    @endif
                </div>
            </div>
        </div>

        <!-- Connection banner -->
        <div x-show="!connected" class="bg-accent-amber/10 border-b border-accent-amber/30 px-4 py-2 text-center text-sm text-text-primary">
            <span x-text="labels.connecting"></span>
        </div>

        <div class="max-w-5xl mx-auto p-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Event ticker (left/center) -->
            <div class="lg:col-span-2 bg-surface-800 border border-border-default rounded-xl p-4">
                <h3 class="text-sm font-semibold text-text-muted mb-3 uppercase tracking-wide">Live</h3>
                <ul class="space-y-2 max-h-96 overflow-y-auto">
                    <template x-for="event in events.slice().reverse()" :key="event.id">
                        <li class="flex items-start gap-3 p-2 rounded bg-surface-700/50">
                            <span class="text-xs font-mono text-text-muted tabular-nums" x-text="event.minute + '\''"></span>
                            <span class="text-xl" x-text="eventIcon(event.type)"></span>
                            <span class="text-sm text-text-primary" x-text="eventLabel(event)"></span>
                        </li>
                    </template>
                    <li x-show="events.length === 0" class="text-sm text-text-muted text-center py-4">
                        Match starting…
                    </li>
                </ul>

                <!-- Opponent activity hint -->
                <div x-show="opponentPreparingSub" class="mt-3 text-xs text-text-muted italic" x-text="labels.opponentPreparingSub"></div>
            </div>

            <!-- Your control panel (right) -->
            <div class="bg-surface-800 border border-border-default rounded-xl p-4">
                <h3 class="text-sm font-semibold text-text-muted mb-3 uppercase tracking-wide">Your team</h3>

                <!-- Subs remaining -->
                <div class="text-sm mb-3" x-text="@js(__('live_duel.subs_remaining', ['count' => ''])) + (3 - subsUsed)"></div>

                <!-- Queue sub -->
                <button
                    type="button"
                    @click="openSubModal()"
                    :disabled="subsUsed >= 3 || phase === 'finished'"
                    class="w-full px-4 py-2 bg-accent-blue text-white rounded-lg font-semibold hover:bg-accent-blue/80 disabled:opacity-40 disabled:cursor-not-allowed transition"
                    x-text="labels.queueSub"
                ></button>

                <!-- Your on-pitch list -->
                <div class="mt-4 text-xs text-text-muted">On pitch:</div>
                <ul class="text-sm space-y-1 mt-1 max-h-48 overflow-y-auto">
                    <template x-for="player in myOnPitch" :key="player.id">
                        <li class="flex justify-between">
                            <span x-text="player.name"></span>
                            <span class="text-text-muted tabular-nums" x-text="player.overall_score"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        <!-- Pause overlay -->
        <div
            x-show="phase === 'paused'"
            x-transition.opacity
            class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4"
        >
            <div class="bg-surface-800 border border-border-default rounded-2xl p-6 md:p-8 max-w-md w-full text-center">
                <div class="text-5xl mb-3" x-text="pauseIcon()"></div>
                <h3 class="text-xl font-bold text-text-primary mb-2" x-text="pauseLabel()"></h3>
                <p class="text-text-muted mb-6 text-sm" x-text="myAcked ? labels.waitingOpponentAck : ''"></p>
                <button
                    type="button"
                    @click="ackPause()"
                    :disabled="myAcked"
                    class="w-full px-4 py-3 bg-accent-blue text-white rounded-lg font-semibold hover:bg-accent-blue/80 disabled:opacity-40 transition"
                    x-text="myAcked ? labels.waitingOpponentAck : labels.continue"
                ></button>
            </div>
        </div>

        <!-- Sub modal -->
        <div
            x-show="subModalOpen"
            x-transition.opacity
            @keydown.escape.window="subModalOpen = false"
            class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4"
        >
            <div class="bg-surface-800 border border-border-default rounded-2xl p-6 max-w-md w-full">
                <h3 class="text-lg font-bold text-text-primary mb-4" x-text="labels.queueSub"></h3>

                <label class="block text-xs text-text-muted mb-1" x-text="labels.pickPlayerOut"></label>
                <select x-model="subPlayerOut" class="w-full mb-3 px-3 py-2 bg-surface-700 border border-border-default rounded-lg text-text-primary">
                    <option value="">—</option>
                    <template x-for="player in myOnPitch" :key="player.id">
                        <option :value="player.id" x-text="player.name + ' (' + player.overall_score + ')'"></option>
                    </template>
                </select>

                <label class="block text-xs text-text-muted mb-1" x-text="labels.pickPlayerIn"></label>
                <select x-model="subPlayerIn" class="w-full mb-4 px-3 py-2 bg-surface-700 border border-border-default rounded-lg text-text-primary">
                    <option value="">—</option>
                    <template x-for="player in myBench" :key="player.id">
                        <option :value="player.id" x-text="player.name + ' (' + player.overall_score + ')'"></option>
                    </template>
                </select>

                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="subModalOpen = false"
                        class="flex-1 px-4 py-2 bg-surface-700 text-text-primary rounded-lg hover:bg-surface-600 transition"
                    >Cancel</button>
                    <button
                        type="button"
                        @click="confirmSub()"
                        :disabled="!subPlayerOut || !subPlayerIn"
                        class="flex-1 px-4 py-2 bg-accent-blue text-white rounded-lg font-semibold hover:bg-accent-blue/80 disabled:opacity-40 transition"
                    >OK</button>
                </div>

                <div x-show="subError" class="mt-3 text-sm text-accent-red" x-text="subError"></div>
            </div>
        </div>

        <!-- Full-time overlay -->
        <div
            x-show="phase === 'finished'"
            x-transition.opacity
            class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
        >
            <div class="bg-surface-800 border border-border-default rounded-2xl p-8 max-w-md w-full text-center">
                <h2 class="text-2xl font-bold text-text-primary mb-1" x-text="labels.fulltime"></h2>
                <div class="text-5xl font-bold tabular-nums my-4">
                    <span x-text="homeScore"></span>
                    <span class="text-text-muted">-</span>
                    <span x-text="awayScore"></span>
                </div>
                <a
                    href="{{ route('live.duel.entry') }}"
                    class="inline-block px-6 py-3 bg-accent-blue text-white rounded-lg font-semibold hover:bg-accent-blue/80 transition"
                >{{ __('live_duel.create_duel') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>
