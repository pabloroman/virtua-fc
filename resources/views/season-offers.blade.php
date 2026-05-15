@php
/** @var App\Models\Game $game */
/** @var \Illuminate\Support\Collection $jobOffers */
/** @var \App\Models\ManagerJobOffer|null $pendingTeamSwitchOffer */
/** @var bool $firedAtSeasonEnd */

$fired = $firedAtSeasonEnd;
$hasAccepted = (bool) $pendingTeamSwitchOffer;
$hasPending = $jobOffers->isNotEmpty();
$nextSeasonLabel = \App\Models\Game::formatSeason((string) ((int) $game->season + 1));
@endphp

<x-app-layout :hide-footer="true">
    <div class="min-h-screen py-6 md:py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <header class="text-center mb-8">
                <h1 class="font-heading text-2xl md:text-3xl font-bold text-text-primary uppercase tracking-wide">
                    {{ __($fired ? 'manager.post_firing_offers_title' : 'manager.season_offers_title') }}
                </h1>
            </header>

            @if($hasAccepted)
                <x-status-banner
                    color="green"
                    :title="__('manager.offer_accepted_banner_title')"
                    :description="__('manager.offer_accepted_banner_message')"
                    class="mb-6"
                >
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </x-slot:icon>
                </x-status-banner>
            @elseif($fired)
                <x-status-banner
                    color="red"
                    :title="__('manager.dismissed_banner_title', ['team_el' => $game->team->nameWithEl()])"
                    :description="__('manager.dismissed_banner_message')"
                    class="mb-6"
                >
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                    </x-slot:icon>
                </x-status-banner>
            @elseif($hasPending)
                <x-status-banner
                    color="green"
                    :title="__('manager.offers_celebration_title')"
                    :description="__('manager.offers_celebration_message')"
                    class="mb-6"
                >
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                        </svg>
                    </x-slot:icon>
                </x-status-banner>
            @else
                <x-status-banner
                    color="blue"
                    :title="__('manager.no_offers_title')"
                    :description="__('manager.no_offers_intro', ['team_de' => $game->team->nameWithDe()])"
                    class="mb-6"
                >
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                        </svg>
                    </x-slot:icon>
                </x-status-banner>
            @endif

            @if($hasAccepted)
                {{-- An accepted-but-not-yet-applied switch shouldn't normally
                     persist on this page: the Accept action triggers the
                     pipeline on the same request. This branch handles the
                     edge case of a user navigating back here after acceptance
                     but before the pipeline has finished. --}}
                <x-section-card :title="__('manager.next_chapter_title')" class="mb-6">
                    <div class="p-5 flex items-center gap-4">
                        <div class="shrink-0 w-12 h-12 flex items-center justify-center bg-surface-700 rounded-lg overflow-hidden">
                            <x-team-crest :team="$pendingTeamSwitchOffer->team" class="w-10 h-10 object-contain" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-heading text-base font-semibold text-text-primary truncate">
                                {{ $pendingTeamSwitchOffer->team->name }}
                            </div>
                            <div class="mt-1 text-xs text-text-secondary">
                                {{ __('manager.starts_next_season_at', ['season' => $nextSeasonLabel]) }}
                            </div>
                        </div>
                    </div>
                </x-section-card>
            @elseif($hasPending)
                <x-section-card :title="__($fired ? 'manager.post_firing_offers_title' : 'manager.season_offers_title')" class="mb-6">
                    <div class="p-5">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach($jobOffers as $offer)
                                <x-job-offer-card
                                    :offer="$offer"
                                    :last-season-position="$positionsByOfferId[$offer->id] ?? null"
                                    accept-route="game.job-offers.accept"
                                    :accept-params="['gameId' => $game->id, 'offerId' => $offer->id]"
                                />
                            @endforeach
                        </div>

                        @if(!$fired)
                            <div class="mt-6 flex justify-center">
                                <form method="POST" action="{{ route('game.job-offers.decline', $game->id) }}" x-data="{ loading: false }" @submit="loading = true">
                                    @csrf
                                    <x-primary-button-spin color="red" class="px-8 py-4 text-lg font-bold">
                                        {{ __('manager.stay_at_current_club', ['team_el' => $game->team->nameWithEl()]) }}
                                    </x-primary-button-spin>
                                </form>
                            </div>
                        @endif
                    </div>
                </x-section-card>
            @else
                {{-- No offers were generated (e.g. "below" grade with no
                     interest from other clubs and not fired). The blue
                     banner above carries the explanatory copy; the section
                     card here just hosts the CTA. --}}
                <x-section-card :title="__('manager.next_chapter_title')" class="mb-6">
                    <div class="p-5 flex justify-center">
                        <form method="POST" action="{{ route('game.job-offers.decline', $game->id) }}" x-data="{ loading: false }" @submit="loading = true">
                            @csrf
                            <x-primary-button-spin color="red" class="px-8 py-4 text-lg font-bold">
                                {{ __('season.start_new_season', ['season' => $nextSeasonLabel]) }}
                            </x-primary-button-spin>
                        </form>
                    </div>
                </x-section-card>
            @endif

        </div>
    </div>
</x-app-layout>
