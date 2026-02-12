<section id="modals" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Modals</h2>
    <p class="text-slate-500 mb-8">Full-featured modal component with Alpine.js. Includes focus management, escape-to-close, body scroll lock, and smooth scale transitions.</p>

    {{-- Interactive Demo --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Interactive Demo</h3>
        <p class="text-sm text-slate-500 mb-4">Click the button below to open a modal. Press Escape or click the backdrop to close.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="flex flex-wrap gap-3">
                <x-primary-button type="button" color="sky" @click="$dispatch('open-modal', 'demo-modal')">Open Modal</x-primary-button>
                <x-secondary-button type="button" @click="$dispatch('open-modal', 'demo-modal-sm')">Small Modal</x-secondary-button>
            </div>
        </div>

        {{-- Modal instances --}}
        <x-modal name="demo-modal" maxWidth="2xl">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-slate-900 mb-2">Modal Title</h3>
                <p class="text-sm text-slate-500 mb-6">This is a demo modal with the default 2xl max-width. It supports focus trapping, escape-to-close, and backdrop click to close.</p>
                <div class="flex justify-end gap-3">
                    <x-secondary-button type="button" @click="$dispatch('close-modal', 'demo-modal')">Cancel</x-secondary-button>
                    <x-primary-button type="button" @click="$dispatch('close-modal', 'demo-modal')">Confirm</x-primary-button>
                </div>
            </div>
        </x-modal>

        <x-modal name="demo-modal-sm" maxWidth="sm">
            <div class="p-6 text-center">
                <h3 class="text-xl font-semibold text-slate-900 mb-2">Small Modal</h3>
                <p class="text-sm text-slate-500 mb-6">A compact modal using maxWidth="sm".</p>
                <x-primary-button type="button" color="sky" @click="$dispatch('close-modal', 'demo-modal-sm')">Got it</x-primary-button>
            </div>
        </x-modal>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">{{-- Trigger --}}
&lt;x-primary-button @click="$dispatch('open-modal', 'confirm-delete')"&gt;
    Delete Player
&lt;/x-primary-button&gt;

{{-- Modal --}}
&lt;x-modal name="confirm-delete" maxWidth="md"&gt;
    &lt;div class="p-6"&gt;
        &lt;h3 class="text-xl font-semibold text-slate-900 mb-2"&gt;Confirm Delete&lt;/h3&gt;
        &lt;p class="text-sm text-slate-500 mb-6"&gt;Are you sure?&lt;/p&gt;
        &lt;div class="flex justify-end gap-3"&gt;
            &lt;x-secondary-button @click="$dispatch('close-modal', 'confirm-delete')"&gt;Cancel&lt;/x-secondary-button&gt;
            &lt;x-danger-button&gt;Delete&lt;/x-danger-button&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/x-modal&gt;</code></pre>
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
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">name</td>
                        <td class="py-2 pr-4 text-slate-500">string</td>
                        <td class="py-2 pr-4 font-mono text-xs">required</td>
                        <td class="py-2 text-slate-500">Unique identifier for open/close events</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">show</td>
                        <td class="py-2 pr-4 text-slate-500">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs">false</td>
                        <td class="py-2 text-slate-500">Initial visibility state</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">maxWidth</td>
                        <td class="py-2 pr-4 text-slate-500">string</td>
                        <td class="py-2 pr-4 font-mono text-xs">'2xl'</td>
                        <td class="py-2 text-slate-500">sm | md | lg | xl | 2xl</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">focusable</td>
                        <td class="py-2 pr-4 text-slate-500">attr</td>
                        <td class="py-2 pr-4 font-mono text-xs">-</td>
                        <td class="py-2 text-slate-500">Auto-focus first focusable element on open</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Events --}}
    <div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Events</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-slate-200">
                    <tr>
                        <th class="font-semibold py-2 pr-4">Event</th>
                        <th class="font-semibold py-2 pr-4">Detail</th>
                        <th class="font-semibold py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">open-modal</td>
                        <td class="py-2 pr-4 text-slate-500">modal name</td>
                        <td class="py-2 text-slate-500">Opens the modal with matching name</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">close-modal</td>
                        <td class="py-2 pr-4 text-slate-500">modal name</td>
                        <td class="py-2 text-slate-500">Closes the modal with matching name</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
