<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0B1120">

        <title>{{ config('app.name', 'Laravel') }} — Admin</title>

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">

        <script>(function(){var t=localStorage.getItem('virtua-theme');if(t==='light'){document.documentElement.classList.add('light');document.querySelector('meta[name=theme-color]')?.setAttribute('content','#ffffff');}})()</script>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-surface-900 text-text-primary">
        <div class="min-h-screen flex flex-col">

            @if(session('impersonating_from'))
                <div class="bg-rose-500 text-white text-center text-xs py-1.5 px-4 flex items-center justify-center gap-3">
                    <span>{{ __('admin.impersonating_banner', ['name' => auth()->user()->name, 'email' => auth()->user()->email]) }}</span>
                    <form method="POST" action="{{ route('admin.stop-impersonation') }}" class="inline">
                        @csrf
                        <x-ghost-button type="submit" color="slate" class="underline font-semibold text-white hover:text-rose-100">{{ __('admin.stop_impersonating') }}</x-ghost-button>
                    </form>
                </div>
            @endif

            @if(config('beta.enabled'))
                <div class="bg-amber-500 text-amber-950 text-center text-xs py-1.5 px-4">
                    <span class="font-semibold">{{ __('beta.badge') }}</span>
                    —
                    {{ __('beta.banner_warning') }}
                    @if(config('beta.feedback_url'))
                        · <a href="{{ config('beta.feedback_url') }}" target="_blank" class="underline font-semibold hover:text-amber-300">{{ __('beta.send_feedback') }}</a>
                    @endif
                </div>
            @endif

            <div class="flex flex-1" x-data="{ sidebarOpen: false }">
                {{-- Mobile header --}}
                <div class="md:hidden flex items-center justify-between px-4 py-3 border-b border-border-default bg-surface-800">
                    <button @click="sidebarOpen = true" class="min-h-[44px] min-w-[44px] flex items-center justify-center text-text-muted hover:text-text-primary">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <span class="font-heading text-sm font-bold uppercase tracking-wider text-text-primary">{{ __('admin.admin_panel') }}</span>
                    <div class="w-[44px]"></div>
                </div>

                {{-- Mobile sidebar overlay --}}
                <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 md:hidden">
                    <div class="fixed inset-0 bg-black/50" @click="sidebarOpen = false"></div>
                    <div class="fixed inset-y-0 left-0 w-72 bg-surface-800 border-r border-border-default flex flex-col"
                         x-show="sidebarOpen"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="-translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="-translate-x-full">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-border-default">
                            <span class="font-heading text-sm font-bold uppercase tracking-wider text-text-primary">{{ __('admin.admin_panel') }}</span>
                            <button @click="sidebarOpen = false" class="min-h-[44px] min-w-[44px] flex items-center justify-center text-text-muted hover:text-text-primary">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        @include('admin._sidebar-nav')
                    </div>
                </div>

                {{-- Desktop sidebar --}}
                <aside class="hidden md:flex md:w-64 md:shrink-0 md:flex-col bg-surface-800 border-r border-border-default">
                    <div class="px-4 py-4 border-b border-border-default">
                        <span class="font-heading text-sm font-bold uppercase tracking-wider text-text-primary">{{ __('admin.admin_panel') }}</span>
                    </div>
                    @include('admin._sidebar-nav')
                </aside>

                {{-- Main content --}}
                <main class="flex-1 min-w-0">
                    <div class="max-w-7xl mx-auto px-4 py-6 md:py-8">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
