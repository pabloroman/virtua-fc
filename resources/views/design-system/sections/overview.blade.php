<section id="overview" class="mb-20">
    <div class="mb-10">
        <h1 class="font-heading text-3xl lg:text-4xl font-bold uppercase tracking-wide text-white mb-3">VirtuaFC Design System</h1>
        <p class="text-sm text-slate-400 max-w-2xl leading-relaxed">A living reference of the UI patterns, components, and design tokens used across VirtuaFC. Built on a dark-first aesthetic with dual typography, opacity-layered surfaces, and high information density for football management.</p>
    </div>

    {{-- Tech Stack --}}
    <div class="mb-12">
        <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-white mb-5">Tech Stack</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach([
                ['name' => 'Laravel 12', 'desc' => 'Backend framework', 'icon' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'],
                ['name' => 'Tailwind CSS 3', 'desc' => 'Utility-first styling', 'icon' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 6c-2.67 0-4.33 1.33-5 4 1-1.33 2.17-1.83 3.5-1.5.76.19 1.31.74 1.91 1.35C13.4 10.85 14.5 12 17 12c2.67 0 4.33-1.33 5-4-1 1.33-2.17 1.83-3.5 1.5-.76-.19-1.31-.74-1.91-1.35C15.6 7.15 14.5 6 12 6zM7 12c-2.67 0-4.33 1.33-5 4 1-1.33 2.17-1.83 3.5-1.5.76.19 1.31.74 1.91 1.35C8.4 16.85 9.5 18 12 18c2.67 0 4.33-1.33 5-4-1 1.33-2.17 1.83-3.5 1.5-.76-.19-1.31-.74-1.91-1.35C10.6 13.15 9.5 12 7 12z"/></svg>'],
                ['name' => 'Alpine.js 3', 'desc' => 'Lightweight interactivity', 'icon' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L2 12l5 5 5-5-5-5zm5 0l-5 5 5 5 5-5-5-5z"/></svg>'],
                ['name' => 'Vite 5', 'desc' => 'Build & HMR', 'icon' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M21.8 3.1L12.7 21.7c-.2.4-.8.4-1 0L2.2 3.1c-.2-.4.1-.9.6-.8l9.1 1.9c.1 0 .2 0 .3 0l9-1.9c.5-.1.8.4.6.8z"/></svg>'],
            ] as $tech)
            <div class="bg-surface-700/50 border border-white/5 rounded-lg p-4 flex items-start gap-3">
                <div class="text-accent-blue shrink-0 mt-0.5">{!! $tech['icon'] !!}</div>
                <div>
                    <div class="font-heading font-semibold text-sm text-white uppercase tracking-wide">{{ $tech['name'] }}</div>
                    <div class="text-xs text-slate-400 mt-0.5">{{ $tech['desc'] }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Design Principles --}}
    <div class="mb-12">
        <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-white mb-5">Design Principles</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach([
                [
                    'title' => 'Dark-First',
                    'desc' => 'Every surface is dark by default. The UI is built on deep navy and charcoal tones (surface-900 through surface-600), with light text on top. White is used only for headings and emphasis, never for backgrounds.',
                    'color' => 'text-accent-blue',
                ],
                [
                    'title' => 'Dual Typography',
                    'desc' => 'Two typefaces create visual hierarchy: Barlow Condensed (font-heading) for headings, labels, and numbers delivers athletic energy; Inter (font-sans) for body text provides clean readability at small sizes.',
                    'color' => 'text-accent-gold',
                ],
                [
                    'title' => 'Opacity-Based Layers',
                    'desc' => 'Depth is created through translucent white borders (white/5, white/10) and semi-transparent backgrounds (surface-700/50) rather than shadows or solid border colors. This keeps surfaces cohesive.',
                    'color' => 'text-accent-green',
                ],
                [
                    'title' => 'Information Density',
                    'desc' => 'Football management requires dense data presentation. Compact tables, micro-labels (10px uppercase tracking-widest), and tight spacing let managers scan rosters, finances, and standings at a glance.',
                    'color' => 'text-accent-orange',
                ],
            ] as $principle)
            <div class="bg-surface-800 border border-white/5 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-2 h-2 rounded-full {{ $principle['color'] }} bg-current"></div>
                    <h3 class="font-heading font-bold text-sm text-white uppercase tracking-wide">{{ $principle['title'] }}</h3>
                </div>
                <p class="text-sm text-slate-400 leading-relaxed">{{ $principle['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Typeface Specimen --}}
    <div class="mb-12">
        <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-white mb-5">Typefaces</h2>

        {{-- Barlow Condensed --}}
        <div class="bg-surface-800 border border-white/5 rounded-xl p-6 mb-4">
            <div class="flex flex-col md:flex-row md:items-baseline md:justify-between mb-1">
                <h3 class="font-heading text-3xl font-bold text-white uppercase tracking-wide">Barlow Condensed</h3>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1 md:mt-0">font-heading</div>
            </div>
            <p class="text-sm text-slate-400 mb-5">Headings, labels, stat numbers, navigation items. Condensed width maximizes information density while maintaining athletic energy.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-surface-700/50 rounded-lg p-4">
                    <div class="text-[10px] text-slate-500 uppercase tracking-widest mb-2">Regular (400)</div>
                    <div class="font-heading text-2xl text-white" style="font-weight: 400;">AaBbCcDd 0123456789</div>
                    <div class="font-heading text-sm text-slate-400 mt-1" style="font-weight: 400;">The quick brown fox jumps over the lazy dog</div>
                </div>
                <div class="bg-surface-700/50 rounded-lg p-4">
                    <div class="text-[10px] text-slate-500 uppercase tracking-widest mb-2">Semibold (600)</div>
                    <div class="font-heading text-2xl font-semibold text-white">AaBbCcDd 0123456789</div>
                    <div class="font-heading text-sm font-semibold text-slate-400 mt-1">The quick brown fox jumps over the lazy dog</div>
                </div>
                <div class="bg-surface-700/50 rounded-lg p-4">
                    <div class="text-[10px] text-slate-500 uppercase tracking-widest mb-2">Bold (700)</div>
                    <div class="font-heading text-2xl font-bold text-white">AaBbCcDd 0123456789</div>
                    <div class="font-heading text-sm font-bold text-slate-400 mt-1">The quick brown fox jumps over the lazy dog</div>
                </div>
            </div>
        </div>

        {{-- Inter --}}
        <div class="bg-surface-800 border border-white/5 rounded-xl p-6">
            <div class="flex flex-col md:flex-row md:items-baseline md:justify-between mb-1">
                <h3 class="font-heading text-3xl font-bold text-white uppercase tracking-wide">Inter</h3>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1 md:mt-0">font-sans / font-body</div>
            </div>
            <p class="text-sm text-slate-400 mb-5">Body text, descriptions, form inputs, table data. Optimized for screen readability at small sizes with clear letterforms.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-surface-700/50 rounded-lg p-4">
                    <div class="text-[10px] text-slate-500 uppercase tracking-widest mb-2">Regular (400)</div>
                    <div class="font-sans text-lg text-white">AaBbCcDd 0123456789</div>
                    <div class="font-sans text-sm text-slate-400 mt-1">The quick brown fox jumps over the lazy dog</div>
                </div>
                <div class="bg-surface-700/50 rounded-lg p-4">
                    <div class="text-[10px] text-slate-500 uppercase tracking-widest mb-2">Medium (500)</div>
                    <div class="font-sans text-lg font-medium text-white">AaBbCcDd 0123456789</div>
                    <div class="font-sans text-sm font-medium text-slate-400 mt-1">The quick brown fox jumps over the lazy dog</div>
                </div>
                <div class="bg-surface-700/50 rounded-lg p-4">
                    <div class="text-[10px] text-slate-500 uppercase tracking-widest mb-2">Semibold (600)</div>
                    <div class="font-sans text-lg font-semibold text-white">AaBbCcDd 0123456789</div>
                    <div class="font-sans text-sm font-semibold text-slate-400 mt-1">The quick brown fox jumps over the lazy dog</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Usage example --}}
    <div>
        <h2 class="text-lg font-semibold text-white mb-3">Font Usage</h2>
        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;!-- Heading --&gt;
&lt;h2 class="font-heading text-2xl font-bold uppercase tracking-wide text-white"&gt;...&lt;/h2&gt;

&lt;!-- Micro-label --&gt;
&lt;span class="text-[10px] text-slate-500 uppercase tracking-widest"&gt;...&lt;/span&gt;

&lt;!-- Body text --&gt;
&lt;p class="text-sm text-slate-400"&gt;...&lt;/p&gt;

&lt;!-- Stat number --&gt;
&lt;span class="font-heading text-3xl font-bold text-white"&gt;87&lt;/span&gt;</code></pre>
        </div>
    </div>
</section>
