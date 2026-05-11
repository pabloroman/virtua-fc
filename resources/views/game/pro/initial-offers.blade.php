@php
/** @var \Illuminate\Support\Collection $offers */
@endphp

<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 pb-12">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                {{ __('manager.initial_offers_title') }}
            </h2>
            <p class="mt-2 text-text-secondary text-sm md:text-base max-w-2xl">
                {{ __('manager.initial_offers_intro') }}
            </p>
        </div>

        <x-input-error :messages="$errors->get('limit')" class="mb-4" />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($offers as $offer)
                <x-job-offer-card
                    :offer="$offer"
                    accept-route="new-game-pro.offers.accept"
                    :accept-params="['offerId' => $offer->id]"
                />
            @endforeach
        </div>

        <div class="mt-8 flex flex-col md:flex-row items-start md:items-center gap-3 md:gap-4">
            <form method="POST" action="{{ route('new-game-pro') }}">
                @csrf
                <button type="submit"
                        class="text-sm text-text-secondary hover:text-text-primary underline underline-offset-2">
                    {{ __('manager.reroll_offers') }}
                </button>
            </form>

            <a href="{{ route('select-team') }}"
               class="text-sm text-text-secondary hover:text-text-primary">
                {{ __('manager.back_to_select_team') }}
            </a>
        </div>
    </div>
</x-app-layout>
