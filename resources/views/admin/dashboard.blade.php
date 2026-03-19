<x-admin-layout>
    <h1 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-6">
        {{ __('admin.dashboard_title') }}
    </h1>

    {{-- Summary stats --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
            <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.total_users') }}</div>
            <div class="font-heading text-2xl font-bold text-text-primary">{{ number_format($totalUsers) }}</div>
        </div>
        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
            <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.total_games') }}</div>
            <div class="font-heading text-2xl font-bold text-text-primary">{{ number_format($totalGames) }}</div>
        </div>
        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
            <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.new_users_7d') }}</div>
            <div class="font-heading text-2xl font-bold text-accent-primary">{{ number_format($newUsers7d) }}</div>
        </div>
        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
            <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.new_games_7d') }}</div>
            <div class="font-heading text-2xl font-bold text-accent-primary">{{ number_format($newGames7d) }}</div>
        </div>
    </div>

    {{-- Device analytics --}}
    <div class="mb-8">
        <h2 class="font-heading text-lg font-bold uppercase tracking-wider text-text-primary mb-4">
            {{ __('admin.device_analytics') }}
        </h2>

        @if($deviceTotalLogins > 0)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                {{-- Device type breakdown --}}
                <div class="bg-surface-800 border border-border-default rounded-xl p-4">
                    <div class="text-xs text-text-muted uppercase tracking-wider mb-3">{{ __('admin.device_type') }}</div>
                    <div class="space-y-2">
                        @foreach($deviceTypes as $device)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-text-secondary">{{ __('admin.device_' . $device['label']) }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-text-primary">{{ number_format($device['count']) }}</span>
                                    <span class="text-xs text-text-muted">({{ $device['percentage'] }}%)</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3 pt-3 border-t border-border-default">
                        <div class="text-xs text-text-muted">{{ __('admin.last_30_days') }}</div>
                        <div class="mt-1 space-y-1">
                            @foreach($deviceTypesRecent as $device)
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-text-secondary">{{ __('admin.device_' . $device['label']) }}</span>
                                    <span class="text-xs text-text-muted">{{ number_format($device['count']) }} ({{ $device['percentage'] }}%)</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Top browsers --}}
                <div class="bg-surface-800 border border-border-default rounded-xl p-4">
                    <div class="text-xs text-text-muted uppercase tracking-wider mb-3">{{ __('admin.browser') }}</div>
                    <div class="space-y-2">
                        @foreach($topBrowsers as $browser)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-text-secondary">{{ $browser['label'] }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-text-primary">{{ number_format($browser['count']) }}</span>
                                    <span class="text-xs text-text-muted">({{ $browser['percentage'] }}%)</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Top OS --}}
                <div class="bg-surface-800 border border-border-default rounded-xl p-4">
                    <div class="text-xs text-text-muted uppercase tracking-wider mb-3">{{ __('admin.operating_system') }}</div>
                    <div class="space-y-2">
                        @foreach($topOs as $os)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-text-secondary">{{ $os['label'] }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-text-primary">{{ number_format($os['count']) }}</span>
                                    <span class="text-xs text-text-muted">({{ $os['percentage'] }}%)</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="text-xs text-text-muted mb-4">
                {{ __('admin.logins') }}: {{ number_format($deviceTotalLogins) }} {{ __('admin.all_time') }} · {{ number_format($deviceRecentLogins) }} {{ __('admin.last_30_days') }}
            </div>
        @else
            <div class="bg-surface-800 border border-border-default rounded-xl p-4 text-sm text-text-muted mb-4">
                {{ __('admin.no_data') }}
            </div>
        @endif
    </div>

    {{-- Quick links --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="{{ route('admin.users') }}" class="bg-surface-800 border border-border-default rounded-xl p-5 hover:border-accent-blue/40 transition-colors group">
            <div class="flex items-center gap-3 mb-2">
                <svg class="w-5 h-5 text-text-muted group-hover:text-accent-blue transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
                <h3 class="font-heading text-sm font-bold uppercase tracking-wider text-text-primary">{{ __('admin.nav_users') }}</h3>
            </div>
            <p class="text-sm text-text-muted">{{ __('admin.users_description') }}</p>
        </a>

        <a href="{{ route('admin.activation') }}" class="bg-surface-800 border border-border-default rounded-xl p-5 hover:border-accent-blue/40 transition-colors group">
            <div class="flex items-center gap-3 mb-2">
                <svg class="w-5 h-5 text-text-muted group-hover:text-accent-blue transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4.5h14.25M3 9h9.75M3 13.5h5.25m5.25-.75L17.25 9m0 0L21 12.75M17.25 9v12" />
                </svg>
                <h3 class="font-heading text-sm font-bold uppercase tracking-wider text-text-primary">{{ __('admin.nav_activation') }}</h3>
            </div>
            <p class="text-sm text-text-muted">{{ __('admin.activation_description') }}</p>
        </a>

        <a href="{{ route('admin.game-stats') }}" class="bg-surface-800 border border-border-default rounded-xl p-5 hover:border-accent-blue/40 transition-colors group">
            <div class="flex items-center gap-3 mb-2">
                <svg class="w-5 h-5 text-text-muted group-hover:text-accent-blue transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
                <h3 class="font-heading text-sm font-bold uppercase tracking-wider text-text-primary">{{ __('admin.nav_game_stats') }}</h3>
            </div>
            <p class="text-sm text-text-muted">{{ __('admin.game_stats_description') }}</p>
        </a>
    </div>
</x-admin-layout>
