@php
/** @var App\Models\Game $game */
/** @var \Illuminate\Support\Collection $candidates */
/** @var array $slots */
@endphp

<x-app-layout :hide-footer="true">
    <div class="min-h-screen py-12 md:py-16">
        <div class="max-w-2xl mx-auto px-4 sm:px-6">

            {{-- Hero --}}
            <div class="text-center mb-8">
                <x-team-crest :team="$game->team" class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-4 drop-shadow-lg" />
                <p class="text-xs font-semibold text-text-muted uppercase tracking-widest mb-1">{{ __('game.preseason_setup_subtitle') }}</p>
                <h1 class="font-heading text-3xl md:text-4xl font-bold text-text-primary">{{ __('game.preseason_setup_title') }}</h1>
                <p class="text-sm text-text-secondary mt-3">{{ __('game.preseason_setup_intro') }}</p>
            </div>

            <x-flash-message type="error" :message="session('error')" class="mb-6" />

            <form action="{{ route('game.preseason-setup.save', $game->id) }}" method="POST">
                @csrf

                @if($candidates->isEmpty())
                    <x-status-banner color="blue" :title="__('game.preseason_setup_title')" :description="__('game.preseason_setup_no_opponents')" class="mb-6" />
                @else
                    <div class="space-y-4 mb-8">
                        @foreach($slots as $index => $date)
                            <div class="bg-surface-800 border border-border-default rounded-xl p-4 md:p-5"
                                 x-data="{ isHome: true, hasOpponent: false }">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-[10px] text-text-muted uppercase tracking-wider font-semibold">
                                        {{ __('game.preseason_setup_fixture', ['number' => $index + 1]) }}
                                    </span>
                                    <span class="text-sm font-semibold text-text-secondary">
                                        {{ $date->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    {{-- Opponent picker --}}
                                    <x-select-input
                                        name="slots[{{ $index }}][team_id]"
                                        class="w-full"
                                        x-on:change="hasOpponent = $event.target.value !== ''">
                                        <option value="">{{ __('game.preseason_setup_no_match') }}</option>
                                        @foreach($candidates as $team)
                                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                                        @endforeach
                                    </x-select-input>

                                    {{-- Home / away toggle --}}
                                    <div class="flex items-center gap-2" x-show="hasOpponent" x-cloak>
                                        <input type="hidden" name="slots[{{ $index }}][is_home]" :value="isHome ? '1' : '0'">
                                        <button type="button" @click="isHome = true"
                                            class="flex-1 px-3 py-2 rounded-lg text-sm font-semibold border transition-colors"
                                            :class="isHome ? 'bg-accent-blue/15 border-accent-blue/50 text-accent-blue' : 'bg-surface-700 border-border-default text-text-secondary'">
                                            {{ __('game.preseason_setup_home') }}
                                        </button>
                                        <button type="button" @click="isHome = false"
                                            class="flex-1 px-3 py-2 rounded-lg text-sm font-semibold border transition-colors"
                                            :class="!isHome ? 'bg-accent-blue/15 border-accent-blue/50 text-accent-blue' : 'bg-surface-700 border-border-default text-text-secondary'">
                                            {{ __('game.preseason_setup_away') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <x-primary-button class="w-full">{{ __('game.preseason_setup_confirm') }}</x-primary-button>
            </form>

        </div>
    </div>
</x-app-layout>
