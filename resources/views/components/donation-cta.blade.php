<div class="max-w-xl mx-auto text-center">
    <div class="bg-pink-700 rounded-xl px-6 py-6 md:px-8 md:py-8">
        {{-- Heart icon --}}
        <div class="inline-flex items-center justify-center w-10 h-10 md:w-12 md:h-12 rounded-full bg-white/15 mb-4">
            <svg class="w-5 h-5 md:w-6 md:h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
            </svg>
        </div>

        <h3 class="font-heading text-lg md:text-lx font-bold text-white mb-2">
            {{ __('app.donation_title') }}
        </h3>

        <p class="text-sm text-white/80 mb-5 leading-relaxed max-w-md mx-auto">
            {{ __('app.donation_description') }}
        </p>

        <a href="https://ko-fi.com/virtuafc"
           target="_blank"
           rel="noopener noreferrer"
           class="inline-flex items-center justify-center gap-2 px-6 py-2.5 min-h-[44px] text-sm font-semibold rounded-lg bg-white/20 text-white border border-white/30 hover:bg-white/30 hover:border-white/40 transition ease-in-out duration-150"
        >
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
            </svg>
            {{ __('app.donation_button') }}
        </a>
    </div>
</div>
