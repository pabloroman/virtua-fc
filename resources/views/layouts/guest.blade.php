<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0B1120">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/icons/icon-180.png">

        <!-- FOUC prevention: apply saved theme before paint -->
        <script>(function(){var t=localStorage.getItem('virtua-theme');if(t==='light'){document.documentElement.classList.add('light');document.querySelector('meta[name=theme-color]')?.setAttribute('content','#ffffff');}})()</script>

        <!-- Fonts (loaded via CSS @import in app.css) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-surface-900 text-text-primary">

    @if(config('beta.enabled'))
        <div class="bg-amber-500/10 border-b border-amber-500/20 text-amber-400 text-center text-sm py-1.5 px-4">
            <span class="font-semibold">{{ __('beta.badge') }}</span>
            —
            {{ __('beta.login_notice') }}
            @if(config('beta.feedback_url'))
                · <a href="{{ config('beta.feedback_url') }}" target="_blank" class="underline font-semibold hover:text-amber-300">{{ __('beta.send_feedback') }}</a>
            @endif
        </div>
    @endif

        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">

            <div>
                <x-application-logo class="w-20 h-20 fill-current text-text-muted" />
            </div>

            <div
            {{ $attributes->merge(['class' => 'w-full sm:max-w-md mt-6 px-6 py-4 bg-surface-800 border border-border-default shadow-xl overflow-hidden sm:rounded-xl']) }}
            >
                <x-flash-message type="warning" :message="session('warning')" class="mb-4" />

                {{ $slot }}
            </div>

            <div class="mt-4">
                <x-theme-toggle />
            </div>
        </div>
    </body>
</html>
