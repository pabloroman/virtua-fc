<section id="forms" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Forms</h2>
    <p class="text-sm text-text-secondary mb-8">Form components use dark surfaces with <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">accent-blue</code> focus rings, <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">border-border-strong</code> borders, and <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">rounded-lg</code> corners for a consistent dark-themed input experience.</p>

    {{-- Text Input --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Text Input</h3>
        <p class="text-sm text-text-secondary mb-4">Standard text input on dark surface with blue focus ring. Supports all HTML input attributes plus <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">disabled</code> and <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">readonly</code> states.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3 space-y-4">
            <div class="max-w-sm">
                <x-input-label for="demo-input" value="Player Name" />
                <x-text-input id="demo-input" type="text" class="mt-1 block w-full" placeholder="Enter player name..." />
            </div>
            <div class="max-w-sm">
                <x-input-label for="demo-disabled" value="Disabled" />
                <x-text-input id="demo-disabled" type="text" class="mt-1 block w-full opacity-50 cursor-not-allowed" value="Disabled input" disabled />
            </div>
            <div class="max-w-sm">
                <x-input-label for="demo-readonly" value="Read-only" />
                <x-text-input id="demo-readonly" type="text" class="mt-1 block w-full" value="Read-only input" readonly />
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-input-label for="name" value="Player Name" /&gt;
&lt;x-text-input id="name" type="text" class="mt-1 block w-full" placeholder="Enter player name..." /&gt;
&lt;x-input-error :messages="$errors-&gt;get('name')" class="mt-2" /&gt;</code></pre>
        </div>
    </div>

    {{-- Select Input --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Select Input</h3>
        <p class="text-sm text-text-secondary mb-4">Same dark styling as text inputs. Options inherit the dark background. Supports disabled state.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="max-w-sm space-y-4">
                <div>
                    <x-input-label for="demo-select" value="Formation" />
                    <x-select-input id="demo-select" class="mt-1 block w-full">
                        <option value="">Select formation...</option>
                        <option value="442">4-4-2</option>
                        <option value="433">4-3-3</option>
                        <option value="4231">4-2-3-1</option>
                        <option value="352">3-5-2</option>
                    </x-select-input>
                </div>
                <div>
                    <x-input-label for="demo-select-disabled" value="Disabled Select" />
                    <x-select-input id="demo-select-disabled" class="mt-1 block w-full opacity-50 cursor-not-allowed" disabled>
                        <option value="442">4-4-2</option>
                    </x-select-input>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-input-label for="formation" value="Formation" /&gt;
&lt;x-select-input id="formation" class="mt-1 block w-full"&gt;
    &lt;option value=""&gt;Select formation...&lt;/option&gt;
    &lt;option value="442"&gt;4-4-2&lt;/option&gt;
    &lt;option value="433"&gt;4-3-3&lt;/option&gt;
&lt;/x-select-input&gt;</code></pre>
        </div>
    </div>

    {{-- Checkbox --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Checkbox</h3>
        <p class="text-sm text-text-secondary mb-4">Dark-surfaced checkbox with <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">accent-blue</code> checkmark color and matching focus ring. Ring offset uses <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">surface-900</code> to blend with the dark background.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="space-y-3">
                <label class="flex items-center gap-2 cursor-pointer">
                    <x-checkbox-input name="demo-check-1" checked />
                    <span class="text-sm text-text-body">Auto-select best lineup</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <x-checkbox-input name="demo-check-2" />
                    <span class="text-sm text-text-body">Include youth players</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer opacity-50">
                    <x-checkbox-input name="demo-check-3" disabled />
                    <span class="text-sm text-text-body">Disabled checkbox</span>
                </label>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;label class="flex items-center gap-2 cursor-pointer"&gt;
    &lt;x-checkbox-input name="auto_lineup" /&gt;
    &lt;span class="text-sm text-text-body"&gt;Auto-select best lineup&lt;/span&gt;
&lt;/label&gt;</code></pre>
        </div>
    </div>

    {{-- Money Input --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Money Input</h3>
        <p class="text-sm text-text-secondary mb-4">Stepper-style money input with increment/decrement buttons. Supports hold-to-repeat and auto-adjusting step size (10K below 1M, 100K above). Available in <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">md</code> (default) and <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">sm</code> sizes.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3 space-y-4">
            <div>
                <x-input-label value="Transfer Budget (default)" class="mb-1" />
                <x-money-input name="demo-money" :value="5000000" :min="100000" />
            </div>
            <div>
                <x-input-label value="Bid Amount (small)" class="mb-1" />
                <x-money-input name="demo-money-sm" :value="500000" :min="10000" size="sm" />
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-money-input name="transfer_budget" :value="5000000" :min="100000" /&gt;
&lt;x-money-input name="bid_amount" :value="500000" :min="10000" size="sm" /&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto mt-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Default</th>
                        <th class="font-semibold text-text-body py-2">Description</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">name</td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4 font-mono text-xs">required</td>
                        <td class="py-2">Hidden input name for form submission</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">value</td>
                        <td class="py-2 pr-4">int</td>
                        <td class="py-2 pr-4 font-mono text-xs">0</td>
                        <td class="py-2">Initial value in euros</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">min</td>
                        <td class="py-2 pr-4">int</td>
                        <td class="py-2 pr-4 font-mono text-xs">0</td>
                        <td class="py-2">Minimum allowed value</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">size</td>
                        <td class="py-2 pr-4">string</td>
                        <td class="py-2 pr-4 font-mono text-xs">'md'</td>
                        <td class="py-2">md | sm</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Input Error --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Input Error</h3>
        <p class="text-sm text-text-secondary mb-4">Displays validation error messages below form fields in <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">accent-red</code>.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="max-w-sm">
                <x-input-label for="demo-error" value="Email" />
                <x-text-input id="demo-error" type="email" class="mt-1 block w-full border-accent-red/50 focus:border-accent-red focus:ring-accent-red" value="invalid-email" />
                <x-input-error :messages="['The email field must be a valid email address.']" class="mt-2" />
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-text-input class="border-accent-red/50 focus:border-accent-red focus:ring-accent-red" /&gt;
&lt;x-input-error :messages="$errors-&gt;get('email')" class="mt-2" /&gt;</code></pre>
        </div>
    </div>

    {{-- Search Input Pattern --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Search Input</h3>
        <p class="text-sm text-text-secondary mb-4">Compact search field with an icon prefix. Used in table toolbars and filter bars. Not a Blade component -- built inline with utility classes.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="max-w-xs">
                <div class="relative">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-text-muted pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text" placeholder="Search players..." class="w-full bg-surface-700 border border-border-default rounded-md text-xs text-text-primary placeholder-slate-500 pl-8 pr-3 py-1.5 focus:outline-hidden focus:border-accent-blue/50" />
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="relative"&gt;
    &lt;svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-text-muted pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"&gt;
        &lt;path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /&gt;
    &lt;/svg&gt;
    &lt;input type="text" placeholder="Search players..."
           class="w-full bg-surface-700 border border-border-default rounded-md text-xs text-text-primary placeholder-slate-500 pl-8 pr-3 py-1.5 focus:outline-hidden focus:border-accent-blue/50" /&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Complete Form Group --}}
    <div>
        <h3 class="text-lg font-semibold text-text-primary mb-2">Complete Form Group</h3>
        <p class="text-sm text-text-secondary mb-4">Standard form pattern composing label, input, select, checkbox, and error components together on a dark surface.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="max-w-md space-y-4">
                <div>
                    <x-input-label for="form-name" value="Team Name" />
                    <x-text-input id="form-name" type="text" class="mt-1 block w-full" value="Real Madrid CF" />
                </div>
                <div>
                    <x-input-label for="form-season" value="Season" />
                    <x-select-input id="form-season" class="mt-1 block w-full">
                        <option>2025/26</option>
                        <option>2026/27</option>
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Transfer Budget" class="mb-1" />
                    <x-money-input name="form-budget" :value="15000000" :min="1000000" />
                </div>
                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <x-checkbox-input name="form-check" checked />
                        <span class="text-sm text-text-body">I accept the terms</span>
                    </label>
                </div>
                <div>
                    <x-primary-button>Create Game</x-primary-button>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="space-y-4"&gt;
    &lt;div&gt;
        &lt;x-input-label for="name" value="Team Name" /&gt;
        &lt;x-text-input id="name" type="text" class="mt-1 block w-full" /&gt;
        &lt;x-input-error :messages="$errors-&gt;get('name')" class="mt-2" /&gt;
    &lt;/div&gt;
    &lt;div&gt;
        &lt;x-input-label for="season" value="Season" /&gt;
        &lt;x-select-input id="season" class="mt-1 block w-full"&gt;
            &lt;option&gt;2025/26&lt;/option&gt;
        &lt;/x-select-input&gt;
    &lt;/div&gt;
    &lt;div&gt;
        &lt;x-input-label value="Budget" class="mb-1" /&gt;
        &lt;x-money-input name="budget" :value="15000000" :min="1000000" /&gt;
    &lt;/div&gt;
    &lt;div&gt;
        &lt;label class="flex items-center gap-2 cursor-pointer"&gt;
            &lt;x-checkbox-input name="terms" /&gt;
            &lt;span class="text-sm text-text-body"&gt;I accept the terms&lt;/span&gt;
        &lt;/label&gt;
    &lt;/div&gt;
    &lt;x-primary-button&gt;Create Game&lt;/x-primary-button&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>
</section>
