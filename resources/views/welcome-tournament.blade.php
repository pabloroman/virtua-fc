@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition|null $competition */
@endphp

<x-app-layout :hide-footer="true">
    <div class="min-h-screen py-8 md:py-16">
        <div class="max-w-2xl mx-auto px-4 sm:px-6">

            {{-- Welcome Header --}}
            <div class="text-center mb-10">
                <x-team-crest :team="$game->team" class="w-24 h-24 md:w-32 md:h-32 mx-auto mb-6 drop-shadow-lg" />
                <h1 class="font-heading text-3xl md:text-4xl font-bold uppercase tracking-wide text-text-primary mb-2">{{ __('game.tournament_welcome_title', ['team' => $game->team->name]) }}</h1>
                <p class="text-lg text-text-secondary">{{ __('game.tournament_welcome_subtitle') }}</p>
            </div>

            {{-- How it works --}}
            <div class="text-center mb-6">
                <h2 class="font-heading text-xl md:text-2xl font-bold uppercase tracking-wide text-text-primary">{{ __('game.welcome_how_it_works') }}</h2>
            </div>

            <div class="space-y-4 mb-10">
                {{-- Squad selection --}}
                <div class="bg-surface-800 border border-border-default rounded-xl p-5 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-accent-green/20 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-accent-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-text-primary">{{ __('game.tournament_welcome_step_squad') }}</h3>
                        <p class="text-sm text-text-secondary mt-1">{{ __('game.tournament_welcome_step_squad_desc') }}</p>
                    </div>
                </div>

                {{-- Tournament format --}}
                <div class="bg-surface-800 border border-border-default rounded-xl p-5 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-accent-gold/20 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-accent-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-text-primary">{{ __('game.tournament_welcome_step_format') }}</h3>
                        <p class="text-sm text-text-secondary mt-1">{{ __('game.tournament_welcome_step_format_desc') }}</p>
                    </div>
                </div>

                {{-- Lineup --}}
                <div class="bg-surface-800 border border-border-default rounded-xl p-5 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-accent-blue/20 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-accent-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-text-primary">{{ __('game.tournament_welcome_step_lineup') }}</h3>
                        <p class="text-sm text-text-secondary mt-1">{{ __('game.tournament_welcome_step_lineup_desc') }}</p>
                    </div>
                </div>

                {{-- One shot at glory --}}
                <div class="bg-surface-800 border border-border-default rounded-xl p-5 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-accent-red/20 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-accent-red" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-text-primary">{{ __('game.tournament_welcome_step_glory') }}</h3>
                        <p class="text-sm text-text-secondary mt-1">{{ __('game.tournament_welcome_step_glory_desc') }}</p>
                    </div>
                </div>
            </div>

            {{-- CTA --}}
            <form method="POST" action="{{ route('game.welcome.complete', $game->id) }}">
                @csrf
                <div class="flex justify-center">
                    <x-primary-button type="submit" color="teal">
                        {{ __('game.tournament_welcome_start') }}
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </x-primary-button>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
