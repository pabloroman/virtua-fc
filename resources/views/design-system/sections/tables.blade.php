<section id="tables" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Tables</h2>
    <p class="text-slate-500 mb-8">Data tables with responsive column hiding, group headers, hover states, and mobile-friendly horizontal scroll.</p>

    {{-- Standard Table --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Standard Data Table</h3>
        <p class="text-sm text-slate-500 mb-4">Wrapped in <code class="text-xs bg-slate-100 px-1 py-0.5 rounded text-slate-700">overflow-x-auto</code> for mobile scroll. Non-essential columns use <code class="text-xs bg-slate-100 px-1 py-0.5 rounded text-slate-700">hidden md:table-cell</code>.</p>

        <div class="border border-slate-200 rounded-lg overflow-hidden mb-3">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left border-b">
                        <tr>
                            <th class="font-semibold py-2 px-4">Name</th>
                            <th class="font-semibold py-2 text-center">Pos</th>
                            <th class="font-semibold py-2 hidden md:table-cell">Nationality</th>
                            <th class="font-semibold py-2 text-center hidden md:table-cell">Age</th>
                            <th class="font-semibold py-2 text-right pr-4 hidden md:table-cell">Value</th>
                            <th class="font-semibold py-2 text-center">OVR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach([
                            ['name' => 'Pedri', 'pos' => 'CM', 'nat' => 'Spain', 'age' => 23, 'value' => '€85M', 'ovr' => 88],
                            ['name' => 'Gavi', 'pos' => 'CM', 'nat' => 'Spain', 'age' => 21, 'value' => '€72M', 'ovr' => 82],
                            ['name' => 'Lamine Yamal', 'pos' => 'RW', 'nat' => 'Spain', 'age' => 18, 'value' => '€120M', 'ovr' => 85],
                            ['name' => 'Marc Casadó', 'pos' => 'DM', 'nat' => 'Spain', 'age' => 21, 'value' => '€35M', 'ovr' => 76],
                        ] as $player)
                        <tr class="border-b border-slate-200 hover:bg-slate-50 transition-colors">
                            <td class="py-2 px-4 font-medium text-slate-900">{{ $player['name'] }}</td>
                            <td class="py-2 text-center"><x-position-badge abbreviation="{{ $player['pos'] }}" size="sm" /></td>
                            <td class="py-2 text-slate-500 hidden md:table-cell">{{ $player['nat'] }}</td>
                            <td class="py-2 text-center hidden md:table-cell">{{ $player['age'] }}</td>
                            <td class="py-2 text-right pr-4 tabular-nums text-slate-600 hidden md:table-cell">{{ $player['value'] }}</td>
                            <td class="py-2 text-center">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold {{ $player['ovr'] >= 80 ? 'bg-emerald-500 text-white' : ($player['ovr'] >= 70 ? 'bg-lime-500 text-white' : 'bg-amber-500 text-white') }}">{{ $player['ovr'] }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="overflow-x-auto"&gt;
    &lt;table class="w-full text-sm"&gt;
        &lt;thead class="text-left border-b"&gt;
            &lt;tr&gt;
                &lt;th class="font-semibold py-2 px-4"&gt;Name&lt;/th&gt;
                &lt;th class="font-semibold py-2 hidden md:table-cell"&gt;Age&lt;/th&gt;
                &lt;th class="font-semibold py-2 text-right pr-4"&gt;Value&lt;/th&gt;
            &lt;/tr&gt;
        &lt;/thead&gt;
        &lt;tbody&gt;
            &lt;tr class="border-b border-slate-200 hover:bg-slate-50"&gt;
                &lt;td class="py-2 px-4"&gt;...&lt;/td&gt;
            &lt;/tr&gt;
        &lt;/tbody&gt;
    &lt;/table&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Group Headers --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Group Headers</h3>
        <p class="text-sm text-slate-500 mb-4">Slate-200 background rows used to group table rows by category (e.g., position groups in squad).</p>

        <div class="border border-slate-200 rounded-lg overflow-hidden mb-3">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody>
                        <tr class="bg-slate-200">
                            <td colspan="3" class="py-2 px-4 text-xs font-semibold text-slate-600 uppercase tracking-wide">Goalkeepers</td>
                        </tr>
                        <tr class="border-b border-slate-200 hover:bg-slate-50">
                            <td class="py-2 px-4 font-medium text-slate-900">Ter Stegen</td>
                            <td class="py-2"><x-position-badge abbreviation="GK" size="sm" /></td>
                            <td class="py-2 text-right pr-4 text-slate-500">32</td>
                        </tr>
                        <tr class="bg-slate-200">
                            <td colspan="3" class="py-2 px-4 text-xs font-semibold text-slate-600 uppercase tracking-wide">Midfielders</td>
                        </tr>
                        <tr class="border-b border-slate-200 hover:bg-slate-50">
                            <td class="py-2 px-4 font-medium text-slate-900">Pedri</td>
                            <td class="py-2"><x-position-badge abbreviation="CM" size="sm" /></td>
                            <td class="py-2 text-right pr-4 text-slate-500">23</td>
                        </tr>
                        <tr class="border-b border-slate-200 hover:bg-slate-50">
                            <td class="py-2 px-4 font-medium text-slate-900">Gavi</td>
                            <td class="py-2"><x-position-badge abbreviation="CM" size="sm" /></td>
                            <td class="py-2 text-right pr-4 text-slate-500">21</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;tr class="bg-slate-200"&gt;
    &lt;td colspan="N" class="py-2 px-4 text-xs font-semibold text-slate-600 uppercase tracking-wide"&gt;
        Group Name
    &lt;/td&gt;
&lt;/tr&gt;</code></pre>
        </div>
    </div>

    {{-- Financial Table --}}
    <div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Financial Table</h3>
        <p class="text-sm text-slate-500 mb-4">Line-item table with green income and red expense values. Used in the finances page budget flow.</p>

        <div class="border border-slate-200 rounded-lg overflow-hidden mb-3">
            <div class="px-5 py-4 space-y-0 text-sm">
                <div class="flex items-center justify-between py-2">
                    <span class="text-slate-500 pl-5">TV Rights</span>
                    <span class="text-green-600">+&euro;18.5M</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-slate-500 pl-5">Commercial</span>
                    <span class="text-green-600">+&euro;12.3M</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-slate-500 pl-5">Matchday</span>
                    <span class="text-green-600">+&euro;8.7M</span>
                </div>
                <div class="border-t pt-2 mt-1">
                    <div class="flex items-center justify-between py-1">
                        <span class="font-semibold text-slate-700 pl-5">Total Revenue</span>
                        <span class="font-semibold text-green-600">+&euro;39.5M</span>
                    </div>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-slate-500 pl-5">Wages</span>
                    <span class="text-red-600">-&euro;24.8M</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-slate-500 pl-5">Operating Expenses</span>
                    <span class="text-red-600">-&euro;3.2M</span>
                </div>
                <div class="border-t-2 border-slate-900 pt-2 mt-1">
                    <div class="flex items-center justify-between py-1">
                        <span class="font-semibold text-lg text-slate-900">= Transfer Budget</span>
                        <span class="font-semibold text-lg text-slate-900">&euro;11.5M</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
