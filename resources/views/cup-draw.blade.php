@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition $competition */
/** @var string $roundName */
/** @var bool $twoLegged */
/** @var Illuminate\Support\Collection $ties */
/** @var int|null $playerIndex */
@endphp

{{--
    Draw ceremony. The pairings were already decided and written when the round
    was drawn; this only reveals them one at a time. Each tie is a ghost slot
    that swaps for the real card — <x-cup-tie-card-ghost> matches
    <x-cup-tie-card>'s geometry exactly, so nothing shifts as they land.
--}}
<x-app-layout :hide-footer="true">
    <div class="min-h-screen flex items-start md:items-center justify-center pt-16 md:pt-0 pb-8"
         x-data="drawCeremony({{ $ties->count() }})"
         x-init="start()"
         @click="skip()">
        <div class="w-full max-w-md px-4">
            {{-- Competition & round --}}
            <div class="text-center mb-6">
                <x-competition-pill :competition="$competition" class="justify-center mb-2" />
                <h1 class="text-lg md:text-2xl font-semibold text-text-primary">{{ __('cup.draw_title') }}</h1>
                <p class="text-sm text-text-muted mt-1">
                    {{ __($roundName) }}@if($twoLegged) &middot; {{ __('cup.two_legged_tie') }}@endif
                </p>
            </div>

            {{-- The bowl --}}
            <div class="space-y-2">
                @foreach($ties as $i => $tie)
                    <div data-tie class="scroll-mt-24">
                        <div x-show="revealed <= {{ $i }}">
                            <x-cup-tie-card-ghost />
                        </div>
                        <div x-show="revealed > {{ $i }}"
                             x-cloak
                             style="display: none"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100">
                            <x-cup-tie-card :tie="$tie" :player-team-id="$game->team_id" />
                            @if($i === $playerIndex)
                                <p class="mt-1 text-[11px] font-semibold text-accent-blue text-center">{{ __('cup.draw_your_tie') }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Skip is a real button so the reveal is dismissable from the
                 keyboard; the click handler on the container is the tap-anywhere
                 shortcut. Continue only appears once every tie has landed, so the
                 two can't fight over the same tap. --}}
            <div class="mt-6 flex justify-center">
                <x-secondary-button x-show="revealed < {{ $ties->count() }}" @click.stop="skip()">
                    {{ __('game.live_skip') }}
                </x-secondary-button>

                <form method="POST"
                      action="{{ route('game.cup-draw.dismiss', $game->id) }}"
                      x-show="revealed >= {{ $ties->count() }}"
                      x-cloak
                      style="display: none"
                      @click.stop>
                    @csrf
                    <x-primary-button>{{ __('app.continue') }}</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
