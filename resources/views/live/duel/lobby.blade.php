<x-app-layout>
    <div
        x-data="liveDuelLobby({
            sessionId: @js($session->id),
            viewerRole: @js($viewerRole),
            shareUrl: @js(url()->current()),
            reverbKey: @js($reverbKey),
            reverbHost: @js($reverbHost),
            reverbPort: @js($reverbPort),
            reverbScheme: @js($reverbScheme),
            hostIsoCode: @js($session->host_iso_code),
            guestIsoCode: @js($session->guest_iso_code),
            hostName: @js($session->host->name ?? ''),
            guestName: @js($session->guest?->name ?? ''),
        })"
        class="max-w-3xl mx-auto p-6 md:p-10"
    >
        <div class="bg-surface-800 border border-border-default rounded-2xl p-6 md:p-10 text-center">
            <h1 class="text-2xl md:text-3xl font-bold text-text-primary mb-2">
                {{ __('live_duel.title') }}
            </h1>

            <template x-if="viewerRole === 'host' && !guestIsoCode">
                <div class="space-y-6 mt-6">
                    <p class="text-text-muted">{{ __('live_duel.waiting_for_opponent') }}</p>
                    <div class="text-5xl">{{ collect(App\Http\Actions\LiveDuel\ShowLiveDuelEntry::nationCatalog())->firstWhere('iso', $session->host_iso_code)['flag'] ?? '' }}</div>
                    <p class="text-text-primary font-semibold">
                        @php $hostNation = collect(App\Http\Actions\LiveDuel\ShowLiveDuelEntry::nationCatalog())->firstWhere('iso', $session->host_iso_code); @endphp
                        {{ $hostNation['name'] ?? $session->host_iso_code }}
                    </p>

                    <div class="pt-4 border-t border-border-default">
                        <p class="text-sm text-text-muted mb-2">{{ __('live_duel.share_link') }}</p>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                readonly
                                :value="shareUrl"
                                class="flex-1 px-3 py-2 bg-surface-700 border border-border-default rounded-lg text-sm text-text-primary"
                                @click="$event.target.select()"
                            >
                            <button
                                type="button"
                                @click="copyShareUrl"
                                class="px-4 py-2 bg-accent-blue text-white rounded-lg font-semibold hover:bg-accent-blue/80 transition"
                                x-text="copied ? @js(__('live_duel.link_copied')) : @js(__('live_duel.copy_link'))"
                            ></button>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="viewerRole === 'host' && guestIsoCode">
                <div class="space-y-4 mt-6">
                    <p class="text-text-muted" x-text="guestName ? @js(__('live_duel.opponent_choosing_team', ['name' => ''])) + ' ' + guestName : @js(__('live_duel.opponent_choosing_team', ['name' => 'Opponent']))"></p>
                    <div class="animate-pulse">⚽</div>
                </div>
            </template>

            <template x-if="viewerRole === 'guest'">
                <div class="space-y-4 mt-6">
                    <p class="text-text-muted">{{ __('live_duel.waiting_for_kickoff') }}</p>
                    <div class="animate-pulse text-3xl">⚽</div>
                </div>
            </template>
        </div>
    </div>
</x-app-layout>
