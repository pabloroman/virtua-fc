@php
/** @var App\Models\Game $game */
@endphp

<x-app-layout>
    <div class="min-h-screen flex items-center justify-center py-8" x-data="loadingPoller()" x-init="startPolling()">
        <div class="text-center px-4">
            @if($showCrest ?? false)
                {{-- Team Logo --}}
                <x-team-crest :team="$game->team"
                     class="w-24 h-24 mx-auto mb-6 animate-pulse" />
            @endif

            {{-- Spinner --}}
            <div class="flex justify-center mb-6">
                <svg class="animate-spin h-8 w-8 text-teal-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            {{-- Title --}}
            <h1 class="text-2xl font-bold text-white mb-2">{{ $title }}</h1>

            {{-- Description --}}
            <p class="text-slate-400 max-w-md mx-auto">{{ $message }}</p>
        </div>
    </div>

    <script>
        function loadingPoller() {
            return {
                startPolling() {
                    const pollUrl = '{{ route("game.setup-status", $game->id) }}';

                    const interval = setInterval(async () => {
                        try {
                            const response = await fetch(pollUrl);
                            const data = await response.json();
                            if (data.ready) {
                                clearInterval(interval);
                                window.location.reload();
                            }
                        } catch (e) {
                            // Silently retry on network error
                        }
                    }, 2000);
                }
            };
        }
    </script>
</x-app-layout>
