<x-app-layout :hide-footer="true">
    <div class="min-h-screen flex items-center justify-center py-8 px-4">
        <div class="w-full max-w-md text-center">

            {{-- Brand --}}
            <div class="flex justify-center mb-8">
                <x-application-logo />
            </div>

            {{-- Title --}}
            <h1 class="font-heading text-2xl md:text-3xl font-bold uppercase tracking-wide text-text-primary leading-tight mb-6">
                {{ __('migration.completed_title') }}
            </h1>

            {{-- Check icon --}}
            <div class="flex justify-center mb-6">
                <div class="w-16 h-16 rounded-full bg-accent-green/15 flex items-center justify-center">
                    <svg class="w-9 h-9 text-accent-green" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>

            {{-- Body --}}
            <p class="text-text-secondary mb-8">
                {{ __('migration.completed_body', ['url' => $destinationUrl]) }}
            </p>

            {{-- CTA --}}
            @if($destinationUrl !== '')
                <a href="{{ $destinationUrl }}"
                   class="inline-flex items-center justify-center px-6 py-3 bg-accent-green hover:bg-green-600 text-white font-semibold rounded-lg transition shadow-lg shadow-accent-green/20">
                    {{ __('migration.completed_cta') }}
                </a>
            @endif

        </div>
    </div>
</x-app-layout>
