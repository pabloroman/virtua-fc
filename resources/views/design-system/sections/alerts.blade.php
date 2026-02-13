<section id="alerts" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Alerts</h2>
    <p class="text-slate-500 mb-8">Alert patterns for flash messages, status banners, and inline notifications. Bold left-bar accents with sharp edges for clear visual hierarchy.</p>

    {{-- Flash Messages --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Flash Messages</h3>
        <p class="text-sm text-slate-500 mb-4">Used for session-based success/error feedback after form submissions. Left border accent with no rounding for a sharp, assertive look.</p>

        <div class="border border-slate-200 rounded-lg p-6 space-y-3 mb-3">
            {{-- Success --}}
            <div class="flex items-start gap-3 border-l-4 border-l-teal-500 bg-teal-50/80 py-3 pl-4 pr-4">
                <svg class="w-5 h-5 text-teal-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm text-teal-800">Transfer completed! Pedri has joined your squad.</span>
            </div>

            {{-- Error --}}
            <div class="flex items-start gap-3 border-l-4 border-l-red-500 bg-red-50/80 py-3 pl-4 pr-4">
                <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm text-red-800">Transfer bid rejected. The asking price is higher.</span>
            </div>

            {{-- Warning --}}
            <div class="flex items-start gap-3 border-l-4 border-l-amber-500 bg-amber-50/80 py-3 pl-4 pr-4">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span class="text-sm text-amber-800">Budget allocation is locked during the transfer window.</span>
            </div>

            {{-- Info --}}
            <div class="flex items-start gap-3 border-l-4 border-l-sky-500 bg-sky-50/80 py-3 pl-4 pr-4">
                <svg class="w-5 h-5 text-sky-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm text-sky-800">Scout report will be available after the next matchday.</span>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">{{-- Success --}}
&lt;div class="flex items-start gap-3 border-l-4 border-l-teal-500 bg-teal-50/80 py-3 pl-4 pr-4"&gt;
    &lt;svg class="w-5 h-5 text-teal-600 shrink-0 mt-0.5"&gt;...&lt;/svg&gt;
    &lt;span class="text-sm text-teal-800"&gt;&#123;&#123; session('success') &#125;&#125;&lt;/span&gt;
&lt;/div&gt;

{{-- Error --}}
&lt;div class="flex items-start gap-3 border-l-4 border-l-red-500 bg-red-50/80 py-3 pl-4 pr-4"&gt;
    &lt;svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5"&gt;...&lt;/svg&gt;
    &lt;span class="text-sm text-red-800"&gt;&#123;&#123; session('error') &#125;&#125;&lt;/span&gt;
&lt;/div&gt;

{{-- Warning --}}
&lt;div class="flex items-start gap-3 border-l-4 border-l-amber-500 bg-amber-50/80 py-3 pl-4 pr-4"&gt;
    &lt;svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"&gt;...&lt;/svg&gt;
    &lt;span class="text-sm text-amber-800"&gt;...&lt;/span&gt;
&lt;/div&gt;

{{-- Info --}}
&lt;div class="flex items-start gap-3 border-l-4 border-l-sky-500 bg-sky-50/80 py-3 pl-4 pr-4"&gt;
    &lt;svg class="w-5 h-5 text-sky-600 shrink-0 mt-0.5"&gt;...&lt;/svg&gt;
    &lt;span class="text-sm text-sky-800"&gt;...&lt;/span&gt;
&lt;/div&gt;</code></pre>
        </div>

        {{-- Alert color reference --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-slate-200">
                    <tr>
                        <th class="font-semibold py-2 pr-4">Type</th>
                        <th class="font-semibold py-2 pr-4">Border</th>
                        <th class="font-semibold py-2 pr-4">Background</th>
                        <th class="font-semibold py-2 pr-4">Icon</th>
                        <th class="font-semibold py-2">Text</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-medium text-slate-700">Success</td>
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">teal-500</td>
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">teal-50/80</td>
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">teal-600</td>
                        <td class="py-2 font-mono text-xs text-sky-600">teal-800</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-medium text-slate-700">Error</td>
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">red-500</td>
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">red-50/80</td>
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">red-600</td>
                        <td class="py-2 font-mono text-xs text-sky-600">red-800</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-medium text-slate-700">Warning</td>
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">amber-500</td>
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">amber-50/80</td>
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">amber-600</td>
                        <td class="py-2 font-mono text-xs text-sky-600">amber-800</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-medium text-slate-700">Info</td>
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">sky-500</td>
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">sky-50/80</td>
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">sky-600</td>
                        <td class="py-2 font-mono text-xs text-sky-600">sky-800</td>
                    </tr>
                </tbody>
            </table>
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
