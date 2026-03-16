@php
/** @var App\Models\Game $game */
/** @var App\Models\GameMatch $nextMatch */
$comp = $nextMatch->competition;
@endphp

<x-app-layout :hide-footer="true">
    <div class="min-h-screen flex items-start md:items-center justify-center pt-24 md:pt-0 pb-8" x-data="loadingPoller()" x-init="startPolling()">
        <div class="w-full max-w-md px-4">
            {{-- Competition & Matchday --}}
            <div class="text-center mb-8">
                <x-competition-pill :competition="$comp" class="justify-center mb-2" />
                <h1 class="text-lg md:text-2xl font-bold text-text-primary">
                    @if($nextMatch->round_name)
                        {{ __($nextMatch->round_name) }}
                    @elseif($nextMatch->round_number)
                        {{ __('game.matchday_n', ['number' => $nextMatch->round_number]) }}
                    @endif
                </h1>
                <p class="text-sm text-text-muted mt-1">
                    {{ $nextMatch->homeTeam->stadium_name ?? '' }} &middot; {{ $nextMatch->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
                </p>
            </div>

            {{-- Team Face-Off --}}
            <div class="flex items-center justify-center gap-4 md:gap-8 mb-8">
                {{-- Home Team --}}
                <div class="flex-1 flex flex-col items-center text-center min-w-0">
                    <x-team-crest :team="$nextMatch->homeTeam" class="w-16 h-16 md:w-24 md:h-24 mb-2" />
                    <h4 class="text-sm md:text-base font-bold text-text-primary truncate max-w-full">{{ $nextMatch->homeTeam->short_name ?? $nextMatch->homeTeam->name }}</h4>
                </div>

                {{-- VS Divider --}}
                <div class="shrink-0">
                    <span class="text-lg md:text-2xl font-black text-text-body tracking-tight">{{ __('game.vs') }}</span>
                </div>

                {{-- Away Team --}}
                <div class="flex-1 flex flex-col items-center text-center min-w-0">
                    <x-team-crest :team="$nextMatch->awayTeam" class="w-16 h-16 md:w-24 md:h-24 mb-2" />
                    <h4 class="text-sm md:text-base font-bold text-text-primary truncate max-w-full">{{ $nextMatch->awayTeam->short_name ?? $nextMatch->awayTeam->name }}</h4>
                </div>
            </div>

            {{-- Loading indicator --}}
            <div class="text-center">
                <div class="flex justify-center mb-3">
                    <svg class="animate-spin h-6 w-6 text-accent-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <p class="text-sm text-text-secondary">{{ __('game.simulating_matches_message') }}</p>
            </div>
        </div>
    </div>

    <script>
        function loadingPoller() {
            return {
                startPolling() {
                    const pollUrl = '{{ route("game.setup-status", $game->id) }}';
                    const minDisplayMs = 250;
                    const showTime = Date.now();

                    const interval = setInterval(async () => {
                        try {
                            const response = await fetch(pollUrl);
                            const data = await response.json();
                            if (data.ready) {
                                clearInterval(interval);
                                const elapsed = Date.now() - showTime;
                                const remaining = minDisplayMs - elapsed;
                                if (remaining > 0) {
                                    setTimeout(() => window.location.reload(), remaining);
                                } else {
                                    window.location.reload();
                                }
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
