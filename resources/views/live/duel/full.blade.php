<x-app-layout>
    <div class="max-w-2xl mx-auto p-6 md:p-10">
        <div class="bg-surface-800 border border-border-default rounded-2xl p-8 text-center">
            <div class="text-6xl mb-4">🚫</div>
            <h1 class="text-2xl font-bold text-text-primary mb-2">{{ __('live_duel.match_full') }}</h1>
            <p class="text-text-muted mb-6">
                {{ $reason ?? __('live_duel.match_full_description') }}
            </p>
            <a
                href="{{ route('live.duel.entry') }}"
                class="inline-block px-6 py-3 bg-accent-blue text-white rounded-lg font-semibold hover:bg-accent-blue/80 transition"
            >
                {{ __('live_duel.create_duel') }}
            </a>
        </div>
    </div>
</x-app-layout>
