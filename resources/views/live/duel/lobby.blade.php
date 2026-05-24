<x-app-layout>
    @php
        $hostTeam = $session->host_team_id ? \App\Models\Team::find($session->host_team_id) : null;
        $guestTeam = $session->guest_team_id ? \App\Models\Team::find($session->guest_team_id) : null;
        $waitingForGuest = $viewerRole === 'host' && $session->guest_team_id === null;
        $opponentChoosing = $viewerRole === 'host' && $session->guest_team_id !== null;
        $waitingForKickoff = $viewerRole === 'guest' && $session->guest_team_id !== null;
    @endphp

    <div
        x-data="liveDuelLobby({
            sessionId: @js($session->id),
            viewerRole: @js($viewerRole),
            shareUrl: @js(url()->current()),
            reverbKey: @js($reverbKey),
            reverbHost: @js($reverbHost),
            reverbPort: @js($reverbPort),
            reverbScheme: @js($reverbScheme),
        })"
        class="max-w-3xl mx-auto p-6 md:p-10"
    >
        <div class="bg-surface-800 border border-border-default rounded-2xl p-6 md:p-10 text-center">
            <h1 class="text-2xl md:text-3xl font-bold text-text-primary mb-6">
                {{ __('live_duel.title') }}
            </h1>

            @if ($waitingForGuest && $hostTeam)
                <div class="space-y-6">
                    <p class="text-text-muted">{{ __('live_duel.waiting_for_opponent') }}</p>
                    @if ($hostTeam->image)
                        <img src="{{ $hostTeam->image }}" alt="{{ $hostTeam->name }}" class="w-24 h-24 mx-auto object-contain" />
                    @endif
                    <p class="text-text-primary font-semibold text-lg">{{ $hostTeam->name }}</p>

                    <div class="pt-6 border-t border-border-default text-left">
                        <p class="text-sm text-text-muted mb-2">{{ __('live_duel.share_link') }}</p>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                readonly
                                value="{{ url()->current() }}"
                                class="flex-1 px-3 py-2 bg-surface-700 border border-border-default rounded-lg text-sm text-text-primary"
                                @click="$event.target.select()"
                            >
                            <button
                                type="button"
                                @click="copyShareUrl"
                                class="px-4 py-2 bg-accent-blue text-white rounded-lg font-semibold hover:bg-accent-blue/80 transition whitespace-nowrap"
                                x-text="copied ? @js(__('live_duel.link_copied')) : @js(__('live_duel.copy_link'))"
                            ></button>
                        </div>
                    </div>
                </div>
            @elseif ($opponentChoosing)
                <div class="space-y-4">
                    <p class="text-text-muted">
                        {{ __('live_duel.opponent_choosing_team', ['name' => $session->guest->name ?? 'Opponent']) }}
                    </p>
                    <div class="text-3xl animate-pulse">⚽</div>
                </div>
            @elseif ($waitingForKickoff && $guestTeam)
                <div class="space-y-4">
                    <p class="text-text-muted">{{ __('live_duel.waiting_for_kickoff') }}</p>
                    @if ($guestTeam->image)
                        <img src="{{ $guestTeam->image }}" alt="{{ $guestTeam->name }}" class="w-24 h-24 mx-auto object-contain" />
                    @endif
                    <p class="text-text-primary font-semibold text-lg">{{ $guestTeam->name }}</p>
                    <div class="text-3xl animate-pulse">⚽</div>
                </div>
            @else
                <p class="text-text-muted">{{ __('live_duel.connecting') }}</p>
            @endif
        </div>
    </div>
</x-app-layout>
