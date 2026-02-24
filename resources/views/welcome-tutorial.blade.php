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
                <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">{{ __('game.welcome_team') }}</h1>
                <p class="text-lg text-slate-400">{{ __('game.welcome_appointed', ['team_de' => $game->team->nameWithDe()]) }}</p>
            </div>

            {{-- How it works --}}
            <div class="text-center mb-6">
                <h2 class="text-xl md:text-2xl font-bold text-white">{{ __('game.welcome_how_it_works') }}</h2>
            </div>

            <div class="space-y-4 mb-10">
                {{-- Matchday --}}
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-red-500/20 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-white">{{ __('game.welcome_step_matches') }}</h3>
                        <p class="text-sm text-slate-400 mt-1">{{ __('game.welcome_step_matches_desc') }}</p>
                    </div>
                </div>

                {{-- Squad --}}
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-sky-500/20 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-white">{{ __('game.welcome_step_squad') }}</h3>
                        <p class="text-sm text-slate-400 mt-1">{{ __('game.welcome_step_squad_desc') }}</p>
                    </div>
                </div>

                {{-- Transfers --}}
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-white">{{ __('game.welcome_step_transfers') }}</h3>
                        <p class="text-sm text-slate-400 mt-1">{{ __('game.welcome_step_transfers_desc') }}</p>
                    </div>
                </div>

                {{-- Finances --}}
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-amber-500/20 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-white">{{ __('game.welcome_step_finances') }}</h3>
                        <p class="text-sm text-slate-400 mt-1">{{ __('game.welcome_step_finances_desc') }}</p>
                    </div>
                </div>

                {{-- Auto-save --}}
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-5 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-teal-500/20 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-white">{{ __('game.welcome_step_autosave') }}</h3>
                        <p class="text-sm text-slate-400 mt-1">{{ __('game.welcome_step_autosave_desc') }}</p>
                    </div>
                </div>
            </div>

            {{-- CTA --}}
            <form method="POST" action="{{ route('game.welcome.complete', $game->id) }}">
                @csrf
                <div class="flex justify-center">
                    <button type="submit" class="inline-flex items-center gap-2 px-8 py-3 min-h-[44px] bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl transition-colors duration-200 text-lg">
                        {{ __('game.welcome_start_journey') }}
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </button>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
