<section id="buttons" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Buttons</h2>
    <p class="text-slate-500 mb-8">All buttons use a minimum height of 44px for touch accessibility, <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded text-slate-700">rounded-lg</code> corners, and smooth transitions.</p>

    {{-- Primary Button --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Primary Button</h3>
        <p class="text-sm text-slate-500 mb-4">The main call-to-action button. Defaults to red. Four color variants tuned to the cool-toned app palette.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="flex flex-wrap gap-3">
                <x-primary-button color="red">Red (default)</x-primary-button>
                <button type="button" class="inline-flex items-center justify-center px-4 py-2 min-h-[44px] sm:min-h-0 bg-teal-600 hover:bg-teal-700 focus:ring-teal-500 active:bg-teal-800 border border-transparent rounded-lg font-semibold text-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150">Teal</button>
                <x-primary-button color="sky">Sky</x-primary-button>
                <button type="button" class="inline-flex items-center justify-center px-4 py-2 min-h-[44px] sm:min-h-0 bg-slate-700 hover:bg-slate-800 focus:ring-slate-500 active:bg-slate-900 border border-transparent rounded-lg font-semibold text-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150">Slate</button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-primary-button&gt;Save&lt;/x-primary-button&gt;
&lt;x-primary-button color="teal"&gt;Confirm&lt;/x-primary-button&gt;
&lt;x-primary-button color="sky"&gt;Info&lt;/x-primary-button&gt;
&lt;x-primary-button color="slate"&gt;Dismiss&lt;/x-primary-button&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-slate-200">
                    <tr>
                        <th class="font-semibold py-2 pr-4">Prop</th>
                        <th class="font-semibold py-2 pr-4">Type</th>
                        <th class="font-semibold py-2 pr-4">Default</th>
                        <th class="font-semibold py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">color</td>
                        <td class="py-2 pr-4 text-slate-500">string</td>
                        <td class="py-2 pr-4 font-mono text-xs">'red'</td>
                        <td class="py-2 text-slate-500">red | teal | sky | slate</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Semantic Roles --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Semantic Roles</h3>
        <p class="text-sm text-slate-500 mb-4">Each button color has a specific semantic purpose. Use the color that matches the action's intent.</p>

        <div class="border border-slate-200 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="text-left border-b bg-slate-50">
                    <tr>
                        <th class="font-semibold py-2.5 px-4">Color</th>
                        <th class="font-semibold py-2.5 px-4">Role</th>
                        <th class="font-semibold py-2.5 px-4 hidden md:table-cell">Usage Examples</th>
                        <th class="font-semibold py-2.5 px-4">Preview</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-slate-200">
                        <td class="py-2.5 px-4 font-mono text-xs text-sky-600">red</td>
                        <td class="py-2.5 px-4 text-slate-700">Primary CTA</td>
                        <td class="py-2.5 px-4 text-slate-500 hidden md:table-cell">Save, Submit, Advance matchday</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded bg-red-600"></span></td>
                    </tr>
                    <tr class="border-b border-slate-200">
                        <td class="py-2.5 px-4 font-mono text-xs text-sky-600">teal</td>
                        <td class="py-2.5 px-4 text-slate-700">Success / Confirm</td>
                        <td class="py-2.5 px-4 text-slate-500 hidden md:table-cell">Accept offer, Renew contract, Request loan</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded bg-teal-600"></span></td>
                    </tr>
                    <tr class="border-b border-slate-200">
                        <td class="py-2.5 px-4 font-mono text-xs text-sky-600">sky</td>
                        <td class="py-2.5 px-4 text-slate-700">Informational / Process</td>
                        <td class="py-2.5 px-4 text-slate-500 hidden md:table-cell">Conduct draw, Submit bid, Pre-contract offer</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded bg-sky-600"></span></td>
                    </tr>
                    <tr>
                        <td class="py-2.5 px-4 font-mono text-xs text-sky-600">slate</td>
                        <td class="py-2.5 px-4 text-slate-700">Neutral / Dismiss</td>
                        <td class="py-2.5 px-4 text-slate-500 hidden md:table-cell">Let go, Decline, Release player</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded bg-slate-700"></span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Disabled State --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Disabled State</h3>
        <p class="text-sm text-slate-500 mb-4">All button types support a disabled state with reduced opacity and no-cursor.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="flex flex-wrap gap-3">
                <x-primary-button disabled>Disabled Primary</x-primary-button>
                <x-secondary-button disabled>Disabled Secondary</x-secondary-button>
                <x-danger-button disabled>Disabled Danger</x-danger-button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-primary-button disabled&gt;Disabled&lt;/x-primary-button&gt;</code></pre>
        </div>
    </div>

    {{-- Secondary Button --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Secondary Button</h3>
        <p class="text-sm text-slate-500 mb-4">Used for secondary actions. White background with a subtle border.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <x-secondary-button>Secondary Action</x-secondary-button>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-secondary-button&gt;Cancel&lt;/x-secondary-button&gt;</code></pre>
        </div>
    </div>

    {{-- Danger Button --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Danger Button</h3>
        <p class="text-sm text-slate-500 mb-4">For destructive actions. Red background matches the primary button's default, but semantically distinct.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <x-danger-button>Delete Account</x-danger-button>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-danger-button&gt;Delete&lt;/x-danger-button&gt;</code></pre>
        </div>
    </div>

    {{-- Button with Spinner --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Button with Spinner</h3>
        <p class="text-sm text-slate-500 mb-4">Shows a loading spinner during form submission. Uses Alpine.js to toggle the loading state.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3" x-data="{ loading: false }">
            <div class="flex items-center gap-4">
                <button @click="loading = !loading"
                        class="inline-flex items-center justify-center px-4 py-2 min-h-[44px] sm:min-h-0 bg-red-600 hover:bg-red-700 border border-transparent rounded-lg font-semibold text-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition ease-in-out duration-150"
                        :disabled="loading">
                    <svg x-show="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span x-text="loading ? 'Processing...' : 'Submit'">Submit</span>
                </button>
                <span class="text-xs text-slate-400">Click to toggle loading state</span>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-primary-button-spin&gt;Submit&lt;/x-primary-button-spin&gt;</code></pre>
        </div>
    </div>

    {{-- Inline Link Buttons --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Inline Link Buttons</h3>
        <p class="text-sm text-slate-500 mb-4">Text-style buttons used inline for secondary actions within content areas.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="flex flex-wrap gap-6">
                <button class="text-sm text-sky-600 hover:text-sky-800 font-medium">View all &rarr;</button>
                <button class="text-xs text-sky-600 hover:text-sky-800">Mark all as read</button>
                <button class="text-sm text-red-600 hover:text-red-800">Remove</button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;a href="#" class="text-sm text-sky-600 hover:text-sky-800"&gt;View all &amp;rarr;&lt;/a&gt;
&lt;button class="text-xs text-sky-600 hover:text-sky-800"&gt;Mark all as read&lt;/button&gt;
&lt;button class="text-sm text-red-600 hover:text-red-800"&gt;Remove&lt;/button&gt;</code></pre>
        </div>
    </div>

    {{-- Button Sizes Reference --}}
    <div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Size Patterns</h3>
        <p class="text-sm text-slate-500 mb-4">Button sizes are controlled via padding classes rather than a size prop.</p>

        <div class="border border-slate-200 rounded-lg p-6">
            <div class="flex flex-wrap items-end gap-4">
                <div class="text-center">
                    <button class="inline-flex items-center justify-center px-3 py-1 min-h-[44px] sm:min-h-0 bg-red-600 hover:bg-red-700 rounded-lg text-xs font-semibold text-white transition">Small (px-3 py-1)</button>
                </div>
                <div class="text-center">
                    <x-primary-button>Standard (px-4 py-2)</x-primary-button>
                </div>
                <div class="text-center">
                    <button class="inline-flex items-center justify-center px-6 py-3 min-h-[44px] sm:min-h-0 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-semibold text-white transition">Large (px-6 py-3)</button>
                </div>
                <div class="text-center">
                    <button class="w-full inline-flex items-center justify-center px-4 py-2 min-h-[44px] sm:min-h-0 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-semibold text-white transition">Full Width (w-full)</button>
                </div>
            </div>
        </div>
    </div>
</section>
