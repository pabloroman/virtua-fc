<section id="logo-brand" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Logo & Brand</h2>
    <p class="text-sm text-text-secondary mb-8">The VirtuaFC brand identity is built around a bold skewed red parallelogram with white text. The skew angle (-12deg) is the defining visual motif carried across logo, favicon, and UI accents.</p>

    {{-- Primary Logo --}}
    <h3 class="text-lg font-semibold text-text-primary mb-3">Primary Logo</h3>
    <p class="text-sm text-text-secondary mb-4">The main wordmark rendered as an SVG. Uses a skewed red-600 parallelogram with Barlow Semi Condensed white text.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
        {{-- Dark background (surface-900) --}}
        <div class="border border-border-default rounded-lg p-8 flex items-center justify-center bg-surface-900">
            <x-application-logo />
        </div>
        {{-- Lighter background (surface-800) --}}
        <div class="border border-border-default rounded-lg p-8 flex items-center justify-center bg-surface-800">
            <x-application-logo />
        </div>
    </div>

    {{-- Logo Sizes --}}
    <h3 class="text-lg font-semibold text-text-primary mb-3">Logo Sizes</h3>
    <p class="text-sm text-text-secondary mb-4">The logo scales across contexts -- from navigation headers to compact footers. Use the Tailwind/HTML implementation for in-app rendering.</p>
    <div class="bg-surface-800 border border-border-default rounded-xl p-6 space-y-6 mb-4">
        {{-- Default (responsive sm:text-3xl) --}}
        <div class="flex flex-col md:flex-row md:items-center gap-3">
            <div class="w-32 shrink-0">
                <div class="text-xs text-text-muted">Default (nav)</div>
                <code class="text-[10px] font-mono text-accent-blue">&lt;x-application-logo /&gt;</code>
            </div>
            <div class="self-start">
                <x-application-logo />
            </div>
        </div>
    </div>

    <div x-data="{ copied: false }" class="relative mb-10">
        <button @click="navigator.clipboard.writeText($refs.logoCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
        </button>
        <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="logoCode">&lt;!-- Logo (Blade component) --&gt;
&lt;x-application-logo /&gt;

&lt;!-- Rendered HTML --&gt;
&lt;div class="-skew-x-12 bg-red-600 px-3 sm:px-4 py-1"&gt;
    &lt;span class="skew-x-12 inline-block text-xl sm:text-3xl font-extrabold text-white tracking-tight"
          style="font-family: 'Barlow Semi Condensed', sans-serif;"&gt;Virtua FC&lt;/span&gt;
&lt;/div&gt;</code></pre>
    </div>

    {{-- Favicon / App Icon --}}
    <h3 class="text-lg font-semibold text-text-primary mb-3">Favicon / App Icon</h3>
    <p class="text-sm text-text-secondary mb-4">A minimal monogram using the letter "V" on the skewed red background. Used for browser tabs, bookmarks, and app icons.</p>
    <div class="flex flex-wrap items-end gap-6 mb-4">
        @foreach([
            ['size' => 64, 'label' => '64px'],
            ['size' => 48, 'label' => '48px'],
            ['size' => 32, 'label' => '32px'],
            ['size' => 24, 'label' => '24px'],
            ['size' => 16, 'label' => '16px'],
        ] as $icon)
        <div class="text-center">
            <div class="border border-border-default rounded-lg p-3 bg-surface-800 mb-1.5 inline-flex items-center justify-center" style="width: {{ $icon['size'] + 24 }}px; height: {{ $icon['size'] + 24 }}px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="{{ $icon['size'] }}" height="{{ $icon['size'] }}">
                    <rect fill="#dc2626" x="4" y="4" width="24" height="24" rx="2" transform="skewX(-12)" transform-origin="center"/>
                    <text fill="white" font-family="'Barlow Semi Condensed', 'Arial Black', sans-serif" font-weight="800" font-size="20" x="16" y="23" text-anchor="middle">V</text>
                </svg>
            </div>
            <div class="text-[10px] text-text-muted">{{ $icon['label'] }}</div>
        </div>
        @endforeach
    </div>

    <div x-data="{ copied: false }" class="relative mb-10">
        <button @click="navigator.clipboard.writeText($refs.faviconCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
        </button>
        <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="faviconCode">&lt;svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"&gt;
  &lt;rect fill="#dc2626" x="4" y="4" width="24" height="24" rx="2"
        transform="skewX(-12)" transform-origin="center"/&gt;
  &lt;text fill="white" font-family="'Barlow Semi Condensed', 'Arial Black', sans-serif"
        font-weight="800" font-size="20" x="16" y="23" text-anchor="middle"&gt;V&lt;/text&gt;
