<section id="alerts" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Alerts</h2>
    <p class="text-sm text-text-secondary mb-8">Alert patterns for flash messages, status banners, and inline notifications. Bold left-bar accents for clear visual hierarchy.</p>

    {{-- Flash Messages --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Flash Messages</h3>
        <p class="text-sm text-text-secondary mb-4">Used for session-based success/error feedback after form submissions. Left border accent with tinted dark backgrounds.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 space-y-3 mb-3">
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
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;!-- Session-based (self-checking — renders nothing if session key is absent) --&gt;
&lt;x-flash-message type="success" :message="session('success')" class="mb-4" /&gt;
&lt;x-flash-message type="error" :message="session('error')" class="mb-4" /&gt;
&lt;x-flash-message type="warning" :message="session('warning')" class="mb-4" /&gt;
&lt;x-flash-message type="info" :message="session('info')" class="mb-4" /&gt;

&lt;!-- Slot content (when you need custom markup or conditional wrapping) --&gt;
@@if($errors-&gt;has('limit'))
    &lt;x-flash-message type="error" class="mb-4"&gt;
        &#123;&#123; $errors-&gt;first('limit') &#125;&#125;
    &lt;/x-flash-message&gt;
@@endif</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto mb-6">
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
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">type</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">success</td>
                        <td class="py-2 text-text-secondary">success | error | warning | info</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">message</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">string|null</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">null</td>
                        <td class="py-2 text-text-secondary">Message text. If null and slot is empty, nothing renders.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Alert color reference --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold py-2 pr-4 text-text-body">Type</th>
                        <th class="font-semibold py-2 pr-4 text-text-body">Border</th>
                        <th class="font-semibold py-2 pr-4 text-text-body">Background</th>
                        <th class="font-semibold py-2 pr-4 text-text-body">Icon</th>
                        <th class="font-semibold py-2 text-text-body">Text</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-medium text-text-primary">Success</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-green">emerald-500</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-green">emerald-500/10</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-green">emerald-400</td>
                        <td class="py-2 font-mono text-xs text-accent-green">emerald-400</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-medium text-text-primary">Error</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-red">red-500</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-red">red-500/10</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-red">red-400</td>
                        <td class="py-2 font-mono text-xs text-accent-red">red-400</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-medium text-text-primary">Warning</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-gold">amber-500</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-gold">amber-500/10</td>
                        <td class="py-2 pr-4 font-mono text-xs text-accent-gold">amber-400</td>
                        <td class="py-2 font-mono text-xs text-accent-gold">amber-400</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-medium text-text-primary">Info</td>
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
        <h3 class="text-lg font-semibold text-text-primary mb-2">Banner Alerts</h3>
        <p class="text-sm text-text-secondary mb-4">Full-width banners for app-level notices. Used for beta mode and admin impersonation.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl overflow-hidden mb-3">
            <div class="bg-amber-500/10 border-b border-amber-500/20 text-amber-400 text-center text-sm py-1.5 px-4">
                <span class="font-semibold">BETA</span> &mdash; This game is in beta. Your progress may be reset.
            </div>
            <div class="bg-rose-500 text-white text-center text-sm py-1.5 px-4">
                Impersonating user: john@example.com &middot; <a href="#" class="underline font-semibold hover:text-rose-100">Stop</a>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">{{-- Beta banner --}}
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

    {{-- Status Banners --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Status Banners</h3>
        <p class="text-sm text-text-secondary mb-4">Contextual banners with icon, title, optional description, and action slot. Used for pre-season notices, action-required alerts, and other page-level status messages.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 space-y-3 mb-3">
            {{-- Warning with action --}}
            <x-status-banner color="gold" title="Action required">
                <x-slot name="icon">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </x-slot>
                <x-primary-button-link color="amber" href="#">
                    Go to lineup
                </x-primary-button-link>
            </x-status-banner>

            {{-- Info with description --}}
            <x-status-banner color="blue" title="Pre-season" description="The season starts on 15 Aug 2025. Prepare your squad and set your budget.">
                <x-slot name="icon">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </x-slot>
                <x-secondary-button>Skip pre-season</x-secondary-button>
            </x-status-banner>

            {{-- Success --}}
            <x-status-banner color="green" title="Transfer completed" description="Pedri has joined your squad.">
                <x-slot name="icon">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-slot>
            </x-status-banner>

            {{-- Error --}}
            <x-status-banner color="red" title="Squad too large" description="You must release or loan out 2 players before the deadline.">
                <x-slot name="icon">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-slot>
            </x-status-banner>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;!-- Warning banner with action button --&gt;
&lt;x-status-banner color="gold" :title="__('messages.action_required')"&gt;
    &lt;x-slot name="icon"&gt;
        &lt;svg class="w-5 h-5" ...&gt;...&lt;/svg&gt;
    &lt;/x-slot&gt;
    &lt;x-primary-button-link color="amber" :href="$url"&gt;Go&lt;/x-primary-button-link&gt;
&lt;/x-status-banner&gt;

&lt;!-- Info banner with title + description --&gt;
&lt;x-status-banner color="blue" :title="__('game.pre_season')" :description="__('game.pre_season_desc')"&gt;
    &lt;x-slot name="icon"&gt;
        &lt;svg class="w-5 h-5" ...&gt;...&lt;/svg&gt;
    &lt;/x-slot&gt;
    &lt;x-secondary-button&gt;Skip&lt;/x-secondary-button&gt;
&lt;/x-status-banner&gt;

&lt;!-- Minimal (no action slot, no icon) --&gt;
&lt;x-status-banner color="green" title="Transfer completed" /&gt;</code></pre>
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
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">color</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">blue</td>
                        <td class="py-2 text-text-secondary">blue | gold | red | green</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">icon</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">slot</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">null</td>
                        <td class="py-2 text-text-secondary">SVG icon displayed in a circular container.</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">title</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">string|null</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">null</td>
                        <td class="py-2 text-text-secondary">Bold heading text.</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">description</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">string|null</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">null</td>
                        <td class="py-2 text-text-secondary">Secondary text below the title.</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">default slot</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">slot</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">—</td>
                        <td class="py-2 text-text-secondary">Action area (buttons, links) aligned to the right on desktop.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Actionable Warning Card --}}
    <div>
        <h3 class="text-lg font-semibold text-text-primary mb-2">Actionable Warning Card</h3>
        <p class="text-sm text-text-secondary mb-4">Dashed border variant used when an action is required from the user (e.g., budget not allocated).</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="text-center py-6 border-2 border-dashed border-amber-500/30 bg-amber-500/5 rounded-xl">
                <div class="text-sm text-amber-400 font-medium mb-2">Budget not allocated</div>
                <div class="text-3xl font-bold text-text-primary mb-1">&euro;42.5M</div>
                <div class="text-sm text-text-secondary mb-4">Available surplus to allocate</div>
                <button class="inline-flex items-center gap-2 px-5 py-2 bg-accent-blue hover:bg-blue-600 text-white text-sm font-semibold rounded-lg transition-colors">
                    Set up budget &rarr;
                </button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="text-center py-6 border-2 border-dashed border-amber-500/30 bg-amber-500/5 rounded-xl"&gt;
    &lt;div class="text-sm text-amber-400 font-medium mb-2"&gt;Budget not allocated&lt;/div&gt;
    &lt;div class="text-3xl font-bold text-white mb-1"&gt;&#123;&#123; $amount &#125;&#125;&lt;/div&gt;
    &lt;div class="text-sm text-text-secondary mb-4"&gt;Available surplus&lt;/div&gt;
    &lt;!-- CTA button --&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>
</section>
