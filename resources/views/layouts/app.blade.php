<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=barlow-condensed:400,600,800&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gradient-to-bl from-slate-900 via-cyan-950 to-teal-950">

            <!-- Page Heading -->
            @isset($header)
                <header>
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="text-slate-700">
                {{ $slot }}
            </main>
            <footer>
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <div class="flex space-x-4">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <a class="text-sm text-slate-400 cursor-pointer hover:text-slate-300" :href="route('logout')"
                               onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </a>
                        </form>
                        <a class="text-sm text-slate-400 hover:text-slate-300" href="{{ route('select-team') }}">Nueva partida</a>
                        <a class="text-sm text-slate-400 hover:text-slate-300" href="{{ route('dashboard') }}">Cargar partida</a>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
