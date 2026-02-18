<section id="shirt-badges" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Shirt Badges</h2>
    <p class="text-slate-500 mb-8">Player badges on the pitch lineup view use team-specific shirt patterns and colors. Each badge shows the player's number or initials with a contrast-aware backdrop for patterned shirts.</p>

    <div x-data="shirtBadgePreview()" class="space-y-6">
        {{-- Pattern legend --}}
        <div class="flex flex-wrap gap-3 text-xs">
            <template x-for="p in ['solid', 'stripes', 'hoops', 'sash', 'halves']" :key="p">
                <span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full font-medium uppercase tracking-wide" x-text="p"></span>
            </template>
        </div>

        {{-- GK + all teams grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            {{-- Goalkeeper reference --}}
            <div class="flex flex-col items-center gap-2 p-3 bg-slate-50 rounded-lg border border-slate-200">
                <div class="bg-emerald-600 rounded-lg p-3 flex gap-2 items-center">
                    <div class="relative w-11 h-11 rounded-xl border border-white/20 shadow-lg flex items-center justify-center" style="background: linear-gradient(to bottom right, #FBBF24, #D97706)">
                        <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full" style="color: #FFFFFF; text-shadow: 0 1px 2px rgba(0,0,0,0.5)">1</span>
                    </div>
                    <div class="relative w-11 h-11 rounded-xl border border-white/20 shadow-lg flex items-center justify-center" style="background: linear-gradient(to bottom right, #FBBF24, #D97706)">
                        <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full" style="color: #FFFFFF; text-shadow: 0 1px 2px rgba(0,0,0,0.5)">GK</span>
                    </div>
                </div>
                <div class="text-[11px] text-slate-600 font-medium text-center">Goalkeeper</div>
                <div class="text-[10px] text-slate-400 uppercase tracking-wide">all teams</div>
            </div>

            {{-- Team badges --}}
            <template x-for="[name, tc] in teams" :key="name">
                <div class="flex flex-col items-center gap-2 p-3 bg-slate-50 rounded-lg border border-slate-200">
                    <div class="bg-emerald-600 rounded-lg p-3 flex gap-2 items-center">
                        <div class="relative w-11 h-11 rounded-xl border border-white/20 shadow-lg flex items-center justify-center" :style="getShirtStyle(tc)">
                            <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full" :style="getNumberStyle(tc)">10</span>
                        </div>
                        <div class="relative w-11 h-11 rounded-xl border border-white/20 shadow-lg flex items-center justify-center" :style="getShirtStyle(tc)">
                            <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full" :style="getNumberStyle(tc)" x-text="getInitials(name)"></span>
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-600 font-medium text-center truncate w-full" x-text="name"></div>
                    <div class="text-[10px] text-slate-400 uppercase tracking-wide" x-text="tc.pattern"></div>
                </div>
            </template>
        </div>
    </div>

    <script>
        function shirtBadgePreview() {
            return {
                teams: Object.entries(@js($allTeams)),

                getShirtStyle(tc) {
                    const p = tc.primary;
                    const s = tc.secondary;
                    switch (tc.pattern) {
                        case 'stripes':
                            return `background: repeating-linear-gradient(90deg, ${p} 0px, ${p} 6px, ${s} 6px, ${s} 12px)`;
                        case 'hoops':
                            return `background: repeating-linear-gradient(0deg, ${p} 0px, ${p} 6px, ${s} 6px, ${s} 12px)`;
                        case 'sash':
                            return `background: linear-gradient(135deg, ${p} 0%, ${p} 35%, ${s} 35%, ${s} 65%, ${p} 65%, ${p} 100%)`;
                        case 'halves':
                            return `background: linear-gradient(90deg, ${p} 50%, ${s} 50%)`;
                        default:
                            return `background: ${p}`;
                    }
                },

                getNumberStyle(tc) {
                    const color = tc.number || '#FFFFFF';
                    if (tc.pattern !== 'solid') {
                        const backdrop = this._getBackdropColor(tc);
                        return `color: ${color}; background: ${backdrop}CC; text-shadow: 0 1px 2px rgba(0,0,0,0.15)`;
                    }
                    return `color: ${color}; text-shadow: 0 1px 2px rgba(0,0,0,0.2)`;
                },

                _getBackdropColor(tc) {
                    const numLum = this._hexLuminance(tc.number);
                    const priLum = this._hexLuminance(tc.primary);
                    const secLum = this._hexLuminance(tc.secondary);
                    return Math.abs(numLum - priLum) >= Math.abs(numLum - secLum) ? tc.primary : tc.secondary;
                },

                _hexLuminance(hex) {
                    if (!hex || hex.length < 7) return 0.5;
                    const r = parseInt(hex.slice(1, 3), 16) / 255;
                    const g = parseInt(hex.slice(3, 5), 16) / 255;
                    const b = parseInt(hex.slice(5, 7), 16) / 255;
                    return 0.299 * r + 0.587 * g + 0.114 * b;
                },

                getInitials(name) {
                    const parts = name.trim().split(/\s+/);
                    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
                    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
                },
            };
        }
    </script>
</section>
