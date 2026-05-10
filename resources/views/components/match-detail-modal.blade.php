<div x-data="{
    loading: false,
    content: '',
    loadMatch(url) {
        this.content = '';
        this.loading = true;
        this.$dispatch('open-modal', 'match-detail');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => { this.content = html; this.loading = false; })
            .catch(() => { this.loading = false; });
    }
}" x-on:show-match-detail.window="loadMatch($event.detail)">

    <x-modal name="match-detail" maxWidth="2xl">
        <div class="relative">
            <button type="button"
                @click="$dispatch('close-modal', 'match-detail')"
                class="absolute top-3 right-3 z-10 w-8 h-8 rounded-full flex items-center justify-center bg-surface-700/80 hover:bg-surface-600 text-text-secondary hover:text-text-primary transition-colors"
                aria-label="{{ __('app.close') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div x-show="loading" class="p-12 flex items-center justify-center">
                <svg class="animate-spin h-8 w-8 text-text-secondary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <div x-show="!loading" x-html="content"></div>
        </div>
    </x-modal>
</div>
