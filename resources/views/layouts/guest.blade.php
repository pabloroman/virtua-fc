<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0B1120">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts (loaded via CSS @import in app.css) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-surface-900 text-white">

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
                <x-application-logo class="w-20 h-20 fill-current text-slate-500" />
            </div>

            <div
            {{ $attributes->merge(['class' => 'w-full sm:max-w-md mt-6 px-6 py-4 bg-surface-800 border border-white/5 shadow-xl overflow-hidden sm:rounded-xl']) }}
            >
                @if(session('warning'))
                    <div class="mb-4 p-4 bg-amber-500/10 border border-amber-500/20 rounded-lg text-amber-400 text-sm">
                        {{ session('warning') }}
                    </div>
                @endif

                {{ $slot }}
            </div>
        </div>
    </body>
</html>
