<section id="modals" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Modals</h2>
    <p class="text-sm text-text-secondary mb-8">Full-featured modal component with Alpine.js. Includes focus management, escape-to-close, body scroll lock, and smooth scale transitions. The modal panel uses <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">bg-surface-800</code> with a subtle <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">border-border-strong</code> edge.</p>

    {{-- Interactive Demo --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Interactive Demo</h3>
        <p class="text-sm text-text-secondary mb-4">Click the buttons below to open modals. Press Escape or click the backdrop to close.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="flex flex-wrap gap-3">
                <x-primary-button type="button" color="blue" @click="$dispatch('open-modal', 'ds-demo-modal')">Open Modal</x-primary-button>
                <x-secondary-button type="button" @click="$dispatch('open-modal', 'ds-demo-modal-sm')">Small Modal</x-secondary-button>
            </div>
        </div>

        {{-- Modal instances --}}
        <x-modal name="ds-demo-modal" maxWidth="2xl">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-text-primary mb-2">Modal Title</h3>
                <p class="text-sm text-text-secondary mb-6">This is a demo modal with the default 2xl max-width. It supports focus trapping, escape-to-close, and backdrop click to close. The panel is styled with <code class="text-xs bg-surface-700 px-1 py-0.5 rounded-sm text-text-body">bg-surface-800</code>.</p>
                <div class="flex justify-end gap-3">
                    <x-secondary-button type="button" @click="$dispatch('close-modal', 'ds-demo-modal')">Cancel</x-secondary-button>
                    <x-primary-button type="button" color="blue" @click="$dispatch('close-modal', 'ds-demo-modal')">Confirm</x-primary-button>
                </div>
            </div>
        </x-modal>

        <x-modal name="ds-demo-modal-sm" maxWidth="sm">
            <div class="p-6 text-center">
                <h3 class="text-xl font-semibold text-text-primary mb-2">Small Modal</h3>
                <p class="text-sm text-text-secondary mb-6">A compact modal using maxWidth="sm".</p>
                <x-primary-button type="button" color="blue" @click="$dispatch('close-modal', 'ds-demo-modal-sm')">Got it</x-primary-button>
            </div>
        </x-modal>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">{{-- Trigger --}}
&lt;x-primary-button @click="$dispatch('open-modal', 'confirm-delete')"&gt;
    Delete Player
&lt;/x-primary-button&gt;

{{-- Modal --}}
&lt;x-modal name="confirm-delete" maxWidth="md"&gt;
    &lt;div class="p-6"&gt;
        &lt;h3 class="text-xl font-semibold text-text-primary mb-2"&gt;Confirm Delete&lt;/h3&gt;
        &lt;p class="text-sm text-text-secondary mb-6"&gt;Are you sure?&lt;/p&gt;
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
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold py-2 pr-4 text-text-body">Prop</th>
                        <th class="font-semibold py-2 pr-4 text-text-body">Type</th>
                        <th class="font-semibold py-2 pr-4 text-text-body">Default</th>
                        <th class="font-semibold py-2 text-text-body">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">name</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">required</td>
                        <td class="py-2 text-text-secondary">Unique identifier for open/close events</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">show</td>
                        <td class="py-2 pr-4 text-text-secondary">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">false</td>
                        <td class="py-2 text-text-secondary">Initial visibility state</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">maxWidth</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">'2xl'</td>
                        <td class="py-2 text-text-secondary">sm | md | lg | xl | 2xl | 3xl | 4xl</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">focusable</td>
                        <td class="py-2 pr-4 text-text-secondary">attr</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">&mdash;</td>
                        <td class="py-2 text-text-secondary">Auto-focus first focusable element on open</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Events --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Events</h3>
        <p class="text-sm text-text-secondary mb-4">Use Alpine.js <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">$dispatch</code> to open and close modals by name.</p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold py-2 pr-4 text-text-body">Event</th>
                        <th class="font-semibold py-2 pr-4 text-text-body">Detail</th>
                        <th class="font-semibold py-2 text-text-body">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">open-modal</td>
                        <td class="py-2 pr-4 text-text-secondary">modal name</td>
                        <td class="py-2 text-text-secondary">Opens the modal with matching name</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">close-modal</td>
                        <td class="py-2 pr-4 text-text-secondary">modal name</td>
                        <td class="py-2 text-text-secondary">Closes the modal with matching name</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Header Component --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Modal Header Component</h3>
        <p class="text-sm text-text-secondary mb-4">Use <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">&lt;x-modal-header&gt;</code> for a consistent header with title and close button. The header sits outside the scrollable body so it stays fixed at the top.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="flex flex-wrap gap-3">
                <x-primary-button type="button" color="blue" @click="$dispatch('open-modal', 'ds-demo-panel')">Open Panel Modal</x-primary-button>
            </div>
        </div>

        <x-modal name="ds-demo-panel" maxWidth="lg">
            <x-modal-header modal-name="ds-demo-panel">Panel Title</x-modal-header>
            <div class="p-5 max-h-[80vh] overflow-y-auto">
                <p class="text-sm text-text-secondary mb-4">This modal uses <code class="text-xs bg-surface-700 px-1 py-0.5 rounded-sm text-text-body">&lt;x-modal-header&gt;</code> for the fixed header and a scrollable body container.</p>
                <div class="bg-surface-700/50 border border-border-strong rounded-lg p-4">
                    <p class="text-sm text-text-secondary">Content goes here. The header stays pinned while this area scrolls.</p>
                </div>
            </div>
        </x-modal>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.modalHeaderCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="modalHeaderCode">&lt;x-modal name="my-panel" maxWidth="lg"&gt;
    &lt;x-modal-header modal-name="my-panel"&gt;Panel Title&lt;/x-modal-header&gt;
    &lt;div class="p-5 max-h-[80vh] overflow-y-auto"&gt;
        &lt;!-- Scrollable content --&gt;
    &lt;/div&gt;
&lt;/x-modal&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold py-2 pr-4 text-text-body">Prop</th>
                        <th class="font-semibold py-2 pr-4 text-text-body">Type</th>
                        <th class="font-semibold py-2 pr-4 text-text-body">Default</th>
                        <th class="font-semibold py-2 text-text-body">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">modal-name</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">required</td>
                        <td class="py-2 text-text-secondary">Name of the parent modal (used to dispatch close event)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Usage Patterns --}}
    <div>
        <h3 class="text-lg font-semibold text-text-primary mb-2">Usage Patterns</h3>
        <p class="text-sm text-text-secondary mb-4">Common modal content patterns. Use <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">&lt;x-modal-header&gt;</code> for panel/detail modals with scrollable content. Use inline headers for simple confirmation dialogs.</p>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">{{-- Panel modal (scrollable content with fixed header) --}}
&lt;x-modal name="report-panel" maxWidth="lg"&gt;
    &lt;x-modal-header modal-name="report-panel"&gt;Report Title&lt;/x-modal-header&gt;
    &lt;div class="p-5 max-h-[80vh] overflow-y-auto"&gt;
        &lt;!-- Scrollable content --&gt;
    &lt;/div&gt;
&lt;/x-modal&gt;

{{-- Confirmation modal (simple, no header component needed) --}}
&lt;x-modal name="confirm-action" maxWidth="md"&gt;
    &lt;div class="p-6"&gt;
        &lt;h3 class="text-xl font-semibold text-text-primary mb-2"&gt;Title&lt;/h3&gt;
        &lt;p class="text-sm text-text-secondary mb-6"&gt;Description text.&lt;/p&gt;
        &lt;div class="flex justify-end gap-3"&gt;
            &lt;x-secondary-button @click="$dispatch('close-modal', 'confirm-action')"&gt;
                Cancel
            &lt;/x-secondary-button&gt;
            &lt;x-primary-button color="blue"&gt;Confirm&lt;/x-primary-button&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/x-modal&gt;</code></pre>
        </div>
    </div>
</section>
