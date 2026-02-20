<section id="logo-brand" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Logo & Brand</h2>
    <p class="text-slate-500 mb-8">The VirtuaFC brand identity is built around a bold skewed red parallelogram with white text. The skew angle (-12deg) is the defining visual motif carried across logo, favicon, and UI accents.</p>

    {{-- Primary Logo --}}
    <h3 class="text-lg font-semibold text-slate-900 mb-3">Primary Logo</h3>
    <p class="text-sm text-slate-500 mb-4">The main wordmark rendered as an SVG. Uses a skewed red-600 parallelogram with Barlow Semi Condensed white text.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
        {{-- Light background --}}
        <div class="border border-slate-200 rounded-lg p-8 flex items-center justify-center bg-white">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 64" class="h-14">
                <defs>
                    <style>
                        .logo-bg { fill: #dc2626; }
                        .logo-text { fill: #ffffff; font-family: 'Barlow Semi Condensed', 'Arial Black', sans-serif; font-weight: 700; font-size: 38px; letter-spacing: -0.5px; }
                    </style>
                </defs>
                <rect class="logo-bg" x="8" y="6" width="264" height="52" rx="2" transform="skewX(-12)"/>
                <text class="logo-text" x="140" y="46" text-anchor="middle">Virtua FC</text>
            </svg>
        </div>
        {{-- Dark background --}}
        <div class="border border-slate-700 rounded-lg p-8 flex items-center justify-center bg-gradient-to-bl from-slate-900 via-cyan-950 to-teal-950">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 64" class="h-14">
                <defs>
                    <style>
                        .logo-bg-dark { fill: #dc2626; }
                        .logo-text-dark { fill: #ffffff; font-family: 'Barlow Semi Condensed', 'Arial Black', sans-serif; font-weight: 700; font-size: 38px; letter-spacing: -0.5px; }
                    </style>
                </defs>
                <rect class="logo-bg-dark" x="8" y="6" width="264" height="52" rx="2" transform="skewX(-12)"/>
                <text class="logo-text-dark" x="140" y="46" text-anchor="middle">Virtua FC</text>
            </svg>
        </div>
    </div>
    <div class="flex gap-3 mb-10">
        <span class="text-[10px] text-slate-400 bg-slate-100 px-2 py-1 rounded">Light background</span>
        <span class="text-[10px] text-slate-400 bg-slate-100 px-2 py-1 rounded">Dark background</span>
    </div>

    {{-- Logo Sizes --}}
    <h3 class="text-lg font-semibold text-slate-900 mb-3">Logo Sizes</h3>
    <p class="text-sm text-slate-500 mb-4">The logo scales across contexts — from navigation headers to compact footers. Use the Tailwind/HTML implementation for in-app rendering.</p>
    <div class="border border-slate-200 rounded-lg p-6 space-y-6 mb-4">
        {{-- Large --}}
        <div class="flex flex-col md:flex-row md:items-center gap-3">
            <div class="w-32 shrink-0">
                <div class="text-xs text-slate-400">Large (hero)</div>
                <code class="text-[10px] font-mono text-sky-600">text-4xl</code>
            </div>
            <div class="-skew-x-12 bg-red-600 px-6 py-1.5 inline-block self-start">
                <span class="skew-x-12 inline-block text-4xl font-bold text-white tracking-tight">Virtua FC</span>
            </div>
        </div>
        {{-- Medium --}}
        <div class="flex flex-col md:flex-row md:items-center gap-3 pt-4 border-t border-slate-100">
            <div class="w-32 shrink-0">
                <div class="text-xs text-slate-400">Medium (nav)</div>
                <code class="text-[10px] font-mono text-sky-600">text-3xl</code>
            </div>
            <div class="-skew-x-12 bg-red-600 px-4 py-1 inline-block self-start">
                <span class="skew-x-12 inline-block text-3xl font-bold text-white tracking-tight">Virtua FC</span>
            </div>
        </div>
        {{-- Small --}}
        <div class="flex flex-col md:flex-row md:items-center gap-3 pt-4 border-t border-slate-100">
            <div class="w-32 shrink-0">
                <div class="text-xs text-slate-400">Small (footer)</div>
                <code class="text-[10px] font-mono text-sky-600">text-xl</code>
            </div>
            <div class="-skew-x-12 bg-red-600 px-3 py-1 inline-block self-start">
                <span class="skew-x-12 inline-block text-xl font-bold text-white tracking-tight">Virtua FC</span>
            </div>
        </div>
    </div>

    <div x-data="{ copied: false }" class="relative mb-10">
        <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
        </button>
        <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;!-- Logo (Tailwind) --&gt;
&lt;div class="-skew-x-12 bg-red-600 px-4 py-1"&gt;
    &lt;span class="skew-x-12 inline-block text-3xl font-bold text-white tracking-tight"&gt;Virtua FC&lt;/span&gt;
&lt;/div&gt;</code></pre>
    </div>

    {{-- Favicon / App Icon --}}
    <h3 class="text-lg font-semibold text-slate-900 mb-3">Favicon / App Icon</h3>
    <p class="text-sm text-slate-500 mb-4">A minimal monogram using the letter "V" on the skewed red background. Used for browser tabs, bookmarks, and app icons.</p>
    <div class="flex flex-wrap items-end gap-6 mb-4">
        @foreach([
            ['size' => 64, 'label' => '64px'],
            ['size' => 48, 'label' => '48px'],
            ['size' => 32, 'label' => '32px'],
            ['size' => 24, 'label' => '24px'],
            ['size' => 16, 'label' => '16px'],
        ] as $icon)
        <div class="text-center">
            <div class="border border-slate-200 rounded-lg p-3 bg-white mb-1.5 inline-flex items-center justify-center" style="width: {{ $icon['size'] + 24 }}px; height: {{ $icon['size'] + 24 }}px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="{{ $icon['size'] }}" height="{{ $icon['size'] }}">
                    <rect fill="#dc2626" x="4" y="4" width="24" height="24" rx="2" transform="skewX(-12)" transform-origin="center"/>
                    <text fill="white" font-family="'Barlow Semi Condensed', 'Arial Black', sans-serif" font-weight="800" font-size="20" x="16" y="23" text-anchor="middle">V</text>
                </svg>
            </div>
            <div class="text-[10px] text-slate-400">{{ $icon['label'] }}</div>
        </div>
        @endforeach
    </div>

    <div x-data="{ copied: false }" class="relative mb-10">
        <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
        </button>
        <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"&gt;
  &lt;rect fill="#dc2626" x="4" y="4" width="24" height="24" rx="2"
        transform="skewX(-12)" transform-origin="center"/&gt;
  &lt;text fill="white" font-family="'Barlow Semi Condensed', 'Arial Black', sans-serif"
        font-weight="800" font-size="20" x="16" y="23" text-anchor="middle"&gt;V&lt;/text&gt;
&lt;/svg&gt;</code></pre>
    </div>

    {{-- Brand Anatomy --}}
    <h3 class="text-lg font-semibold text-slate-900 mb-3">Brand Anatomy</h3>
    <p class="text-sm text-slate-500 mb-4">The core elements that make up the VirtuaFC visual identity.</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
        <div class="border border-slate-200 rounded-lg p-5">
            <div class="w-10 h-10 rounded-lg bg-red-600 mb-3 -skew-x-12"></div>
            <h4 class="font-semibold text-sm text-slate-900 mb-1">Skewed Parallelogram</h4>
            <p class="text-xs text-slate-500 leading-relaxed">The -12deg skew is the signature shape. Applied via <code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded">-skew-x-12</code> in Tailwind or <code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded">skewX(-12deg)</code> in SVG.</p>
        </div>
        <div class="border border-slate-200 rounded-lg p-5">
            <div class="w-10 h-10 rounded-lg bg-red-600 mb-3 flex items-center justify-center">
                <span class="text-white text-xs font-bold">#dc2626</span>
            </div>
            <h4 class="font-semibold text-sm text-slate-900 mb-1">Brand Red</h4>
            <p class="text-xs text-slate-500 leading-relaxed">Tailwind's <code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded">red-600</code> (#dc2626) is the primary brand color. Used for the logo background and primary CTA buttons.</p>
        </div>
        <div class="border border-slate-200 rounded-lg p-5">
            <div class="h-10 mb-3 flex items-center">
                <span class="text-2xl font-bold text-slate-900 tracking-tight">Barlow SC</span>
            </div>
            <h4 class="font-semibold text-sm text-slate-900 mb-1">Barlow Semi Condensed</h4>
            <p class="text-xs text-slate-500 leading-relaxed">Bold weight (700/800) for the wordmark. The semi-condensed width gives a sporty, athletic feel that matches the football theme.</p>
        </div>
    </div>

    {{-- Usage Guidelines --}}
    <h3 class="text-lg font-semibold text-slate-900 mb-3">Usage Guidelines</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        {{-- Do --}}
        <div class="border border-teal-200 bg-teal-50/50 rounded-lg p-5">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-5 h-5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-sm font-semibold text-teal-800">Do</span>
            </div>
            <ul class="space-y-2 text-xs text-teal-800">
                <li class="flex gap-2"><span class="text-teal-500 shrink-0">&bull;</span> Use the red-600 parallelogram as the logo background</li>
                <li class="flex gap-2"><span class="text-teal-500 shrink-0">&bull;</span> Maintain the -12deg skew angle consistently</li>
                <li class="flex gap-2"><span class="text-teal-500 shrink-0">&bull;</span> Use white text on the red background</li>
                <li class="flex gap-2"><span class="text-teal-500 shrink-0">&bull;</span> Keep adequate clear space around the logo</li>
            </ul>
        </div>
        {{-- Don't --}}
        <div class="border border-red-200 bg-red-50/50 rounded-lg p-5">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-sm font-semibold text-red-800">Don't</span>
            </div>
            <ul class="space-y-2 text-xs text-red-800">
                <li class="flex gap-2"><span class="text-red-400 shrink-0">&bull;</span> Change the skew angle or remove it entirely</li>
                <li class="flex gap-2"><span class="text-red-400 shrink-0">&bull;</span> Use a different background color for the logo</li>
                <li class="flex gap-2"><span class="text-red-400 shrink-0">&bull;</span> Apply effects like drop shadows or gradients to the logo</li>
                <li class="flex gap-2"><span class="text-red-400 shrink-0">&bull;</span> Stretch or distort the logo proportions</li>
            </ul>
        </div>
    </div>

    {{-- Inline SVG Logo (for external use) --}}
    <h3 class="text-lg font-semibold text-slate-900 mt-10 mb-3">SVG Logo (for external use)</h3>
    <p class="text-sm text-slate-500 mb-4">A self-contained SVG for use outside the app (social media, documentation, external sites). No Tailwind dependency.</p>
    <div class="border border-slate-200 rounded-lg p-8 flex items-center justify-center bg-slate-50 mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 64" class="h-16">
            <defs>
                <style>
                    .vfc-bg { fill: #dc2626; }
                    .vfc-text { fill: #ffffff; font-family: 'Barlow Semi Condensed', 'Arial Black', sans-serif; font-weight: 700; font-size: 38px; letter-spacing: -0.5px; }
                </style>
            </defs>
            <rect class="vfc-bg" x="8" y="6" width="264" height="52" rx="2" transform="skewX(-12)"/>
            <text class="vfc-text" x="140" y="46" text-anchor="middle">Virtua FC</text>
        </svg>
    </div>
    <div x-data="{ copied: false }" class="relative">
        <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
        </button>
        <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 64"&gt;
  &lt;rect fill="#dc2626" x="8" y="6" width="264" height="52" rx="2"
        transform="skewX(-12)"/&gt;
  &lt;text fill="#fff" font-family="'Barlow Semi Condensed', 'Arial Black', sans-serif"
        font-weight="700" font-size="38" x="140" y="46"
        text-anchor="middle"&gt;Virtua FC&lt;/text&gt;
&lt;/svg&gt;</code></pre>
    </div>

    {{-- Downloadable Assets (PNG) --}}
    <h3 class="text-lg font-semibold text-slate-900 mt-10 mb-3">Downloadable Assets (PNG)</h3>
    <p class="text-sm text-slate-500 mb-4">Pre-rendered PNG versions for use in presentations, documents, and contexts where SVG is not supported. All assets are in <code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded">/img/brand/</code>.</p>

    {{-- Wordmark PNGs --}}
    <h4 class="text-sm font-semibold text-slate-700 mb-3">Wordmark</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        {{-- Light variant --}}
        <div class="border border-slate-200 rounded-lg overflow-hidden">
            <div class="p-6 flex items-center justify-center bg-white min-h-[120px]">
                <img src="/img/brand/logo.png" alt="VirtuaFC logo" class="h-12">
            </div>
            <div class="border-t border-slate-200 px-4 py-3 bg-slate-50 flex items-center justify-between">
                <div>
                    <div class="text-xs font-medium text-slate-700">Transparent background</div>
                    <div class="text-[10px] text-slate-400">354 &times; 94 (1x) &middot; PNG</div>
                </div>
                <div class="flex gap-2">
                    <a href="/img/brand/logo.png" download class="text-[10px] font-medium text-sky-600 hover:text-sky-800 px-2 py-1 bg-sky-50 rounded transition-colors">1x</a>
                    <a href="/img/brand/logo@2x.png" download class="text-[10px] font-medium text-sky-600 hover:text-sky-800 px-2 py-1 bg-sky-50 rounded transition-colors">2x</a>
                    <a href="/img/brand/logo.svg" download class="text-[10px] font-medium text-sky-600 hover:text-sky-800 px-2 py-1 bg-sky-50 rounded transition-colors">SVG</a>
                </div>
            </div>
        </div>
        {{-- Dark variant --}}
        <div class="border border-slate-700 rounded-lg overflow-hidden">
            <div class="p-6 flex items-center justify-center bg-slate-900 min-h-[120px]">
                <img src="/img/brand/logo-dark.png" alt="VirtuaFC logo on dark background" class="h-16">
            </div>
            <div class="border-t border-slate-700 px-4 py-3 bg-slate-800 flex items-center justify-between">
                <div>
                    <div class="text-xs font-medium text-slate-200">Dark background</div>
                    <div class="text-[10px] text-slate-400">434 &times; 174 (1x) &middot; PNG</div>
                </div>
                <div class="flex gap-2">
                    <a href="/img/brand/logo-dark.png" download class="text-[10px] font-medium text-sky-400 hover:text-sky-300 px-2 py-1 bg-sky-900/30 rounded transition-colors">1x</a>
                    <a href="/img/brand/logo-dark@2x.png" download class="text-[10px] font-medium text-sky-400 hover:text-sky-300 px-2 py-1 bg-sky-900/30 rounded transition-colors">2x</a>
                    <a href="/img/brand/logo-dark.svg" download class="text-[10px] font-medium text-sky-400 hover:text-sky-300 px-2 py-1 bg-sky-900/30 rounded transition-colors">SVG</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Icon PNGs --}}
    <h4 class="text-sm font-semibold text-slate-700 mb-3">App Icon</h4>
    <div class="border border-slate-200 rounded-lg overflow-hidden mb-4">
        <div class="p-6 bg-white">
            <div class="flex flex-wrap items-end gap-6">
                @foreach([
                    ['file' => 'icon-512.png', 'display' => 80, 'label' => '512px'],
                    ['file' => 'icon-256.png', 'display' => 56, 'label' => '256px'],
                    ['file' => 'icon-128.png', 'display' => 40, 'label' => '128px'],
                    ['file' => 'icon-64.png', 'display' => 28, 'label' => '64px'],
                    ['file' => 'icon-32.png', 'display' => 20, 'label' => '32px'],
                ] as $icon)
                <div class="text-center">
                    <div class="mb-1.5 inline-flex items-center justify-center">
                        <img src="/img/brand/{{ $icon['file'] }}" alt="VirtuaFC icon {{ $icon['label'] }}" style="width: {{ $icon['display'] }}px; height: {{ $icon['display'] }}px;" class="rounded">
                    </div>
                    <div class="text-[10px] text-slate-400">{{ $icon['label'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
        <div class="border-t border-slate-200 px-4 py-3 bg-slate-50 flex flex-wrap items-center gap-2">
            <span class="text-xs text-slate-500 mr-2">Download:</span>
            @foreach([
                ['file' => 'icon-512.png', 'label' => '512px'],
                ['file' => 'icon-256.png', 'label' => '256px'],
                ['file' => 'icon-128.png', 'label' => '128px'],
                ['file' => 'icon-64.png', 'label' => '64px'],
                ['file' => 'icon-32.png', 'label' => '32px'],
                ['file' => 'icon.svg', 'label' => 'SVG'],
            ] as $dl)
            <a href="/img/brand/{{ $dl['file'] }}" download class="text-[10px] font-medium text-sky-600 hover:text-sky-800 px-2 py-1 bg-sky-50 rounded transition-colors">{{ $dl['label'] }}</a>
            @endforeach
        </div>
    </div>
</section>