&lt;/svg&gt;</code></pre>
    </div>

    {{-- Brand Anatomy --}}
    <h3 class="text-lg font-semibold text-text-primary mb-3">Brand Anatomy</h3>
    <p class="text-sm text-text-secondary mb-4">The core elements that make up the VirtuaFC visual identity.</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
        <div class="bg-surface-800 border border-border-default rounded-xl p-5">
            <div class="w-10 h-10 rounded-lg bg-red-600 mb-3 -skew-x-12"></div>
            <h4 class="font-semibold text-sm text-text-primary mb-1">Skewed Parallelogram</h4>
            <p class="text-xs text-text-secondary leading-relaxed">The -12deg skew is the signature shape. Applied via <code class="text-[10px] bg-surface-700 px-1 py-0.5 rounded-sm text-text-body">-skew-x-12</code> in Tailwind or <code class="text-[10px] bg-surface-700 px-1 py-0.5 rounded-sm text-text-body">skewX(-12deg)</code> in SVG.</p>
        </div>
        <div class="bg-surface-800 border border-border-default rounded-xl p-5">
            <div class="w-10 h-10 rounded-lg bg-red-600 mb-3 flex items-center justify-center">
                <span class="text-white text-xs font-bold">#dc2626</span>
            </div>
            <h4 class="font-semibold text-sm text-text-primary mb-1">Brand Red</h4>
            <p class="text-xs text-text-secondary leading-relaxed">Tailwind's <code class="text-[10px] bg-surface-700 px-1 py-0.5 rounded-sm text-text-body">red-600</code> (#dc2626) is the primary brand color. Used for the logo background and primary CTA buttons.</p>
        </div>
        <div class="bg-surface-800 border border-border-default rounded-xl p-5">
            <div class="h-10 mb-3 flex items-center">
                <span class="text-2xl font-extrabold text-text-primary tracking-tight" style="font-family: 'Barlow Semi Condensed', sans-serif;">Barlow SC</span>
            </div>
            <h4 class="font-semibold text-sm text-text-primary mb-1">Barlow Semi Condensed</h4>
            <p class="text-xs text-text-secondary leading-relaxed">ExtraBold weight (800) for the wordmark. The semi-condensed width gives a sporty, athletic feel that matches the football theme.</p>
        </div>
    </div>

    {{-- Usage Guidelines --}}
    <h3 class="text-lg font-semibold text-text-primary mb-3">Usage Guidelines</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        {{-- Do --}}
        <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-sm font-semibold text-emerald-300">Do</span>
            </div>
            <ul class="space-y-2 text-xs text-emerald-300/90">
                <li class="flex gap-2"><span class="text-emerald-500 shrink-0">&bull;</span> Use the red-600 parallelogram as the logo background</li>
                <li class="flex gap-2"><span class="text-emerald-500 shrink-0">&bull;</span> Maintain the -12deg skew angle consistently</li>
                <li class="flex gap-2"><span class="text-emerald-500 shrink-0">&bull;</span> Use white text on the red background</li>
                <li class="flex gap-2"><span class="text-emerald-500 shrink-0">&bull;</span> Keep adequate clear space around the logo</li>
            </ul>
        </div>
        {{-- Don't --}}
        <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-sm font-semibold text-red-300">Don't</span>
            </div>
            <ul class="space-y-2 text-xs text-red-300/90">
                <li class="flex gap-2"><span class="text-red-500 shrink-0">&bull;</span> Change the skew angle or remove it entirely</li>
                <li class="flex gap-2"><span class="text-red-500 shrink-0">&bull;</span> Use a different background color for the logo</li>
                <li class="flex gap-2"><span class="text-red-500 shrink-0">&bull;</span> Apply effects like drop shadows or gradients to the logo</li>
                <li class="flex gap-2"><span class="text-red-500 shrink-0">&bull;</span> Stretch or distort the logo proportions</li>
            </ul>
        </div>
    </div>

    {{-- Inline SVG Logo (for external use) --}}
    <h3 class="text-lg font-semibold text-text-primary mt-10 mb-3">SVG Logo (for external use)</h3>
    <p class="text-sm text-text-secondary mb-4">A self-contained SVG for use outside the app (social media, documentation, external sites). No Tailwind dependency.</p>
    <div class="bg-surface-700/30 border border-border-default rounded-xl p-8 flex items-center justify-center mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 143 46" class="h-14">
            <defs>
                <style>
                    .vfc-bg { fill: #dc2626; }
                    .vfc-text { fill: #ffffff; font-family: 'Barlow Semi Condensed', 'Arial Black', sans-serif; font-weight: 800; font-size: 28px; }
                </style>
            </defs>
            <rect class="vfc-bg" x="12" y="3" width="129" height="40" transform="skewX(-12)"/>
            <text class="vfc-text" x="72" y="33" text-anchor="middle">Virtua FC</text>
        </svg>
    </div>
    <div x-data="{ copied: false }" class="relative">
        <button @click="navigator.clipboard.writeText($refs.svgLogoCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
        </button>
        <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="svgLogoCode">&lt;svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 143 46"&gt;
  &lt;rect fill="#dc2626" x="12" y="3" width="129" height="40"
        transform="skewX(-12)"/&gt;
  &lt;text fill="#fff" font-family="'Barlow Semi Condensed', 'Arial Black', sans-serif"
        font-weight="800" font-size="28" x="72" y="33"
        text-anchor="middle"&gt;Virtua FC&lt;/text&gt;
&lt;/svg&gt;</code></pre>
    </div>

    {{-- Downloadable Assets (PNG) --}}
    <h3 class="text-lg font-semibold text-text-primary mt-10 mb-3">Downloadable Assets (PNG)</h3>
    <p class="text-sm text-text-secondary mb-4">Pre-rendered PNG versions for use in presentations, documents, and contexts where SVG is not supported. All assets are in <code class="text-[10px] bg-surface-700 px-1 py-0.5 rounded-sm text-text-body">/img/brand/</code>.</p>

    {{-- Wordmark PNGs --}}
    <h4 class="text-sm font-semibold text-text-body mb-3">Wordmark</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        {{-- Dark variant (surface-900) --}}
        <div class="border border-border-default rounded-xl overflow-hidden">
            <div class="p-6 flex items-center justify-center bg-surface-900 min-h-[120px]">
                <img src="/img/brand/logo-dark.png" alt="VirtuaFC logo on dark background" class="h-12">
            </div>
            <div class="border-t border-border-default px-4 py-3 bg-surface-800 flex items-center justify-between">
                <div>
                    <div class="text-xs font-medium text-text-primary">Dark background</div>
                    <div class="text-[10px] text-text-muted">600 &times; 194 &middot; PNG</div>
                </div>
                <div class="flex gap-2">
                    <a href="/img/brand/logo-dark.png" download class="text-[10px] font-medium text-accent-blue hover:text-blue-400 px-2 py-1 bg-accent-blue/10 rounded-sm transition-colors">1x</a>
                    <a href="/img/brand/logo-dark@2x.png" download class="text-[10px] font-medium text-accent-blue hover:text-blue-400 px-2 py-1 bg-accent-blue/10 rounded-sm transition-colors">2x</a>
                    <a href="/img/brand/logo-dark.svg" download class="text-[10px] font-medium text-accent-blue hover:text-blue-400 px-2 py-1 bg-accent-blue/10 rounded-sm transition-colors">SVG</a>
                </div>
            </div>
        </div>
        {{-- Light variant --}}
        <div class="border border-border-default rounded-xl overflow-hidden">
            <div class="p-6 flex items-center justify-center bg-white min-h-[120px]">
                <img src="/img/brand/logo.png" alt="VirtuaFC logo" class="h-12">
            </div>
            <div class="border-t border-border-default px-4 py-3 bg-surface-800 flex items-center justify-between">
                <div>
                    <div class="text-xs font-medium text-text-primary">Light background</div>
                    <div class="text-[10px] text-text-muted">600 &times; 150 &middot; PNG</div>
                </div>
                <div class="flex gap-2">
                    <a href="/img/brand/logo.png" download class="text-[10px] font-medium text-accent-blue hover:text-blue-400 px-2 py-1 bg-accent-blue/10 rounded-sm transition-colors">1x</a>
                    <a href="/img/brand/logo@2x.png" download class="text-[10px] font-medium text-accent-blue hover:text-blue-400 px-2 py-1 bg-accent-blue/10 rounded-sm transition-colors">2x</a>
                    <a href="/img/brand/logo.svg" download class="text-[10px] font-medium text-accent-blue hover:text-blue-400 px-2 py-1 bg-accent-blue/10 rounded-sm transition-colors">SVG</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Icon PNGs --}}
    <h4 class="text-sm font-semibold text-text-body mb-3">App Icon</h4>
    <div class="border border-border-default rounded-xl overflow-hidden mb-4">
        <div class="p-6 bg-surface-800">
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
                        <img src="/img/brand/{{ $icon['file'] }}" alt="VirtuaFC icon {{ $icon['label'] }}" style="width: {{ $icon['display'] }}px; height: {{ $icon['display'] }}px;" class="rounded-sm">
                    </div>
                    <div class="text-[10px] text-text-muted">{{ $icon['label'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
        <div class="border-t border-border-default px-4 py-3 bg-surface-700 flex flex-wrap items-center gap-2">
            <span class="text-xs text-text-muted mr-2">Download:</span>
            @foreach([
                ['file' => 'icon-512.png', 'label' => '512px'],
                ['file' => 'icon-256.png', 'label' => '256px'],
                ['file' => 'icon-128.png', 'label' => '128px'],
                ['file' => 'icon-64.png', 'label' => '64px'],
                ['file' => 'icon-32.png', 'label' => '32px'],
                ['file' => 'icon.svg', 'label' => 'SVG'],
            ] as $dl)
            <a href="/img/brand/{{ $dl['file'] }}" download class="text-[10px] font-medium text-accent-blue hover:text-blue-400 px-2 py-1 bg-accent-blue/10 rounded-sm transition-colors">{{ $dl['label'] }}</a>
            @endforeach
        </div>
    </div>
</section>
