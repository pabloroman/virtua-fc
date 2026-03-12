<section id="modals" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-white mb-2">Modals</h2>
    <p class="text-sm text-slate-400 mb-8">Full-featured modal component with Alpine.js. Includes focus management, escape-to-close, body scroll lock, and smooth scale transitions. The modal panel uses <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">bg-surface-800</code> with a subtle <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">border-white/10</code> edge.</p>

    {{-- Interactive Demo --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Interactive Demo</h3>
        <p class="text-sm text-slate-400 mb-4">Click the buttons below to open modals. Press Escape or click the backdrop to close.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-3">
            <div class="flex flex-wrap gap-3">
                <x-primary-button type="button" color="blue" @click="$dispatch('open-modal', 'ds-demo-modal')">Open Modal</x-primary-button>
                <x-secondary-button type="button" @click="$dispatch('open-modal', 'ds-demo-modal-sm')">Small Modal</x-secondary-button>
            </div>
        </div>

        {{-- Modal instances --}}
        <x-modal name="ds-demo-modal" maxWidth="2xl">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-white mb-2">Modal Title</h3>
                <p class="text-sm text-slate-400 mb-6">This is a demo modal with the default 2xl max-width. It supports focus trapping, escape-to-close, and backdrop click to close. The panel is styled with <code class="text-xs bg-surface-700 px-1 py-0.5 rounded-sm text-slate-300">bg-surface-800</code>.</p>
                <div class="flex justify-end gap-3">
                    <x-secondary-button type="button" @click="$dispatch('close-modal', 'ds-demo-modal')">Cancel</x-secondary-button>
                    <x-primary-button type="button" color="blue" @click="$dispatch('close-modal', 'ds-demo-modal')">Confirm</x-primary-button>
                </div>
            </div>
        </x-modal>

        <x-modal name="ds-demo-modal-sm" maxWidth="sm">
            <div class="p-6 text-center">
                <h3 class="text-xl font-semibold text-white mb-2">Small Modal</h3>
                <p class="text-sm text-slate-400 mb-6">A compact modal using maxWidth="sm".</p>
                <x-primary-button type="button" color="blue" @click="$dispatch('close-modal', 'ds-demo-modal-sm')">Got it</x-primary-button>
            </div>
        </x-modal>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">{{-- Trigger --}}
&lt;x-primary-button @click="$dispatch('open-modal', 'confirm-delete')"&gt;
    Delete Player
&lt;/x-primary-button&gt;

{{-- Modal --}}
&lt;x-modal name="confirm-delete" maxWidth="md"&gt;
    &lt;div class="p-6"&gt;
        &lt;h3 class="text-xl font-semibold text-white mb-2"&gt;Confirm Delete&lt;/h3&gt;
        &lt;p class="text-sm text-slate-400 mb-6"&gt;Are you sure?&lt;/p&gt;
        &lt;div class="flex justify-end gap-3"&gt;
            &lt;x-secondary-button @click="$dispatch('close-modal', 'confirm-delete')"&gt;Cancel&lt;/x-secondary-button&gt;
            &lt;x-primary-button color="red"&gt;Delete&lt;/x-primary-button&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/x-modal&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-white/10">
                    <tr>
                        <th class="font-semibold py-2 pr-4 text-slate-300">Prop</th>
                        <th class="font-semibold py-2 pr-4 text-slate-300">Type</th>
                        <th class="font-semibold py-2 pr-4 text-slate-300">Default</th>
                        <th class="font-semibold py-2 text-slate-300">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">name</td>
                        <td class="py-2 pr-4 text-slate-400">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-slate-400">required</td>
                        <td class="py-2 text-slate-400">Unique identifier for open/close events</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">show</td>
                        <td class="py-2 pr-4 text-slate-400">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs text-slate-400">false</td>
                        <td class="py-2 text-slate-400">Initial visibility state</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">maxWidth</td>
                        <td class="py-2 pr-4 text-slate-400">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-slate-400">'2xl'</td>
                        <td class="py-2 text-slate-400">sm | md | lg | xl | 2xl | 3xl | 4xl</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">focusable</td>
                        <td class="py-2 pr-4 text-slate-400">attr</td>
                        <td class="py-2 pr-4 font-mono text-xs text-slate-400">&mdash;</td>
                        <td class="py-2 text-slate-400">Auto-focus first focusable element on open</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Events --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Events</h3>
        <p class="text-sm text-slate-400 mb-4">Use Alpine.js <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">$dispatch</code> to open and close modals by name.</p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-white/10">
                    <tr>
                        <th class="font-semibold py-2 pr-4 text-slate-300">Event</th>
                        <th class="font-semibold py-2 pr-4 text-slate-300">Detail</th>
                        <th class="font-semibold py-2 text-slate-300">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">open-modal</td>
                        <td class="py-2 pr-4 text-slate-400">modal name</td>
                        <td class="py-2 text-slate-400">Opens the modal with matching name</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">close-modal</td>
                        <td class="py-2 pr-4 text-slate-400">modal name</td>
                        <td class="py-2 text-slate-400">Closes the modal with matching name</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Usage Patterns --}}
    <div>
        <h3 class="text-lg font-semibold text-white mb-2">Usage Patterns</h3>
        <p class="text-sm text-slate-400 mb-4">Common modal content patterns in the dark theme. Always use <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">text-white</code> for titles and <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">text-slate-400</code> for descriptions inside modal panels.</p>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">{{-- Confirmation modal --}}
&lt;x-modal name="confirm-action" maxWidth="md"&gt;
    &lt;div class="p-6"&gt;
        &lt;h3 class="text-xl font-semibold text-white mb-2"&gt;Title&lt;/h3&gt;
        &lt;p class="text-sm text-slate-400 mb-6"&gt;Description text.&lt;/p&gt;
        &lt;div class="flex justify-end gap-3"&gt;
            &lt;x-secondary-button @click="$dispatch('close-modal', 'confirm-action')"&gt;
                Cancel
            &lt;/x-secondary-button&gt;
            &lt;x-primary-button color="blue"&gt;Confirm&lt;/x-primary-button&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/x-modal&gt;

{{-- Form modal with focusable --}}
&lt;x-modal name="edit-form" maxWidth="lg" focusable&gt;
    &lt;form class="p-6"&gt;
        &lt;h3 class="text-xl font-semibold text-white mb-4"&gt;Edit Details&lt;/h3&gt;
        &lt;!-- Form fields --&gt;
        &lt;div class="flex justify-end gap-3 mt-6"&gt;
            &lt;x-secondary-button type="button" @click="$dispatch('close-modal', 'edit-form')"&gt;
                Cancel
            &lt;/x-secondary-button&gt;
            &lt;x-primary-button color="green"&gt;Save&lt;/x-primary-button&gt;
        &lt;/div&gt;
    &lt;/form&gt;
&lt;/x-modal&gt;</code></pre>
        </div>
    </div>
</section>
