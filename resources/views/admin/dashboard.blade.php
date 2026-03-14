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

    {{-- Quick links --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
    </div>
</x-admin-layout>
