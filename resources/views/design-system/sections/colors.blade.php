<section id="colors" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Colors</h2>
    <p class="text-slate-500 mb-8">The color palette is built on Tailwind's slate scale for structure, with sky as the primary accent and semantic colors for status communication.</p>

    {{-- Primary Accent --}}
    <h3 class="text-lg font-semibold text-slate-900 mb-3">Primary Accent</h3>
    <p class="text-sm text-slate-500 mb-4">Sky is the primary interactive color — used for focus rings, active states, links, and selected elements.</p>
    <div class="flex flex-wrap gap-3 mb-10">
        @foreach([
            ['class' => 'bg-sky-50', 'name' => 'sky-50', 'hex' => '#f0f9ff'],
            ['class' => 'bg-sky-100', 'name' => 'sky-100', 'hex' => '#e0f2fe'],
            ['class' => 'bg-sky-200', 'name' => 'sky-200', 'hex' => '#bae6fd'],
            ['class' => 'bg-sky-400', 'name' => 'sky-400', 'hex' => '#38bdf8'],
            ['class' => 'bg-sky-500', 'name' => 'sky-500', 'hex' => '#0ea5e9'],
            ['class' => 'bg-sky-600', 'name' => 'sky-600', 'hex' => '#0284c7'],
            ['class' => 'bg-sky-700', 'name' => 'sky-700', 'hex' => '#0369a1'],
        ] as $color)
        <div class="text-center">
            <div class="w-16 h-16 rounded-lg {{ $color['class'] }} border border-slate-200/50 shadow-sm mb-1.5"></div>
            <div class="text-[10px] font-medium text-slate-700">{{ $color['name'] }}</div>
            <div class="text-[10px] text-slate-400">{{ $color['hex'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Primary Action --}}
    <h3 class="text-lg font-semibold text-slate-900 mb-3">Primary Action</h3>
    <p class="text-sm text-slate-500 mb-4">Red is the default color for primary CTA buttons throughout the application.</p>
    <div class="flex flex-wrap gap-3 mb-10">
        @foreach([
            ['class' => 'bg-red-50', 'name' => 'red-50', 'hex' => '#fef2f2'],
            ['class' => 'bg-red-100', 'name' => 'red-100', 'hex' => '#fee2e2'],
            ['class' => 'bg-red-500', 'name' => 'red-500', 'hex' => '#ef4444'],
            ['class' => 'bg-red-600', 'name' => 'red-600', 'hex' => '#dc2626'],
            ['class' => 'bg-red-700', 'name' => 'red-700', 'hex' => '#b91c1c'],
        ] as $color)
        <div class="text-center">
            <div class="w-16 h-16 rounded-lg {{ $color['class'] }} border border-slate-200/50 shadow-sm mb-1.5"></div>
            <div class="text-[10px] font-medium text-slate-700">{{ $color['name'] }}</div>
            <div class="text-[10px] text-slate-400">{{ $color['hex'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Neutrals --}}
    <h3 class="text-lg font-semibold text-slate-900 mb-3">Neutrals</h3>
    <p class="text-sm text-slate-500 mb-4">Slate provides the structural palette for backgrounds, borders, and text hierarchy.</p>
    <div class="flex flex-wrap gap-3 mb-10">
        @foreach([
            ['class' => 'bg-slate-50', 'name' => 'slate-50', 'hex' => '#f8fafc'],
            ['class' => 'bg-slate-100', 'name' => 'slate-100', 'hex' => '#f1f5f9'],
            ['class' => 'bg-slate-200', 'name' => 'slate-200', 'hex' => '#e2e8f0'],
            ['class' => 'bg-slate-300', 'name' => 'slate-300', 'hex' => '#cbd5e1'],
            ['class' => 'bg-slate-400', 'name' => 'slate-400', 'hex' => '#94a3b8'],
            ['class' => 'bg-slate-500', 'name' => 'slate-500', 'hex' => '#64748b'],
            ['class' => 'bg-slate-600', 'name' => 'slate-600', 'hex' => '#475569'],
            ['class' => 'bg-slate-700', 'name' => 'slate-700', 'hex' => '#334155'],
            ['class' => 'bg-slate-800', 'name' => 'slate-800', 'hex' => '#1e293b'],
            ['class' => 'bg-slate-900', 'name' => 'slate-900', 'hex' => '#0f172a'],
        ] as $color)
        <div class="text-center">
            <div class="w-16 h-16 rounded-lg {{ $color['class'] }} border border-slate-200/50 shadow-sm mb-1.5"></div>
            <div class="text-[10px] font-medium text-slate-700">{{ $color['name'] }}</div>
            <div class="text-[10px] text-slate-400">{{ $color['hex'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Semantic Colors --}}
    <h3 class="text-lg font-semibold text-slate-900 mb-3">Semantic Colors</h3>
    <p class="text-sm text-slate-500 mb-4">Status-communicating colors used consistently across the application.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
        {{-- Success --}}
        <div>
            <div class="text-sm font-semibold text-slate-700 mb-2">Success</div>
            <div class="flex flex-wrap gap-3">
                @foreach([
                    ['class' => 'bg-green-50', 'name' => 'green-50'],
                    ['class' => 'bg-green-100', 'name' => 'green-100'],
                    ['class' => 'bg-green-500', 'name' => 'green-500'],
                    ['class' => 'bg-green-600', 'name' => 'green-600'],
                    ['class' => 'bg-emerald-500', 'name' => 'emerald-500'],
                    ['class' => 'bg-emerald-600', 'name' => 'emerald-600'],
                ] as $color)
                <div class="text-center">
                    <div class="w-12 h-12 rounded-lg {{ $color['class'] }} border border-slate-200/50 shadow-sm mb-1"></div>
                    <div class="text-[10px] text-slate-500">{{ $color['name'] }}</div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Warning --}}
        <div>
            <div class="text-sm font-semibold text-slate-700 mb-2">Warning</div>
            <div class="flex flex-wrap gap-3">
                @foreach([
                    ['class' => 'bg-amber-50', 'name' => 'amber-50'],
                    ['class' => 'bg-amber-100', 'name' => 'amber-100'],
                    ['class' => 'bg-amber-500', 'name' => 'amber-500'],
                    ['class' => 'bg-amber-600', 'name' => 'amber-600'],
                    ['class' => 'bg-yellow-400', 'name' => 'yellow-400'],
                    ['class' => 'bg-yellow-600', 'name' => 'yellow-600'],
                ] as $color)
                <div class="text-center">
                    <div class="w-12 h-12 rounded-lg {{ $color['class'] }} border border-slate-200/50 shadow-sm mb-1"></div>
                    <div class="text-[10px] text-slate-500">{{ $color['name'] }}</div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Danger --}}
        <div>
            <div class="text-sm font-semibold text-slate-700 mb-2">Danger</div>
            <div class="flex flex-wrap gap-3">
                @foreach([
                    ['class' => 'bg-red-50', 'name' => 'red-50'],
                    ['class' => 'bg-red-100', 'name' => 'red-100'],
                    ['class' => 'bg-red-500', 'name' => 'red-500'],
                    ['class' => 'bg-red-600', 'name' => 'red-600'],
                    ['class' => 'bg-red-700', 'name' => 'red-700'],
                ] as $color)
                <div class="text-center">
                    <div class="w-12 h-12 rounded-lg {{ $color['class'] }} border border-slate-200/50 shadow-sm mb-1"></div>
                    <div class="text-[10px] text-slate-500">{{ $color['name'] }}</div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Info --}}
        <div>
            <div class="text-sm font-semibold text-slate-700 mb-2">Info</div>
            <div class="flex flex-wrap gap-3">
                @foreach([
                    ['class' => 'bg-sky-50', 'name' => 'sky-50'],
                    ['class' => 'bg-sky-100', 'name' => 'sky-100'],
                    ['class' => 'bg-sky-200', 'name' => 'sky-200'],
                    ['class' => 'bg-sky-500', 'name' => 'sky-500'],
                ] as $color)
                <div class="text-center">
                    <div class="w-12 h-12 rounded-lg {{ $color['class'] }} border border-slate-200/50 shadow-sm mb-1"></div>
                    <div class="text-[10px] text-slate-500">{{ $color['name'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- App Background Gradient --}}
    <h3 class="text-lg font-semibold text-slate-900 mb-3">Application Background</h3>
    <p class="text-sm text-slate-500 mb-4">The main app uses a dark gradient background that provides contrast for the white content panels.</p>
    <div class="rounded-lg overflow-hidden border border-slate-200 mb-3">
        <div class="h-24 bg-gradient-to-bl from-slate-900 via-cyan-950 to-teal-950"></div>
    </div>
    <div x-data="{ copied: false }" class="relative">
        <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
        </button>
        <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">bg-gradient-to-bl from-slate-900 via-cyan-950 to-teal-950</code></pre>
    </div>
</section>
