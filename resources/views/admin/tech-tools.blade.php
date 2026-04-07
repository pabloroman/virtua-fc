<x-admin-layout>
    <h1 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-6">
        {{ __('admin.tech_tools_title') }}
    </h1>

    {{-- Quick Links --}}
    <x-section-card :title="__('admin.quick_links')" class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 p-4">
            <a href="/horizon" target="_blank"
               class="flex items-center gap-3 px-4 py-3 bg-surface-700 border border-border-default rounded-lg hover:border-accent-blue/40 transition-colors group">
                <svg class="w-5 h-5 text-text-muted group-hover:text-accent-blue transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7" />
                </svg>
                <div>
                    <div class="text-sm font-medium text-text-primary">Horizon</div>
                    <div class="text-xs text-text-muted">{{ __('admin.env_queue') }}</div>
                </div>
                <svg class="w-4 h-4 text-text-faint ml-auto shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
            </a>

            <a href="/pulse" target="_blank"
               class="flex items-center gap-3 px-4 py-3 bg-surface-700 border border-border-default rounded-lg hover:border-accent-blue/40 transition-colors group">
                <svg class="w-5 h-5 text-text-muted group-hover:text-accent-blue transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
                <div>
                    <div class="text-sm font-medium text-text-primary">Pulse</div>
                    <div class="text-xs text-text-muted">App monitoring</div>
                </div>
                <svg class="w-4 h-4 text-text-faint ml-auto shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
            </a>

            <a href="https://cloud.laravel.com/nurisoft/virtuafc/main/metrics" target="_blank"
               class="flex items-center gap-3 px-4 py-3 bg-surface-700 border border-border-default rounded-lg hover:border-accent-blue/40 transition-colors group">
                <svg class="w-5 h-5 text-text-muted group-hover:text-accent-blue transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z" />
                </svg>
                <div>
                    <div class="text-sm font-medium text-text-primary">Laravel Cloud</div>
                    <div class="text-xs text-text-muted">Infrastructure</div>
                </div>
                <svg class="w-4 h-4 text-text-faint ml-auto shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
            </a>
        </div>
    </x-section-card>

    {{-- Impersonate by Game --}}
    <x-section-card :title="__('admin.impersonate_by_game')" class="mb-6">
        <div class="p-4" x-data="{
            gameId: '',
            loading: false,
            result: null,
            error: false,
            async lookup() {
                if (!this.gameId.trim()) return;
                this.loading = true;
                this.error = false;
                this.result = null;
                try {
                    const res = await fetch(`{{ route('admin.lookup-game') }}?game_id=${encodeURIComponent(this.gameId.trim())}`);
                    const data = await res.json();
                    if (data.found) {
                        this.result = data;
                    } else {
                        this.error = true;
                    }
                } catch (e) {
                    this.error = true;
                }
                this.loading = false;
            }
        }">
            <div class="flex flex-col sm:flex-row gap-3">
                <input
                    type="text"
                    x-model="gameId"
                    @keydown.enter.prevent="lookup()"
                    placeholder="{{ __('admin.game_id_placeholder') }}"
                    class="flex-1 bg-surface-700 border border-border-default rounded-lg text-sm text-text-primary placeholder-text-faint px-4 py-2.5 font-mono min-h-[44px] focus:outline-hidden focus:border-accent-blue/50"
                />
                <button
                    @click="lookup()"
                    :disabled="loading || !gameId.trim()"
                    class="px-5 py-2.5 bg-accent-blue text-white text-sm font-semibold rounded-lg min-h-[44px] hover:bg-accent-blue/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
                >
                    <span x-show="!loading">{{ __('admin.lookup') }}</span>
                    <span x-show="loading" x-cloak>...</span>
                </button>
            </div>

            {{-- Error --}}
            <div x-show="error" x-cloak class="mt-3 px-4 py-3 bg-red-500/10 border border-red-500/20 rounded-lg text-sm text-red-400">
                {{ __('admin.game_not_found') }}
            </div>

            {{-- Result --}}
            <div x-show="result" x-cloak class="mt-4">
                <div class="bg-surface-700 border border-border-default rounded-lg overflow-hidden">
                    <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-border-default">
                        <div class="p-3 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-text-muted uppercase tracking-wider">{{ __('admin.game_user') }}</span>
                                <span class="text-sm text-text-primary" x-text="result?.user_name"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-text-muted uppercase tracking-wider">{{ __('admin.email') }}</span>
                                <span class="text-sm text-text-secondary" x-text="result?.user_email"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-text-muted uppercase tracking-wider">{{ __('admin.game_team') }}</span>
                                <span class="text-sm text-text-primary" x-text="result?.team_name ?? '—'"></span>
                            </div>
                        </div>
                        <div class="p-3 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-text-muted uppercase tracking-wider">{{ __('admin.game_mode_label') }}</span>
                                <span class="text-sm text-text-primary" x-text="result?.game_mode"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-text-muted uppercase tracking-wider">{{ __('admin.game_season') }}</span>
                                <span class="text-sm text-text-primary" x-text="result?.season"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-text-muted uppercase tracking-wider">{{ __('admin.game_current_date') }}</span>
                                <span class="text-sm text-text-primary" x-text="result?.current_date ?? '—'"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-text-muted uppercase tracking-wider">Status</span>
                                <span class="text-sm" :class="result?.setup_completed ? 'text-accent-green' : 'text-accent-gold'"
                                      x-text="result?.setup_completed ? '{{ __('admin.game_setup_complete') }}' : '{{ __('admin.game_setup_incomplete') }}'">
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="px-3 py-3 border-t border-border-default">
                        <form method="POST" action="{{ route('admin.impersonate-by-game') }}">
                            @csrf
                            <input type="hidden" name="game_id" :value="result?.game_id" />
                            <button type="submit"
                                    class="w-full px-4 py-2.5 bg-accent-blue text-white text-sm font-semibold rounded-lg min-h-[44px] hover:bg-accent-blue/80 transition-colors">
                                {{ __('admin.impersonate') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </x-section-card>

    {{-- Environment --}}
    <x-section-card :title="__('admin.environment')">
        <div class="divide-y divide-border-default">
            <div class="flex items-center justify-between px-5 py-3">
                <span class="text-sm text-text-muted">{{ __('admin.env_app') }}</span>
                <span class="text-sm font-medium text-text-primary font-mono">{{ $appEnv }}</span>
            </div>
            <div class="flex items-center justify-between px-5 py-3">
                <span class="text-sm text-text-muted">{{ __('admin.env_php') }}</span>
                <span class="text-sm font-medium text-text-primary font-mono">{{ $phpVersion }}</span>
            </div>
            <div class="flex items-center justify-between px-5 py-3">
                <span class="text-sm text-text-muted">{{ __('admin.env_laravel') }}</span>
                <span class="text-sm font-medium text-text-primary font-mono">{{ $laravelVersion }}</span>
            </div>
            <div class="flex items-center justify-between px-5 py-3">
                <span class="text-sm text-text-muted">{{ __('admin.env_queue') }}</span>
                <span class="text-sm font-medium text-text-primary font-mono">{{ $queueConnection }}</span>
            </div>
            <div class="flex items-center justify-between px-5 py-3">
                <span class="text-sm text-text-muted">{{ __('admin.env_failed_jobs') }}</span>
                <a href="/horizon/failed" target="_blank"
                   class="text-sm font-medium font-mono {{ $failedJobsCount > 0 ? 'text-red-400 hover:text-red-300' : 'text-accent-green hover:text-accent-green/80' }} transition-colors">
                    {{ $failedJobsCount }}
                </a>
            </div>
        </div>
    </x-section-card>
</x-admin-layout>
