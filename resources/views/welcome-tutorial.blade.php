@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition|null $competition */
@endphp

<x-app-layout>
    <div class="min-h-screen py-8 md:py-16" x-data="{ step: 1 }">
        <div class="max-w-2xl mx-auto px-4 sm:px-6">

            {{-- Step 1: Welcome --}}
            <div x-show="step === 1" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="text-center mb-10">
                    <img src="{{ $game->team->image }}" alt="{{ $game->team->name }}" class="w-24 h-24 md:w-32 md:h-32 mx-auto mb-6 drop-shadow-lg">
                    <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">{{ __('game.welcome_new_manager') }}</h1>
                    <p class="text-lg text-slate-400">{{ __('game.welcome_appointed', ['team' => $game->team->name]) }}</p>
                </div>

                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-6 md:p-8 mb-8">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-xl bg-teal-500/20 flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-white">{{ $game->player_name }}</h2>
                            <p class="text-sm text-slate-400">{{ __('game.welcome_manager_of', ['team' => $game->team->name]) }}</p>
                        </div>
                    </div>

                    <div class="border-t border-white/10 pt-4 mt-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs text-slate-500 uppercase tracking-wide">{{ __('game.season') }}</div>
                                <div class="text-white font-semibold mt-1">{{ $game->formatted_season }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500 uppercase tracking-wide">{{ __('game.welcome_league') }}</div>
                                <div class="text-white font-semibold mt-1">{{ $competition?->name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center">
                    <button @click="step = 2" class="inline-flex items-center gap-2 px-8 py-3 min-h-[44px] bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl transition-colors duration-200">
                        {{ __('app.continue') }}
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Step 2: How it works --}}
            <div x-show="step === 2" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="text-center mb-8">
                    <h2 class="text-2xl md:text-3xl font-bold text-white mb-2">{{ __('game.welcome_how_it_works') }}</h2>
                    <p class="text-slate-400">{{ __('game.welcome_how_subtitle') }}</p>
                </div>

                <div class="space-y-4 mb-8">
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
                </div>

                <div class="flex justify-center">
                    <button @click="step = 3" class="inline-flex items-center gap-2 px-8 py-3 min-h-[44px] bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl transition-colors duration-200">
                        {{ __('app.continue') }}
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Step 3: Season structure --}}
            <div x-show="step === 3" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="text-center mb-8">
                    <h2 class="text-2xl md:text-3xl font-bold text-white mb-2">{{ __('game.welcome_season_structure') }}</h2>
                    <p class="text-slate-400">{{ __('game.welcome_season_structure_subtitle') }}</p>
                </div>

                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-6 md:p-8 mb-8">
                    <div class="relative">
                        {{-- Timeline --}}
                        <div class="absolute left-5 top-0 bottom-0 w-px bg-white/10"></div>

                        <div class="space-y-8">
                            {{-- Budget allocation --}}
                            <div class="relative flex items-start gap-4 pl-2">
                                <div class="w-7 h-7 rounded-full bg-amber-500/30 border-2 border-amber-500 flex items-center justify-center shrink-0 z-10">
                                    <div class="w-2 h-2 rounded-full bg-amber-400"></div>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-amber-400 text-sm uppercase tracking-wide">{{ __('game.welcome_phase_preseason') }}</h3>
                                    <p class="text-sm text-slate-300 mt-1">{{ __('game.welcome_phase_preseason_desc') }}</p>
                                </div>
                            </div>

                            {{-- Summer window --}}
                            <div class="relative flex items-start gap-4 pl-2">
                                <div class="w-7 h-7 rounded-full bg-emerald-500/30 border-2 border-emerald-500 flex items-center justify-center shrink-0 z-10">
                                    <div class="w-2 h-2 rounded-full bg-emerald-400"></div>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-emerald-400 text-sm uppercase tracking-wide">{{ __('game.welcome_phase_summer') }}</h3>
                                    <p class="text-sm text-slate-300 mt-1">{{ __('game.welcome_phase_summer_desc') }}</p>
                                </div>
                            </div>

                            {{-- Matchdays --}}
                            <div class="relative flex items-start gap-4 pl-2">
                                <div class="w-7 h-7 rounded-full bg-red-500/30 border-2 border-red-500 flex items-center justify-center shrink-0 z-10">
                                    <div class="w-2 h-2 rounded-full bg-red-400"></div>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-red-400 text-sm uppercase tracking-wide">{{ __('game.welcome_phase_season') }}</h3>
                                    <p class="text-sm text-slate-300 mt-1">{{ __('game.welcome_phase_season_desc') }}</p>
                                </div>
                            </div>

                            {{-- Season end --}}
                            <div class="relative flex items-start gap-4 pl-2">
                                <div class="w-7 h-7 rounded-full bg-sky-500/30 border-2 border-sky-500 flex items-center justify-center shrink-0 z-10">
                                    <div class="w-2 h-2 rounded-full bg-sky-400"></div>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-sky-400 text-sm uppercase tracking-wide">{{ __('game.welcome_phase_end') }}</h3>
                                    <p class="text-sm text-slate-300 mt-1">{{ __('game.welcome_phase_end_desc') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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

            {{-- Step indicators --}}
            <div class="flex justify-center gap-2 mt-8">
                <template x-for="i in 3" :key="i">
                    <button @click="step = i" class="w-2 h-2 rounded-full transition-all duration-200" :class="step === i ? 'bg-teal-400 w-6' : 'bg-white/20 hover:bg-white/40'"></button>
                </template>
            </div>

        </div>
    </div>
</x-app-layout>
