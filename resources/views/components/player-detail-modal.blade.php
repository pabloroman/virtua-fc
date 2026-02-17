<div x-data="{
    loading: false,
    content: '',
    loadPlayer(url) {
        this.content = '';
        this.loading = true;
        this.$dispatch('open-modal', 'player-detail');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => { this.content = html; this.loading = false; })
            .catch(() => { this.loading = false; });
    }
}" x-on:show-player-detail.window="loadPlayer($event.detail)">

    <x-modal name="player-detail" maxWidth="3xl">
        {{-- Loading spinner --}}
        <div x-show="loading" class="p-8 flex items-center justify-center">
            <svg class="animate-spin h-8 w-8 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        {{-- Server-rendered content --}}
        <div x-show="!loading" x-html="content"></div>
    </x-modal>
</div>
