<nav class="flex-1 px-3 py-4 space-y-1">
    <a href="{{ route('admin.dashboard') }}"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium min-h-[44px] transition-colors
              {{ request()->routeIs('admin.dashboard') ? 'bg-surface-700 text-text-primary' : 'text-text-muted hover:text-text-secondary hover:bg-surface-700/50' }}">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" />
        </svg>
        {{ __('admin.nav_dashboard') }}
    </a>

    <a href="{{ route('admin.users') }}"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium min-h-[44px] transition-colors
              {{ request()->routeIs('admin.users') ? 'bg-surface-700 text-text-primary' : 'text-text-muted hover:text-text-secondary hover:bg-surface-700/50' }}">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
        {{ __('admin.nav_users') }}
    </a>

    <a href="{{ route('admin.activation') }}"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium min-h-[44px] transition-colors
              {{ request()->routeIs('admin.activation') ? 'bg-surface-700 text-text-primary' : 'text-text-muted hover:text-text-secondary hover:bg-surface-700/50' }}">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4.5h14.25M3 9h9.75M3 13.5h5.25m5.25-.75L17.25 9m0 0L21 12.75M17.25 9v12" />
        </svg>
        {{ __('admin.nav_activation') }}
    </a>
</nav>

<div class="px-3 py-4 border-t border-border-default space-y-1">
    <a href="{{ route('dashboard') }}"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium min-h-[44px] text-text-muted hover:text-text-secondary hover:bg-surface-700/50 transition-colors">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
        </svg>
        {{ __('admin.back_to_app') }}
    </a>

    <x-theme-toggle />
</div>
