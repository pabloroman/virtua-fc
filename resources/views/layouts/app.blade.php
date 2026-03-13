<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0B1120">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- FOUC prevention: apply saved theme before paint -->
        <script>(function(){var t=localStorage.getItem('virtua-theme');if(t==='light'){document.documentElement.classList.add('light');document.querySelector('meta[name=theme-color]')?.setAttribute('content','#ffffff');}})()</script>

        <!-- Fonts (loaded via CSS @import in app.css) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-surface-900 text-text-primary">
        <div class="min-h-screen">

            @if(session('impersonating_from'))
                <div class="bg-rose-500 text-white text-center text-sm py-1.5 px-4 flex items-center justify-center gap-3">
                    <span>{{ __('admin.impersonating_banner', ['name' => auth()->user()->name, 'email' => auth()->user()->email]) }}</span>
                    <form method="POST" action="{{ route('admin.stop-impersonation') }}" class="inline">
                        @csrf
                        <button type="submit" class="underline font-semibold hover:text-rose-100">{{ __('admin.stop_impersonating') }}</button>
                    </form>
                </div>
            @endif

            @if(config('beta.enabled'))
                <div class="bg-amber-500/10 border-b border-amber-500/20 text-amber-400 text-center text-sm py-1.5 px-4">
                    <span class="font-semibold">{{ __('beta.badge') }}</span>
                    —
                    {{ __('beta.banner_warning') }}
                    @if(config('beta.feedback_url'))
                        · <a href="{{ config('beta.feedback_url') }}" target="_blank" class="underline font-semibold hover:text-amber-300">{{ __('beta.send_feedback') }}</a>
                    @endif
                </div>
            @endif

            <!-- Page Heading -->
            @isset($header)
                <header>
                    <div class="max-w-7xl mx-auto p-4">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="text-text-body">
                {{ $slot }}
            </main>
            @unless($hideFooter ?? false)
            <footer>
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-center md:justify-start gap-4 md:gap-0 md:space-x-4">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <a class="text-sm text-text-muted cursor-pointer hover:text-text-secondary" :href="route('logout')"
                               onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('app.log_out') }}
                            </a>
                        </form>
                        <a class="text-sm text-text-muted hover:text-text-secondary" href="{{ route('select-team') }}">{{ __('app.new_game') }}</a>
                        <a class="text-sm text-text-muted hover:text-text-secondary" href="{{ route('dashboard') }}">{{ __('app.load_game') }}</a>
                        @if(auth()->user()?->is_admin)
                            <a class="text-sm text-text-muted hover:text-text-secondary" href="{{ route('admin.users') }}">Admin</a>
                        @endif
                        <x-theme-toggle />
                    </div>
                    <div class="mt-4 text-xs text-text-faint text-center md:text-left">
                        © 2026 Pablo Román · Proyecto Open Source · <a href="{{ route('legal') }}" class="hover:text-text-muted">Aviso Legal</a> · <a href="https://github.com/pabloroman/virtua-fc" target="_blank" class="hover:text-text-muted">GitHub</a>
                    </div>
                </div>
            </footer>
            @endunless
        </div>
    </body>
</html>
