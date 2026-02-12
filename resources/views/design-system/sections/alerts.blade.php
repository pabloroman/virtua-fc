<section id="alerts" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Alerts</h2>
    <p class="text-slate-500 mb-8">Alert patterns for flash messages, status banners, and inline notifications. Each uses a consistent structure: colored background, border, and text.</p>

    {{-- Flash Messages --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Flash Messages</h3>
        <p class="text-sm text-slate-500 mb-4">Used for session-based success/error feedback after form submissions.</p>

        <div class="border border-slate-200 rounded-lg p-6 space-y-3 mb-3">
            <div class="p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                Transfer completed! Pedri has joined your squad.
            </div>
            <div class="p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                Transfer bid rejected. The asking price is higher.
            </div>
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-700 text-sm">
                Budget allocation is locked during the transfer window.
            </div>
            <div class="p-4 bg-sky-50 border border-sky-200 rounded-lg text-sky-700 text-sm">
                Scout report will be available after the next matchday.
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">{{-- Success --}}
&lt;div class="p-4 bg-green-50 border border-green-200 rounded-lg text-green-700"&gt;
    &#123;&#123; session('success') &#125;&#125;
&lt;/div&gt;

{{-- Error --}}
&lt;div class="p-4 bg-red-50 border border-red-200 rounded-lg text-red-700"&gt;
    &#123;&#123; session('error') &#125;&#125;
&lt;/div&gt;

{{-- Warning --}}
&lt;div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-700"&gt;...&lt;/div&gt;

{{-- Info --}}
&lt;div class="p-4 bg-sky-50 border border-sky-200 rounded-lg text-sky-700"&gt;...&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Banner Alert --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Banner Alerts</h3>
        <p class="text-sm text-slate-500 mb-4">Full-width banners for app-level notices. Used for beta mode and admin impersonation.</p>

        <div class="border border-slate-200 rounded-lg overflow-hidden mb-3">
            <div class="bg-amber-500 text-amber-950 text-center text-sm py-1.5 px-4">
                <span class="font-semibold">BETA</span> — This game is in beta. Your progress may be reset.
            </div>
            <div class="bg-rose-500 text-white text-center text-sm py-1.5 px-4">
                Impersonating user: john@example.com &middot; <a href="#" class="underline font-semibold hover:text-rose-100">Stop</a>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="bg-amber-500 text-amber-950 text-center text-sm py-1.5 px-4"&gt;
    &lt;span class="font-semibold"&gt;BETA&lt;/span&gt; &amp;mdash; Warning message
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Dashed Empty State Warning --}}
    <div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Actionable Warning Card</h3>
        <p class="text-sm text-slate-500 mb-4">Dashed border variant used when an action is required from the user (e.g., budget not allocated).</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="text-center py-6 border-2 border-dashed border-amber-300 rounded-lg bg-amber-50">
                <div class="text-sm text-amber-700 font-medium mb-2">Budget not allocated</div>
                <div class="text-3xl font-bold text-slate-900 mb-1">&euro;42.5M</div>
                <div class="text-sm text-slate-500 mb-4">Available surplus to allocate</div>
                <button class="inline-flex items-center gap-2 px-5 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800 transition-colors">
                    Set up budget &rarr;
                </button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="text-center py-6 border-2 border-dashed border-amber-300 rounded-lg bg-amber-50"&gt;
    &lt;div class="text-sm text-amber-700 font-medium mb-2"&gt;Budget not allocated&lt;/div&gt;
    &lt;div class="text-3xl font-bold text-slate-900 mb-1"&gt;&#123;&#123; $amount &#125;&#125;&lt;/div&gt;
    &lt;!-- CTA button --&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>
</section>
