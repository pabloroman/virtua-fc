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

            <!-- Notifications -->
            @if(session('scout_complete'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div x-data="{ show: true }" x-show="show" x-transition class="mb-4 p-4 bg-sky-900/80 border border-sky-700 rounded-lg flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-sky-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <span class="text-sm font-medium text-sky-100">{{ session('scout_complete') }}</span>
                        </div>
                        <button @click="show = false" class="text-sky-400 hover:text-sky-200">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                </div>
            @endif

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
