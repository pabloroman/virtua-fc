<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0B1120">

        <title>{{ __('Mantenimiento') }} - {{ config('app.name') }}</title>

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">

        <!-- FOUC prevention: apply saved theme before paint -->
        <script>(function(){var t=localStorage.getItem('virtua-theme');if(t==='light'){document.documentElement.classList.add('light');document.querySelector('meta[name=theme-color]')?.setAttribute('content','#ffffff');}})()</script>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-surface-900 text-text-primary">
        <div class="min-h-screen flex flex-col">
            {{-- Header --}}
            <header class="py-8 flex justify-center">
                <x-application-logo />
            </header>

            {{-- Content --}}
            <main class="flex-1 flex items-center justify-center max-w-3xl mx-auto w-full px-4 sm:px-6 pb-12">
                <div class="bg-surface-800 border border-border-default rounded-xl p-6 sm:p-10 w-full text-center">
                    {{-- Icon --}}
                    <div class="flex justify-center mb-6">
                        <div class="w-16 h-16 rounded-full bg-accent-orange/15 flex items-center justify-center">
                            <svg class="w-8 h-8 text-accent-orange" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path fill-rule="evenodd" d="M12 6.75a5.25 5.25 0 0 1 6.775-5.025.75.75 0 0 1 .313 1.248l-3.32 3.319c.063.475.276.934.641 1.299.365.365.824.578 1.3.64l3.318-3.319a.75.75 0 0 1 1.248.313 5.25 5.25 0 0 1-5.472 6.756c-1.018-.086-1.87.1-2.309.634L7.344 21.3A3.298 3.298 0 1 1 2.7 16.657l8.684-7.151c.533-.44.72-1.291.634-2.309A5.342 5.342 0 0 1 12 6.75ZM4.117 19.125a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75h-.008a.75.75 0 0 1-.75-.75v-.008Z" clip-rule="evenodd" />
                                <path d="m10.076 8.64-2.201-2.2V4.874a.75.75 0 0 0-.364-.643l-3.75-2.25a.75.75 0 0 0-.916.113l-.75.75a.75.75 0 0 0-.113.916l2.25 3.75a.75.75 0 0 0 .643.364h1.564l2.062 2.062 1.575-1.297Z" />
                                <path fill-rule="evenodd" d="m12.556 17.329 4.183 4.182a3.375 3.375 0 0 0 4.773-4.773l-3.306-3.305a6.803 6.803 0 0 1-1.53.043c-.394-.034-.682-.006-.867.042a.589.589 0 0 0-.167.063l-3.086 3.748Zm3.414-1.36a.75.75 0 0 1 1.06 0l1.875 1.876a.75.75 0 1 1-1.06 1.06L15.97 17.03a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </div>

                    <h1 class="font-heading text-2xl sm:text-3xl font-bold uppercase tracking-wide text-text-primary mb-3">{{ __('En mantenimiento') }}</h1>

                    <div class="space-y-4 text-sm sm:text-base leading-relaxed text-text-body max-w-lg mx-auto">
                        <p>{{ __('Estamos realizando tareas de mantenimiento para mejorar tu experiencia de juego.') }}</p>
                        <p>{{ __('Volveremos en breve. Gracias por tu paciencia.') }}</p>
                    </div>

                    {{-- Retry hint --}}
                    <div class="mt-8 pt-6 border-t border-border-default">
                        <p class="text-xs text-text-faint">{{ __('Puedes volver a intentarlo en unos minutos.') }}</p>
                    </div>
                </div>
            </main>

            {{-- Footer --}}
            <footer class="bg-surface-800/40">
                <div class="border-t border-border-default/50">
                    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
                        <div class="flex flex-col items-center gap-3">
                            <div class="-skew-x-12 bg-text-faint/15 px-3 py-0.5">
                                <span class="skew-x-12 inline-block text-lg font-extrabold text-text-faint tracking-tight" style="font-family: 'Barlow Semi Condensed', sans-serif;">Virtua FC</span>
                            </div>
                            <p class="text-xs text-text-faint">
                                &copy; {{ date('Y') }} Pablo Rom&aacute;n &middot; <a href="https://github.com/pabloroman/virtua-fc" target="_blank" class="hover:text-text-muted transition-colors">Proyecto Open Source</a>
                            </p>
                            <x-theme-toggle />
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
