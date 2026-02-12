<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>VirtuaFC Design System</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=barlow-semi-condensed:400,600,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white text-slate-700"
      x-data="{
          mobileNav: false,
          activeSection: 'overview',
          init() {
              const observer = new IntersectionObserver((entries) => {
                  entries.forEach(entry => {
                      if (entry.isIntersecting) {
                          this.activeSection = entry.target.id;
                      }
                  });
              }, { rootMargin: '-80px 0px -70% 0px', threshold: 0 });

              document.querySelectorAll('section[id]').forEach(section => {
                  observer.observe(section);
              });
          }
      }">

    {{-- Mobile Top Bar --}}
    <div class="md:hidden fixed top-0 left-0 right-0 z-40 bg-white border-b border-slate-200 px-4 py-3 flex items-center justify-between">
        <button @click="mobileNav = true" class="p-1 text-slate-500 hover:text-slate-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <span class="font-semibold text-sm text-slate-900">VirtuaFC Design System</span>
        <div class="w-6"></div>
    </div>

    {{-- Mobile Drawer Backdrop --}}
    <div x-show="mobileNav" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="md:hidden fixed inset-0 z-50 bg-slate-800/60" @click="mobileNav = false" style="display: none;"></div>

    {{-- Mobile Drawer --}}
    <div x-show="mobileNav" x-transition:enter="ease-out duration-300" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
         class="md:hidden fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200 overflow-y-auto" style="display: none;">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <span class="font-bold text-slate-900">Design System</span>
            <button @click="mobileNav = false" class="p-1 text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <nav class="px-3 py-4">
            @include('design-system.partials.nav')
        </nav>
    </div>

    <div class="flex min-h-screen">
        {{-- Desktop Side Navigation --}}
        <aside class="hidden md:block w-64 shrink-0 border-r border-slate-200 bg-slate-50 overflow-y-auto sticky top-0 h-screen">
            <div class="px-5 py-5 border-b border-slate-200">
                <div class="font-bold text-slate-900">VirtuaFC</div>
                <div class="text-xs text-slate-500 mt-0.5">Design System</div>
            </div>
            <nav class="px-3 py-4">
                @include('design-system.partials.nav')
            </nav>
        </aside>

        {{-- Main Content --}}
        <main class="flex-1 min-w-0 pt-14 md:pt-0">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
