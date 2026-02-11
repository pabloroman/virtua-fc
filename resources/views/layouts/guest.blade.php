<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=barlow-semi-condensed:400,600,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased bg-gradient-to-bl from-slate-900 via-cyan-950 to-teal-950">

    @if(config('beta.enabled'))
        <div class="bg-amber-500 text-amber-950 text-center text-sm py-1.5 px-4">
            <span class="font-semibold">{{ __('beta.badge') }}</span>
            —
            {{ __('beta.login_notice') }}
            @if(config('beta.feedback_url'))
                · <a href="{{ config('beta.feedback_url') }}" target="_blank" class="underline font-semibold hover:text-amber-800">{{ __('beta.send_feedback') }}</a>
            @endif
        </div>
    @endif

        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">

            <div>
                <x-application-logo class="w-20 h-20 fill-current text-slate-500" />
            </div>

            <div
            {{ $attributes->merge(['class' => 'w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg']) }}
            >
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
