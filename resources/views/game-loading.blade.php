@php
/** @var App\Models\Game $game */
@endphp

<x-app-layout :hide-footer="true">
    <div class="min-h-screen flex items-center justify-center py-8" x-data="loadingPoller()" x-init="startPolling()">
        <div class="text-center px-4">
            @if($showCrest ?? false)
                {{-- Team Logo --}}
                <x-team-crest :team="$game->team"
                     class="w-24 h-24 mx-auto mb-6 animate-pulse" />
            @endif

            {{-- Spinner --}}
            <div class="flex justify-center mb-6">
                <svg class="animate-spin h-8 w-8 text-accent-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            {{-- Title --}}
            <h1 class="text-2xl font-bold text-text-primary mb-2">{{ $title }}</h1>

            {{-- Description --}}
            <p class="text-text-secondary max-w-md mx-auto">{{ $message }}</p>

            {{-- Progress bar --}}
            <div x-show="progress !== null" x-cloak class="mt-6 max-w-xs mx-auto">
                <div class="w-full bg-surface-700 rounded-full h-2 overflow-hidden">
                    <div class="bg-accent-blue h-2 rounded-full transition-all duration-500 ease-out"
                         :style="`width: ${progress}%`"></div>
                </div>
                <p class="text-text-tertiary text-xs mt-2" x-text="`${progress}%`"></p>
            </div>
        </div>
    </div>

    <script>
        function loadingPoller() {
            return {
                progress: null,
                startPolling() {
                    const pollUrl = '{{ route("game.setup-status", $game->id) }}';

                    const interval = setInterval(async () => {
                        try {
                            const response = await fetch(pollUrl);
                            const data = await response.json();
                            if (data.progress !== null && data.progress !== undefined) {
                                this.progress = data.progress;
                            }
                            if (data.ready) {
                                this.progress = 100;
                                clearInterval(interval);
                                window.location.reload();
                            }
                        } catch (e) {
                            // Silently retry on network error
                        }
                    }, 1000);
                }
            };
        }
    </script>
</x-app-layout>
