<section id="buttons" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-white mb-2">Buttons</h2>
    <p class="text-sm text-slate-400 mb-10">All buttons use Blade components with a minimum height of 44px for touch accessibility, <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">rounded-lg</code> corners, and smooth transitions. Focus rings use <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">focus:ring-offset-surface-900</code> to match the dark background.</p>

    {{-- ================================================================== --}}
    {{-- PRIMARY BUTTON --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Primary Button</h3>
        <p class="text-sm text-slate-400 mb-4">The main call-to-action. Defaults to accent-blue. Four color variants available for semantic differentiation.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-4">
            <div class="flex flex-wrap gap-3">
                <x-primary-button color="blue">Blue (default)</x-primary-button>
                <x-primary-button color="red">Red</x-primary-button>
                <x-primary-button color="green">Green</x-primary-button>
                <x-primary-button color="amber">Amber</x-primary-button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-primary-button&gt;Save&lt;/x-primary-button&gt;
&lt;x-primary-button color="red"&gt;Delete&lt;/x-primary-button&gt;
&lt;x-primary-button color="green"&gt;Confirm&lt;/x-primary-button&gt;
&lt;x-primary-button color="amber"&gt;Warning&lt;/x-primary-button&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-white/10">
                    <tr>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Prop</th>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Type</th>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Default</th>
                        <th class="font-semibold text-slate-300 py-2">Options</th>
                    </tr>
                </thead>
                <tbody class="text-slate-400">
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4"><code class="text-[10px] text-accent-blue">color</code></td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4"><code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">'blue'</code></td>
                        <td class="py-2">blue | red | green | amber</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4"><code class="text-[10px] text-accent-blue">size</code></td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4"><code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">'default'</code></td>
                        <td class="py-2">default | xs</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- SECONDARY BUTTON --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Secondary Button</h3>
        <p class="text-sm text-slate-400 mb-4">Used for secondary actions. Surface background with a subtle white/10 border. Text lightens on hover.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-4">
            <div class="flex flex-wrap gap-3">
                <x-secondary-button>Cancel</x-secondary-button>
                <x-secondary-button size="xs">Small</x-secondary-button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-secondary-button&gt;Cancel&lt;/x-secondary-button&gt;
&lt;x-secondary-button size="xs"&gt;Small&lt;/x-secondary-button&gt;</code></pre>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- DANGER BUTTON --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Danger Button</h3>
        <p class="text-sm text-slate-400 mb-4">For destructive actions like deleting accounts or removing players. Uses accent-red background.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-4">
            <x-danger-button>Delete Account</x-danger-button>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-danger-button&gt;Delete Account&lt;/x-danger-button&gt;</code></pre>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- GHOST BUTTON --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Ghost Button</h3>
        <p class="text-sm text-slate-400 mb-4">Text-only buttons with no background. Shows a subtle tinted background on hover. Five color variants for different contexts.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-4">
            <div class="flex flex-wrap gap-3">
                <x-ghost-button color="blue">Blue (default)</x-ghost-button>
                <x-ghost-button color="red">Red</x-ghost-button>
                <x-ghost-button color="amber">Amber</x-ghost-button>
                <x-ghost-button color="green">Green</x-ghost-button>
                <x-ghost-button color="slate">Slate</x-ghost-button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-ghost-button&gt;View Details&lt;/x-ghost-button&gt;
&lt;x-ghost-button color="red"&gt;Remove&lt;/x-ghost-button&gt;
&lt;x-ghost-button color="amber"&gt;Edit&lt;/x-ghost-button&gt;
&lt;x-ghost-button color="green"&gt;Accept&lt;/x-ghost-button&gt;
&lt;x-ghost-button color="slate"&gt;Dismiss&lt;/x-ghost-button&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-white/10">
                    <tr>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Prop</th>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Type</th>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Default</th>
                        <th class="font-semibold text-slate-300 py-2">Options</th>
                    </tr>
                </thead>
                <tbody class="text-slate-400">
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4"><code class="text-[10px] text-accent-blue">color</code></td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4"><code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">'blue'</code></td>
                        <td class="py-2">blue | red | amber | green | slate</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4"><code class="text-[10px] text-accent-blue">size</code></td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4"><code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">'default'</code></td>
                        <td class="py-2">default | xs</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- BUTTON WITH SPINNER --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Button with Spinner</h3>
        <p class="text-sm text-slate-400 mb-4">Shows a loading spinner during form submission. Requires an Alpine.js <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">loading</code> state on the parent. The button auto-disables while loading.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-4" x-data="{ loading: false }">
            <div class="flex items-center gap-4">
                <x-primary-button-spin @click="loading = !loading">Submit</x-primary-button-spin>
                <span class="text-xs text-slate-500">Click to toggle loading state</span>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div x-data="{ loading: false }"&gt;
    &lt;form @submit="loading = true"&gt;
        &lt;x-primary-button-spin&gt;Submit&lt;/x-primary-button-spin&gt;
    &lt;/form&gt;
&lt;/div&gt;

{{-- With color variant --}}
&lt;x-primary-button-spin color="green"&gt;Confirm&lt;/x-primary-button-spin&gt;</code></pre>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- BUTTON AS LINK --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Button as Link</h3>
        <p class="text-sm text-slate-400 mb-4">Renders an <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">&lt;a&gt;</code> tag styled as a primary button. Use for navigation that should look like a button.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-4">
            <div class="flex flex-wrap gap-3">
                <x-primary-button-link href="#" color="blue">View Squad</x-primary-button-link>
                <x-primary-button-link href="#" color="green">Start Season</x-primary-button-link>
                <x-primary-button-link href="#" color="amber">View Finances</x-primary-button-link>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-primary-button-link href="&#123;&#123; route('squad') &#125;&#125;"&gt;View Squad&lt;/x-primary-button-link&gt;
&lt;x-primary-button-link href="#" color="green"&gt;Start Season&lt;/x-primary-button-link&gt;</code></pre>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- DISABLED STATES --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Disabled States</h3>
        <p class="text-sm text-slate-400 mb-4">All button components support a <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">disabled</code> attribute. Disabled buttons drop to 50% opacity and show a not-allowed cursor.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-4">
            <div class="flex flex-wrap gap-3">
                <x-primary-button disabled>Disabled Primary</x-primary-button>
                <x-secondary-button disabled>Disabled Secondary</x-secondary-button>
                <x-danger-button disabled>Disabled Danger</x-danger-button>
                <x-ghost-button disabled>Disabled Ghost</x-ghost-button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-primary-button disabled&gt;Disabled&lt;/x-primary-button&gt;
&lt;x-secondary-button disabled&gt;Disabled&lt;/x-secondary-button&gt;
&lt;x-danger-button disabled&gt;Disabled&lt;/x-danger-button&gt;
&lt;x-ghost-button disabled&gt;Disabled&lt;/x-ghost-button&gt;</code></pre>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- SIZE PATTERNS --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Size Patterns</h3>
        <p class="text-sm text-slate-400 mb-4">Components support a <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">size</code> prop with <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">xs</code> and <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">default</code>. For larger or full-width buttons, override with Tailwind classes.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-4">
            <div class="flex flex-wrap items-end gap-4">
                <div class="text-center space-y-2">
                    <x-primary-button size="xs">Extra Small</x-primary-button>
                    <div class="text-[10px] text-slate-500">size="xs"</div>
                </div>
                <div class="text-center space-y-2">
                    <x-primary-button>Standard</x-primary-button>
                    <div class="text-[10px] text-slate-500">default</div>
                </div>
                <div class="text-center space-y-2">
                    <x-primary-button class="px-6 py-3">Large</x-primary-button>
                    <div class="text-[10px] text-slate-500">class="px-6 py-3"</div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-white/5">
                <x-primary-button class="w-full">Full Width Button</x-primary-button>
                <div class="text-[10px] text-slate-500 mt-2 text-center">class="w-full"</div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-primary-button size="xs"&gt;Extra Small&lt;/x-primary-button&gt;
&lt;x-primary-button&gt;Standard&lt;/x-primary-button&gt;
&lt;x-primary-button class="px-6 py-3"&gt;Large&lt;/x-primary-button&gt;
&lt;x-primary-button class="w-full"&gt;Full Width&lt;/x-primary-button&gt;</code></pre>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- SEMANTIC ROLES --}}
    {{-- ================================================================== --}}
    <div>
        <h3 class="text-lg font-semibold text-white mb-2">Semantic Roles</h3>
        <p class="text-sm text-slate-400 mb-4">Each button type and color variant has a specific semantic purpose. Use the component and color that matches the action's intent.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-white/10">
                    <tr>
                        <th class="font-semibold text-slate-300 py-2.5 px-4">Component</th>
                        <th class="font-semibold text-slate-300 py-2.5 px-4">Role</th>
                        <th class="font-semibold text-slate-300 py-2.5 px-4 hidden md:table-cell">Usage Examples</th>
                        <th class="font-semibold text-slate-300 py-2.5 px-4">Preview</th>
                    </tr>
                </thead>
                <tbody class="text-slate-400">
                    <tr class="border-b border-white/5">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">primary (blue)</code></td>
                        <td class="py-2.5 px-4 text-slate-300">Primary CTA</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">Save, Submit, Advance matchday</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded-sm bg-accent-blue"></span></td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">primary (green)</code></td>
                        <td class="py-2.5 px-4 text-slate-300">Success / Confirm</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">Accept offer, Renew contract</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded-sm bg-accent-green"></span></td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">primary (amber)</code></td>
                        <td class="py-2.5 px-4 text-slate-300">Warning / Caution</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">Submit bid, Pre-contract offer</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded-sm bg-accent-gold"></span></td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">danger</code></td>
                        <td class="py-2.5 px-4 text-slate-300">Destructive</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">Delete account, Release player</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded-sm bg-accent-red"></span></td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">secondary</code></td>
                        <td class="py-2.5 px-4 text-slate-300">Secondary / Cancel</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">Cancel, Close, Back</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded-sm bg-surface-700 border border-white/10"></span></td>
                    </tr>
                    <tr>
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">ghost</code></td>
                        <td class="py-2.5 px-4 text-slate-300">Tertiary / Inline</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">View details, Toggle, Dismiss</td>
                        <td class="py-2.5 px-4"><span class="text-xs text-accent-blue">Text only</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
