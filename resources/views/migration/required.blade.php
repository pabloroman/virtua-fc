<x-app-layout :hide-footer="true">
    <div class="min-h-screen flex items-center justify-center py-8 px-4">
        <div class="w-full max-w-md text-center">

            {{-- Brand --}}
            <div class="flex justify-center mb-8">
                <x-application-logo />
            </div>

            {{-- Title --}}
            <h1 class="font-heading text-2xl md:text-3xl font-bold uppercase tracking-wide text-text-primary leading-tight mb-6">
                {{ __('migration.required_title') }}
            </h1>

            {{-- Icon --}}
            <div class="flex justify-center mb-6">
                <div class="w-16 h-16 rounded-full bg-accent-blue/15 flex items-center justify-center">
                    <svg class="w-9 h-9 text-accent-blue" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 12h15" />
                    </svg>
                </div>
            </div>

            {{-- Body --}}
            <p class="text-text-secondary mb-8">
                {{ __('migration.required_body') }}
            </p>

            {{-- CTA --}}
            <form method="POST" action="{{ $startUrl }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center justify-center px-6 py-3 bg-accent-green hover:bg-green-600 text-white font-semibold rounded-lg transition shadow-lg shadow-accent-green/20">
                    {{ __('migration.required_cta') }}
                </button>
            </form>

        </div>
    </div>
</x-app-layout>
