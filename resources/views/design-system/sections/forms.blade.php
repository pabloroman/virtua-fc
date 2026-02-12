<section id="forms" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Forms</h2>
    <p class="text-slate-500 mb-8">Form components use sky-500 focus rings, slate-300 borders, and rounded-lg corners for a consistent input experience.</p>

    {{-- Text Input --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Text Input</h3>
        <p class="text-sm text-slate-500 mb-4">Standard text input with focus ring. Supports all HTML input attributes.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3 space-y-4">
            <div class="max-w-sm">
                <x-input-label for="demo-input" value="Label" />
                <x-text-input id="demo-input" type="text" class="mt-1 block w-full" placeholder="Placeholder text" />
            </div>
            <div class="max-w-sm">
                <x-input-label for="demo-disabled" value="Disabled" />
                <x-text-input id="demo-disabled" type="text" class="mt-1 block w-full" value="Disabled input" disabled />
            </div>
            <div class="max-w-sm">
                <x-input-label for="demo-readonly" value="Read-only" />
                <x-text-input id="demo-readonly" type="text" class="mt-1 block w-full" value="Read-only input" readonly />
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-input-label for="name" value="Player Name" /&gt;
&lt;x-text-input id="name" type="text" class="mt-1 block w-full" /&gt;
&lt;x-input-error :messages="$errors-&gt;get('name')" class="mt-2" /&gt;</code></pre>
        </div>
    </div>

    {{-- Select Input --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Select Input</h3>
        <p class="text-sm text-slate-500 mb-4">Same styling as text inputs. Supports disabled state.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="max-w-sm">
                <x-input-label for="demo-select" value="Formation" />
                <x-select-input id="demo-select" class="mt-1 block w-full">
                    <option value="">Select formation...</option>
                    <option value="442">4-4-2</option>
                    <option value="433">4-3-3</option>
                    <option value="4231">4-2-3-1</option>
                    <option value="352">3-5-2</option>
                </x-select-input>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-input-label for="formation" value="Formation" /&gt;
&lt;x-select-input id="formation" class="mt-1 block w-full"&gt;
    &lt;option value="442"&gt;4-4-2&lt;/option&gt;
    &lt;option value="433"&gt;4-3-3&lt;/option&gt;
&lt;/x-select-input&gt;</code></pre>
        </div>
    </div>

    {{-- Checkbox --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Checkbox</h3>
        <p class="text-sm text-slate-500 mb-4">Sky-600 checkmark color with sky-500 focus ring.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="space-y-3">
                <label class="flex items-center gap-2 cursor-pointer">
                    <x-checkbox-input name="demo-check-1" checked />
                    <span class="text-sm text-slate-700">Auto-select best lineup</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <x-checkbox-input name="demo-check-2" />
                    <span class="text-sm text-slate-700">Include youth players</span>
                </label>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;label class="flex items-center gap-2 cursor-pointer"&gt;
    &lt;x-checkbox-input name="auto_lineup" /&gt;
    &lt;span class="text-sm text-slate--700"&gt;Auto-select best lineup&lt;/span&gt;
&lt;/label&gt;</code></pre>
        </div>
    </div>

    {{-- Input Error --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Input Error</h3>
        <p class="text-sm text-slate-500 mb-4">Displays validation error messages below form fields.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="max-w-sm">
                <x-input-label for="demo-error" value="Email" />
                <x-text-input id="demo-error" type="email" class="mt-1 block w-full border-red-300 focus:border-red-500 focus:ring-red-500" value="invalid-email" />
                <p class="mt-2 text-sm text-red-600">The email field must be a valid email address.</p>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-input-error :messages="$errors-&gt;get('email')" class="mt-2" /&gt;</code></pre>
        </div>
    </div>

    {{-- Complete Form Group --}}
    <div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Complete Form Group</h3>
        <p class="text-sm text-slate-500 mb-4">Standard form pattern composing label, input, and error components together.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
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
                    <label class="flex items-center gap-2 cursor-pointer">
                        <x-checkbox-input name="form-check" checked />
                        <span class="text-sm text-slate-700">I accept the terms</span>
                    </label>
                </div>
                <div>
                    <x-primary-button>Create Game</x-primary-button>
                </div>
            </div>
        </div>
    </div>
</section>
