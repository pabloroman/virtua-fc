<section id="alerts" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-white mb-2">Alerts</h2>
    <p class="text-sm text-slate-400 mb-8">Alert patterns for flash messages, status banners, and inline notifications. Bold left-bar accents for clear visual hierarchy.</p>

    {{-- Flash Messages --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Flash Messages</h3>
        <p class="text-sm text-slate-400 mb-4">Used for session-based success/error feedback after form submissions. Left border accent with tinted dark backgrounds.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 space-y-3 mb-3">
            {{-- Success --}}
            <div class="flex items-start gap-3 border-l-4 border-l-emerald-500 bg-emerald-500/10 py-3 pl-4 pr-4 rounded-r-lg">
                <svg class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm text-emerald-400">Transfer completed! Pedri has joined your squad.</span>
            </div>

            {{-- Error --}}
            <div class="flex items-start gap-3 border-l-4 border-l-red-500 bg-red-500/10 py-3 pl-4 pr-4 rounded-r-lg">
                <svg class="w-5 h-5 text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm text-red-400">Transfer bid rejected. The asking price is higher.</span>
            </div>

            {{-- Warning --}}
            <div class="flex items-start gap-3 border-l-4 border-l-amber-500 bg-amber-500/10 py-3 pl-4 pr-4 rounded-r-lg">
                <svg class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span class="text-sm text-amber-400">Budget allocation is locked during the transfer window.</span>
            </div>

            {{-- Info --}}
            <div class="flex items-start gap-3 border-l-4 border-l-accent-blue bg-accent-blue/10 py-3 pl-4 pr-4 rounded-r-lg">
                <svg class="w-5 h-5 text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm text-blue-400">Scout report will be available after the next matchday.</span>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">{{-- Success --}}
&lt;div class="flex items-start gap-3 border-l-4 border-l-emerald-500 bg-emerald-500/10 py-3 pl-4 pr-4 rounded-r-lg"&gt;
    &lt;svg class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"&gt;...&lt;/svg&gt;
    &lt;span class="text-sm text-emerald-400"&gt;&#123;&#123; session('success') &#125;&#125;&lt;/span&gt;
&lt;/div&gt;

{{-- Error --}}
&lt;div class="flex items-start gap-3 border-l-4 border-l-red-500 bg-red-500/10 py-3 pl-4 pr-4 rounded-r-lg"&gt;
    &lt;svg class="w-5 h-5 text-red-400 shrink-0 mt-0.5"&gt;...&lt;/svg&gt;
    &lt;span class="text-sm text-red-400"&gt;&#123;&#123; session('error') &#125;&#125;&lt;/span&gt;
&lt;/div&gt;

{{-- Warning --}}
&lt;div class="flex items-start gap-3 border-l-4 border-l-amber-500 bg-amber-500/10 py-3 pl-4 pr-4 rounded-r-lg"&gt;
    &lt;svg class="w-5 h-5 text-amber-400 shrink-0 mt-0.5"&gt;...&lt;/svg&gt;
    &lt;span class="text-sm text-amber-400"&gt;...&lt;/span&gt;
&lt;/div&gt;

{{-- Info --}}
&lt;div class="flex items-start gap-3 border-l-4 border-l-accent-blue bg-accent-blue/10 py-3 pl-4 pr-4 rounded-r-lg"&gt;
    &lt;svg class="w-5 h-5 text-blue-400 shrink-0 mt-0.5"&gt;...&lt;/svg&gt;
    &lt;span class="text-sm text-blue-400"&gt;...&lt;/span&gt;
&lt;/div&gt;</code></pre>
        </div>

        {{-- Alert color reference --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-white/10">
                    <tr>
                        <th class="font-semibold py-2 pr-4 text-slate-300">Type</th>
                        <th class="font-semibold py-2 pr-4 text-slate-300">Border</th>
                        <th class="font-semibold py-2 pr-4 text-slate-300">Background</th>
                        <th class="font-semibold py-2 pr-4 text-slate-300">Icon</th>
                        <th class="font-semibold py-2 text-slate-300">Text</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-medium text-white">Success</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-green">emerald-500</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-green">emerald-500/10</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-green">emerald-400</td>
                        <td class="py-2 font-mono text-xs text-accent-green">emerald-400</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-medium text-white">Error</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-red">red-500</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-red">red-500/10</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-red">red-400</td>
                        <td class="py-2 font-mono text-xs text-accent-red">red-400</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-medium text-white">Warning</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-gold">amber-500</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-gold">amber-500/10</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-gold">amber-400</td>
                        <td class="py-2 font-mono text-xs text-accent-gold">amber-400</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-medium text-white">Info</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">accent-blue</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">accent-blue/10</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">blue-400</td>
                        <td class="py-2 font-mono text-xs text-accent-blue">blue-400</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Banner Alerts --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Banner Alerts</h3>
        <p class="text-sm text-slate-400 mb-4">Full-width banners for app-level notices. Used for beta mode and admin impersonation.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl overflow-hidden mb-3">
            <div class="bg-amber-500/10 border-b border-amber-500/20 text-amber-400 text-center text-sm py-1.5 px-4">
                <span class="font-semibold">BETA</span> &mdash; This game is in beta. Your progress may be reset.
            </div>
            <div class="bg-rose-500 text-white text-center text-sm py-1.5 px-4">
                Impersonating user: john@example.com &middot; <a href="#" class="underline font-semibold hover:text-rose-100">Stop</a>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">{{-- Beta banner --}}
&lt;div class="bg-amber-500/10 border-b border-amber-500/20 text-amber-400 text-center text-sm py-1.5 px-4"&gt;
    &lt;span class="font-semibold"&gt;BETA&lt;/span&gt; &amp;mdash; Warning message
&lt;/div&gt;

{{-- Impersonation banner --}}
&lt;div class="bg-rose-500 text-white text-center text-sm py-1.5 px-4"&gt;
    Impersonating user: &#123;&#123; $email &#125;&#125; &amp;middot;
    &lt;a href="#" class="underline font-semibold hover:text-rose-100"&gt;Stop&lt;/a&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Actionable Warning Card --}}
    <div>
        <h3 class="text-lg font-semibold text-white mb-2">Actionable Warning Card</h3>
        <p class="text-sm text-slate-400 mb-4">Dashed border variant used when an action is required from the user (e.g., budget not allocated).</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-3">
            <div class="text-center py-6 border-2 border-dashed border-amber-500/30 bg-amber-500/5 rounded-xl">
                <div class="text-sm text-amber-400 font-medium mb-2">Budget not allocated</div>
                <div class="text-3xl font-bold text-white mb-1">&euro;42.5M</div>
                <div class="text-sm text-slate-400 mb-4">Available surplus to allocate</div>
                <button class="inline-flex items-center gap-2 px-5 py-2 bg-accent-blue hover:bg-blue-600 text-white text-sm font-semibold rounded-lg transition-colors">
                    Set up budget &rarr;
                </button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="text-center py-6 border-2 border-dashed border-amber-500/30 bg-amber-500/5 rounded-xl"&gt;
    &lt;div class="text-sm text-amber-400 font-medium mb-2"&gt;Budget not allocated&lt;/div&gt;
    &lt;div class="text-3xl font-bold text-white mb-1"&gt;&#123;&#123; $amount &#125;&#125;&lt;/div&gt;
    &lt;div class="text-sm text-slate-400 mb-4"&gt;Available surplus&lt;/div&gt;
    &lt;!-- CTA button --&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>
</section>
