<section id="buttons" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Buttons</h2>
    <p class="text-sm text-text-secondary mb-10">All buttons use Blade components with a minimum height of 44px for touch accessibility, <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">rounded-lg</code> corners, and smooth transitions. Focus rings use <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">focus:ring-offset-surface-900</code> to match the dark background. <strong class="text-text-body">Every button in the app must use a component &mdash; no inline buttons allowed.</strong></p>

    {{-- ================================================================== --}}
    {{-- PRIMARY BUTTON --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Primary Button</h3>
        <p class="text-sm text-text-secondary mb-4">The main call-to-action. Defaults to accent-blue. Seven color variants available for semantic differentiation. Defaults to <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">type="submit"</code>.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-4">
            <div class="flex flex-wrap gap-3">
                <x-primary-button type="button" color="blue">Blue (default)</x-primary-button>
                <x-primary-button type="button" color="red">Red</x-primary-button>
                <x-primary-button type="button" color="green">Green</x-primary-button>
                <x-primary-button type="button" color="amber">Amber</x-primary-button>
                <x-primary-button type="button" color="sky">Sky</x-primary-button>
                <x-primary-button type="button" color="teal">Teal</x-primary-button>
                <x-primary-button type="button" color="emerald">Emerald</x-primary-button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-primary-button&gt;Save&lt;/x-primary-button&gt;
&lt;x-primary-button color="red"&gt;Delete&lt;/x-primary-button&gt;
&lt;x-primary-button color="green"&gt;Confirm&lt;/x-primary-button&gt;
&lt;x-primary-button color="amber"&gt;Warning&lt;/x-primary-button&gt;
&lt;x-primary-button color="teal"&gt;Track&lt;/x-primary-button&gt;
&lt;x-primary-button color="emerald"&gt;Confirm Squad&lt;/x-primary-button&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Default</th>
                        <th class="font-semibold text-text-body py-2">Options</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4"><code class="text-[10px] text-accent-blue">color</code></td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4"><code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">'blue'</code></td>
                        <td class="py-2">blue | red | green | amber | sky | teal | emerald</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4"><code class="text-[10px] text-accent-blue">size</code></td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4"><code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">'default'</code></td>
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
        <h3 class="text-lg font-semibold text-text-primary mb-2">Secondary Button</h3>
        <p class="text-sm text-text-secondary mb-4">Used for secondary actions. Surface background with a subtle border. Text lightens on hover.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-4">
            <div class="flex flex-wrap gap-3">
                <x-secondary-button>Cancel</x-secondary-button>
                <x-secondary-button size="xs">Small</x-secondary-button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-secondary-button&gt;Cancel&lt;/x-secondary-button&gt;
&lt;x-secondary-button size="xs"&gt;Small&lt;/x-secondary-button&gt;</code></pre>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- DANGER BUTTON --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Danger Button</h3>
        <p class="text-sm text-text-secondary mb-4">For destructive actions like deleting accounts or removing players. Uses accent-red background.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-4">
            <x-danger-button>Delete Account</x-danger-button>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-danger-button&gt;Delete Account&lt;/x-danger-button&gt;</code></pre>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- GHOST BUTTON --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Ghost Button</h3>
        <p class="text-sm text-text-secondary mb-4">Text-only buttons with no background. Shows a subtle tinted background on hover. Five color variants for different contexts.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-4">
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
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-ghost-button&gt;View Details&lt;/x-ghost-button&gt;
&lt;x-ghost-button color="red"&gt;Remove&lt;/x-ghost-button&gt;
&lt;x-ghost-button color="slate"&gt;Dismiss&lt;/x-ghost-button&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Default</th>
                        <th class="font-semibold text-text-body py-2">Options</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4"><code class="text-[10px] text-accent-blue">color</code></td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4"><code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">'blue'</code></td>
                        <td class="py-2">blue | red | amber | green | slate</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4"><code class="text-[10px] text-accent-blue">size</code></td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4"><code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">'default'</code></td>
                        <td class="py-2">default | xs</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- ICON BUTTON --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Icon Button</h3>
        <p class="text-sm text-text-secondary mb-4">Square touch-target button for icon-only actions (close, dismiss, expand). Default size provides 44&times;44px minimum. The <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">sm</code> size removes minimum dimensions for compact contexts like table rows.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-4">
            <div class="flex flex-wrap items-center gap-4">
                <div class="text-center space-y-2">
                    <x-icon-button>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </x-icon-button>
                    <div class="text-[10px] text-text-muted">default</div>
                </div>
                <div class="text-center space-y-2">
                    <x-icon-button size="sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </x-icon-button>
                    <div class="text-[10px] text-text-muted">size="sm"</div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-icon-button&gt;
    &lt;svg class="w-5 h-5" ...&gt;...&lt;/svg&gt;
&lt;/x-icon-button&gt;

&lt;x-icon-button size="sm"&gt;
    &lt;svg class="w-4 h-4" ...&gt;...&lt;/svg&gt;
&lt;/x-icon-button&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Default</th>
                        <th class="font-semibold text-text-body py-2">Options</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr>
                        <td class="py-2 pr-4"><code class="text-[10px] text-accent-blue">size</code></td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4"><code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">'default'</code></td>
                        <td class="py-2">default (44&times;44px) | sm (compact)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- TAB BUTTON --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Tab Button</h3>
        <p class="text-sm text-text-secondary mb-4">Navigation tabs with a bottom border indicator. Active state is applied via Alpine.js <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">:class</code> binding. Used for section switching within a page (e.g., Swiss standings league/knockout toggle, competition group/knockout views).</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-4">
            <div x-data="{ tab: 'league' }" class="flex gap-1 border-b border-border-default">
                <x-tab-button @click="tab = 'league'" x-bind:class="tab === 'league' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-muted hover:text-text-body'">League</x-tab-button>
                <x-tab-button @click="tab = 'knockout'" x-bind:class="tab === 'knockout' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-muted hover:text-text-body'">Knockout</x-tab-button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div x-data="{ tab: 'league' }" class="flex gap-1 border-b border-border-default"&gt;
    &lt;x-tab-button @click="tab = 'league'"
        :class="tab === 'league' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-muted'"&gt;
        League
    &lt;/x-tab-button&gt;
    &lt;x-tab-button @click="tab = 'knockout'"
        :class="tab === 'knockout' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-muted'"&gt;
        Knockout
    &lt;/x-tab-button&gt;
&lt;/div&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Default</th>
                        <th class="font-semibold text-text-body py-2">Options</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr>
                        <td class="py-2 pr-4"><code class="text-[10px] text-accent-blue">size</code></td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4"><code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">'default'</code></td>
                        <td class="py-2">default | xs</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- PILL BUTTON --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Pill Button</h3>
        <p class="text-sm text-text-secondary mb-4">Compact toggle buttons used in groups (view modes, formation options, speed controls). No built-in color &mdash; apply active state colors via Alpine.js <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">:class</code> binding.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-4">
            <div x-data="{ mode: 'tactical' }" class="flex gap-1.5">
                <x-pill-button size="xs" @click="mode = 'tactical'" x-bind:class="mode === 'tactical' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-text-body'">Tactical</x-pill-button>
                <x-pill-button size="xs" @click="mode = 'stats'" x-bind:class="mode === 'stats' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-text-body'">Stats</x-pill-button>
                <x-pill-button size="xs" @click="mode = 'planning'" x-bind:class="mode === 'planning' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-text-body'">Planning</x-pill-button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-pill-button size="xs" @click="mode = 'tactical'"
    :class="mode === 'tactical' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary'"&gt;
    Tactical
&lt;/x-pill-button&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Default</th>
                        <th class="font-semibold text-text-body py-2">Options</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr>
                        <td class="py-2 pr-4"><code class="text-[10px] text-accent-blue">size</code></td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4"><code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">'default'</code></td>
                        <td class="py-2">default | xs | sm</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- ACTION BUTTON --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Action Button</h3>
        <p class="text-sm text-text-secondary mb-4">Tinted outline buttons for contextual actions in cards and detail panels. Uses a subtle colored background with matching border and text.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-4">
            <div class="flex flex-wrap gap-3">
                <x-action-button color="blue">View Details</x-action-button>
                <x-action-button color="green">Accept</x-action-button>
                <x-action-button color="red">Reject</x-action-button>
                <x-action-button color="amber">Counter</x-action-button>
                <x-action-button color="violet">Loan Out</x-action-button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-action-button color="blue"&gt;View Details&lt;/x-action-button&gt;
&lt;x-action-button color="red"&gt;Reject&lt;/x-action-button&gt;
&lt;x-action-button color="violet"&gt;Loan Out&lt;/x-action-button&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Default</th>
                        <th class="font-semibold text-text-body py-2">Options</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr>
                        <td class="py-2 pr-4"><code class="text-[10px] text-accent-blue">color</code></td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4"><code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">'blue'</code></td>
                        <td class="py-2">blue | green | red | amber | violet</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- BUTTON WITH SPINNER --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Button with Spinner</h3>
        <p class="text-sm text-text-secondary mb-4">Shows a loading spinner during form submission. Requires an Alpine.js <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">loading</code> state on the parent. The button auto-disables while loading.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-4" x-data="{ loading: false }">
            <div class="flex items-center gap-4">
                <x-primary-button-spin @click="loading = !loading">Submit</x-primary-button-spin>
                <span class="text-xs text-text-muted">Click to toggle loading state</span>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div x-data="{ loading: false }"&gt;
    &lt;form @submit="loading = true"&gt;
        &lt;x-primary-button-spin&gt;Submit&lt;/x-primary-button-spin&gt;
    &lt;/form&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- BUTTON AS LINK --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Button as Link</h3>
        <p class="text-sm text-text-secondary mb-4">Renders an <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">&lt;a&gt;</code> tag styled as a primary button. Use for navigation that should look like a button.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-4">
            <div class="flex flex-wrap gap-3">
                <x-primary-button-link href="#" color="blue">View Squad</x-primary-button-link>
                <x-primary-button-link href="#" color="green">Start Season</x-primary-button-link>
                <x-primary-button-link href="#" color="amber">View Finances</x-primary-button-link>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-primary-button-link href="&#123;&#123; route('squad') &#125;&#125;"&gt;View Squad&lt;/x-primary-button-link&gt;
&lt;x-primary-button-link href="#" color="green"&gt;Start Season&lt;/x-primary-button-link&gt;</code></pre>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- DISABLED STATES --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Disabled States</h3>
        <p class="text-sm text-text-secondary mb-4">All button components support a <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">disabled</code> attribute. Disabled buttons drop to 50% opacity and show a not-allowed cursor.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-4">
            <div class="flex flex-wrap gap-3">
                <x-primary-button disabled type="button">Disabled Primary</x-primary-button>
                <x-secondary-button disabled>Disabled Secondary</x-secondary-button>
                <x-danger-button disabled type="button">Disabled Danger</x-danger-button>
                <x-ghost-button disabled>Disabled Ghost</x-ghost-button>
                <x-icon-button disabled>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </x-icon-button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-primary-button disabled&gt;Disabled&lt;/x-primary-button&gt;
&lt;x-secondary-button disabled&gt;Disabled&lt;/x-secondary-button&gt;
&lt;x-icon-button disabled&gt;...&lt;/x-icon-button&gt;</code></pre>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- SIZE PATTERNS --}}
    {{-- ================================================================== --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Size Patterns</h3>
        <p class="text-sm text-text-secondary mb-4">Components support a <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">size</code> prop. For larger or full-width buttons, override with Tailwind classes.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-4">
            <div class="flex flex-wrap items-end gap-4">
                <div class="text-center space-y-2">
                    <x-primary-button type="button" size="xs">Extra Small</x-primary-button>
                    <div class="text-[10px] text-text-muted">size="xs"</div>
                </div>
                <div class="text-center space-y-2">
                    <x-primary-button type="button">Standard</x-primary-button>
                    <div class="text-[10px] text-text-muted">default</div>
                </div>
                <div class="text-center space-y-2">
                    <x-primary-button type="button" class="px-6 py-3">Large</x-primary-button>
                    <div class="text-[10px] text-text-muted">class="px-6 py-3"</div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-border-default">
                <x-primary-button type="button" class="w-full">Full Width Button</x-primary-button>
                <div class="text-[10px] text-text-muted mt-2 text-center">class="w-full"</div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-primary-button size="xs"&gt;Extra Small&lt;/x-primary-button&gt;
&lt;x-primary-button&gt;Standard&lt;/x-primary-button&gt;
&lt;x-primary-button class="px-6 py-3"&gt;Large&lt;/x-primary-button&gt;
&lt;x-primary-button class="w-full"&gt;Full Width&lt;/x-primary-button&gt;</code></pre>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- SEMANTIC ROLES --}}
    {{-- ================================================================== --}}
    <div>
        <h3 class="text-lg font-semibold text-text-primary mb-2">Semantic Roles</h3>
        <p class="text-sm text-text-secondary mb-4">Each button type and color variant has a specific semantic purpose. Use the component and color that matches the action's intent.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold text-text-body py-2.5 px-4">Component</th>
                        <th class="font-semibold text-text-body py-2.5 px-4">Role</th>
                        <th class="font-semibold text-text-body py-2.5 px-4 hidden md:table-cell">Usage Examples</th>
                        <th class="font-semibold text-text-body py-2.5 px-4">Preview</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr class="border-b border-border-default">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">primary (blue)</code></td>
                        <td class="py-2.5 px-4 text-text-body">Primary CTA</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">Save, Submit, Advance matchday</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded-sm bg-accent-blue"></span></td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">primary (green)</code></td>
                        <td class="py-2.5 px-4 text-text-body">Success / Confirm</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">Accept offer, Renew contract</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded-sm bg-accent-green"></span></td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">primary (amber)</code></td>
                        <td class="py-2.5 px-4 text-text-body">Warning / Caution</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">Submit bid, Pre-contract offer</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded-sm bg-accent-gold"></span></td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">primary (emerald)</code></td>
                        <td class="py-2.5 px-4 text-text-body">Positive action</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">Confirm squad, Complete setup</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded-sm bg-emerald-600"></span></td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">primary (teal)</code></td>
                        <td class="py-2.5 px-4 text-text-body">Tracking / Scout</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">Start tracking, Tutorial CTA</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded-sm bg-teal-600"></span></td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">danger</code></td>
                        <td class="py-2.5 px-4 text-text-body">Destructive</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">Delete account, Release player</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded-sm bg-accent-red"></span></td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">secondary</code></td>
                        <td class="py-2.5 px-4 text-text-body">Secondary / Cancel</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">Cancel, Close, Back</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-16 h-6 rounded-sm bg-surface-700 border border-border-strong"></span></td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">ghost</code></td>
                        <td class="py-2.5 px-4 text-text-body">Tertiary / Inline</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">View details, Toggle, Dismiss</td>
                        <td class="py-2.5 px-4"><span class="text-xs text-accent-blue">Text only</span></td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">icon-button</code></td>
                        <td class="py-2.5 px-4 text-text-body">Icon-only action</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">Close modal, Delete, Expand detail</td>
                        <td class="py-2.5 px-4"><span class="inline-block w-8 h-8 rounded-sm bg-surface-700 border border-border-default"></span></td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">tab-button</code></td>
                        <td class="py-2.5 px-4 text-text-body">Section switcher</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">League/Knockout tabs, Mobile panels</td>
                        <td class="py-2.5 px-4"><span class="text-xs text-accent-blue border-b-2 border-accent-blue pb-1">Tab</span></td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">pill-button</code></td>
                        <td class="py-2.5 px-4 text-text-body">Group toggle</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">View mode, Formation, Speed control</td>
                        <td class="py-2.5 px-4"><span class="inline-block px-2 py-0.5 text-xs rounded-md bg-accent-blue text-white">Pill</span></td>
                    </tr>
                    <tr>
                        <td class="py-2.5 px-4"><code class="text-[10px] text-accent-blue">action-button</code></td>
                        <td class="py-2.5 px-4 text-text-body">Contextual action</td>
                        <td class="py-2.5 px-4 hidden md:table-cell">View scout results, Accept/Reject offer</td>
                        <td class="py-2.5 px-4"><span class="inline-block px-2 py-0.5 text-xs rounded-md bg-accent-blue/10 text-accent-blue border border-accent-blue/20">Action</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
