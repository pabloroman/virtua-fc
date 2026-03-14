<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0B1120">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">

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

            <!-- Page Heading -->
            @isset($header)
                <header>
                    <div class="max-w-7xl mx-auto p-4">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="text-text-body flex-1">
                {{ $slot }}
            </main>
            @unless($hideFooter ?? false)
            <footer class="mt-12 bg-surface-800/40">
                <div class="border-t border-border-default/50">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                            {{-- Logo + copyright --}}
                            <div class="flex flex-col items-center md:items-start gap-3">
                                <div class="-skew-x-12 bg-text-faint/15 px-3 py-0.5">
                                    <span class="skew-x-12 inline-block text-lg font-extrabold text-text-faint tracking-tight" style="font-family: 'Barlow Semi Condensed', sans-serif;">Virtua FC</span>
                                </div>
                                <p class="text-xs text-text-faint">
                                    &copy; {{ date('Y') }} Pablo Román &middot; <a href="https://github.com/pabloroman/virtua-fc" target="_blank" class="hover:text-text-muted transition-colors">Proyecto Open Source</a> &middot; <a href="{{ route('legal') }}" class="hover:text-text-muted transition-colors">Aviso Legal</a>
                                </p>
                            </div>

                            {{-- Navigation links --}}
                            <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs text-text-muted">
                                <a href="{{ route('select-team') }}" class="hover:text-text-secondary transition-colors">{{ __('app.new_game') }}</a>
                                <a href="{{ route('dashboard') }}" class="hover:text-text-secondary transition-colors">{{ __('app.load_game') }}</a>
                                <form method="POST" action="{{ route('logout') }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-text-muted hover:text-text-secondary transition-colors cursor-pointer">{{ __('app.log_out') }}</button>
                                </form>
                                @if(auth()->user()?->is_admin)
                                    <a href="{{ route('admin.users') }}" class="hover:text-text-secondary transition-colors">Admin</a>
                                    <a href="{{ route('admin.activation') }}" class="hover:text-text-secondary transition-colors">{{ __('admin.activation_title') }}</a>
                                @endif
                                <x-theme-toggle />
                            </nav>
                        </div>
                    </div>
                </div>
            </footer>
            @endunless
        </div>
    </body>
</html>
