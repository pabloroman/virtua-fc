<section id="typography" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Typography</h2>
    <p class="text-slate-500 mb-8">Font sizes are scaled up from Tailwind defaults via <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded text-slate-700">tailwind.config.js</code>. On mobile (&lt;768px), the root font-size drops to 14px, proportionally scaling all rem-based values.</p>

    {{-- Font Size Scale --}}
    <h3 class="text-lg font-semibold text-slate-900 mb-4">Font Size Scale</h3>
    <div class="border border-slate-200 rounded-lg overflow-hidden mb-10">
        @foreach([
            ['class' => 'text-xs', 'name' => 'text-xs', 'size' => '0.8rem', 'usage' => 'Metadata, timestamps, badges'],
            ['class' => 'text-sm', 'name' => 'text-sm', 'size' => '1rem', 'usage' => 'Body text, table cells, labels'],
            ['class' => 'text-base', 'name' => 'text-base', 'size' => '1.25rem', 'usage' => 'Default base size'],
            ['class' => 'text-xl', 'name' => 'text-xl', 'size' => '1.563rem', 'usage' => 'Section headings'],
            ['class' => 'text-2xl', 'name' => 'text-2xl', 'size' => '1.953rem', 'usage' => 'Featured values, large text'],
            ['class' => 'text-3xl', 'name' => 'text-3xl', 'size' => '2.441rem', 'usage' => 'Page titles'],
            ['class' => 'text-4xl', 'name' => 'text-4xl', 'size' => '3.052rem', 'usage' => 'Hero text, match scores'],
        ] as $size)
        <div class="px-5 py-3 flex items-baseline gap-4 {{ !$loop->last ? 'border-b border-slate-100' : '' }}">
            <div class="w-24 shrink-0">
                <code class="text-[10px] font-mono text-sky-600">{{ $size['name'] }}</code>
                <div class="text-[10px] text-slate-400">{{ $size['size'] }}</div>
            </div>
            <div class="{{ $size['class'] }} text-slate-900 truncate flex-1">The quick brown fox</div>
            <div class="text-xs text-slate-400 hidden md:block shrink-0">{{ $size['usage'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Heading Patterns --}}
    <h3 class="text-lg font-semibold text-slate-900 mb-4">Heading Patterns</h3>
    <div class="border border-slate-200 rounded-lg p-6 space-y-4 mb-4">
        <div>
            <div class="text-3xl font-bold text-slate-900">Page Title</div>
            <div class="text-xs text-slate-400 mt-1">text-3xl font-bold text-slate-900</div>
        </div>
        <div class="pt-4 border-t border-slate-100">
            <div class="text-xl font-semibold text-slate-900">Section Heading</div>
            <div class="text-xs text-slate-400 mt-1">text-xl font-semibold text-slate-900</div>
        </div>
        <div class="pt-4 border-t border-slate-100">
            <div class="text-sm font-semibold text-slate-900">Card Header</div>
            <div class="text-xs text-slate-400 mt-1">text-sm font-semibold text-slate-900</div>
        </div>
        <div class="pt-4 border-t border-slate-100">
            <div class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Group Label</div>
            <div class="text-xs text-slate-400 mt-1">text-xs font-semibold text-slate-600 uppercase tracking-wide</div>
        </div>
    </div>

    <div x-data="{ copied: false }" class="relative mb-10">
        <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
        </button>
        <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;h1 class="text-3xl font-bold text-slate-900"&gt;Page Title&lt;/h1&gt;
&lt;h2 class="text-xl font-semibold text-slate-900"&gt;Section Heading&lt;/h2&gt;
&lt;h3 class="text-sm font-semibold text-slate-900"&gt;Card Header&lt;/h3&gt;
&lt;span class="text-xs font-semibold text-slate-600 uppercase tracking-wide"&gt;Group Label&lt;/span&gt;</code></pre>
    </div>

    {{-- Text Styles --}}
    <h3 class="text-lg font-semibold text-slate-900 mb-4">Text Styles</h3>
    <div class="border border-slate-200 rounded-lg p-6 space-y-3 mb-4">
        <div>
            <span class="text-sm text-slate-700">Body text — </span>
            <code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded text-slate-500">text-sm text-slate-700</code>
        </div>
        <div>
            <span class="text-sm text-slate-500">Secondary text — </span>
            <code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded text-slate-500">text-sm text-slate-500</code>
        </div>
        <div>
            <span class="text-xs text-slate-400">Metadata — </span>
            <code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded text-slate-500">text-xs text-slate-400</code>
        </div>
        <div>
            <a href="#" class="text-sm text-sky-600 hover:text-sky-800">Link text &rarr;</a>
            <code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded text-slate-500 ml-2">text-sky-600 hover:text-sky-800</code>
        </div>
        <div>
            <span class="text-sm text-green-600">+€12.5M</span>
            <code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded text-slate-500 ml-2">text-green-600 (income)</code>
        </div>
        <div>
            <span class="text-sm text-red-600">-€8.2M</span>
            <code class="text-[10px] bg-slate-100 px-1 py-0.5 rounded text-slate-500 ml-2">text-red-600 (expense)</code>
        </div>
    </div>

    {{-- Mobile Scaling Note --}}
    <div class="bg-sky-50 border border-sky-200 rounded-lg p-4 text-sm text-sky-800">
        <span class="font-semibold">Mobile scaling:</span> The root font-size drops to 14px on screens narrower than 768px (from the default ~20px). All rem-based sizes scale proportionally. Never use fixed <code class="text-xs bg-sky-100 px-1 py-0.5 rounded">px</code> values for font sizes — use Tailwind's <code class="text-xs bg-sky-100 px-1 py-0.5 rounded">text-*</code> utilities.
    </div>
</section>
