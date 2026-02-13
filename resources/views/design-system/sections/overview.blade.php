<section id="overview" class="mb-20">
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-slate-900 mb-2">VirtuaFC Design System</h1>
        <p class="text-slate-500 max-w-2xl">A living reference of the UI patterns, components, and design tokens used across VirtuaFC. Built to ensure visual consistency and accelerate feature development.</p>
    </div>

    {{-- Tech Stack --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-10">
        @foreach([
            ['name' => 'Laravel 12', 'desc' => 'Backend framework'],
            ['name' => 'Tailwind CSS 3', 'desc' => 'Utility-first styling'],
            ['name' => 'Alpine.js 3', 'desc' => 'Lightweight interactivity'],
            ['name' => 'Vite 5', 'desc' => 'Build & HMR'],
        ] as $tech)
        <div class="border border-slate-200 rounded-lg p-4">
            <div class="font-semibold text-sm text-slate-900">{{ $tech['name'] }}</div>
            <div class="text-xs text-slate-500 mt-0.5">{{ $tech['desc'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Design Principles --}}
    <h2 class="text-xl font-semibold text-slate-900 mb-4">Design Principles</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-10">
        @foreach([
            ['title' => 'Mobile-First', 'desc' => 'Base styles target mobile (375px). Enhance with md: and lg: prefixes. All interactive elements are 44px minimum height.'],
            ['title' => 'Semantic Colors', 'desc' => 'Colors convey meaning consistently: green for success, red for danger, amber for warnings, sky for info and interactive elements.'],
            ['title' => 'Slate-Dominated Neutrals', 'desc' => 'Slate palette provides the structural backbone — from slate-50 backgrounds to slate-900 headings. Sky-500 is the primary accent color.'],
            ['title' => 'Progressive Disclosure', 'desc' => 'Complex data shown via expandable sections, modals, and responsive column hiding. Essential information always visible on mobile.'],
        ] as $principle)
        <div class="border border-slate-200 rounded-lg p-5">
            <h3 class="font-semibold text-sm text-slate-900 mb-1">{{ $principle['title'] }}</h3>
            <p class="text-xs text-slate-500 leading-relaxed">{{ $principle['desc'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Font Specimen --}}
    <h2 class="text-xl font-semibold text-slate-900 mb-4">Typeface</h2>
    <div class="border border-slate-200 rounded-lg p-6">
        <div class="text-4xl font-bold text-slate-900 mb-1">Barlow Semi Condensed</div>
        <div class="text-sm text-slate-500 mb-4">Primary typeface &middot; Weights: 400, 600, 800 &middot; Source: fonts.bunny.net</div>
        <div class="flex flex-wrap gap-6 text-slate-700">
            <div>
                <div class="text-xs text-slate-400 mb-1">Regular (400)</div>
                <div class="text-xl" style="font-weight: 400;">AaBbCcDd 0123456789</div>
            </div>
            <div>
                <div class="text-xs text-slate-400 mb-1">Semibold (600)</div>
                <div class="text-xl font-semibold">AaBbCcDd 0123456789</div>
            </div>
            <div>
                <div class="text-xs text-slate-400 mb-1">Extrabold (800)</div>
                <div class="text-xl" style="font-weight: 800;">AaBbCcDd 0123456789</div>
            </div>
        </div>
    </div>
</section>
