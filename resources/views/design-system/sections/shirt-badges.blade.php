<section id="shirt-badges" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Shirt Badges</h2>
    <p class="text-sm text-text-secondary mb-8">Player badges on the pitch lineup view use team-specific shirt patterns and colors. Each badge shows the player's number or initials with a contrast-aware backdrop for patterned shirts.</p>

    <div class="space-y-8">
        {{-- Pattern legend --}}
        <div class="flex flex-wrap gap-3 text-xs">
            @foreach (['solid', 'stripes', 'hoops', 'sash', 'bar', 'halves', 'quarters', 'chevron'] as $pattern)
                <span class="px-2.5 py-1 bg-surface-700 text-text-secondary border border-border-default rounded-full font-medium uppercase tracking-wide">{{ $pattern }}</span>
            @endforeach
        </div>

        {{-- Goalkeeper reference --}}
        <div>
            <h3 class="font-heading text-lg font-bold uppercase tracking-wide text-text-secondary mb-3">Goalkeeper</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <div class="flex flex-col items-center gap-2 p-3 bg-surface-800 rounded-xl border border-border-default">
                    <div class="bg-pitch-base rounded-lg p-3 flex gap-2 items-center">
                        <div class="relative w-11 h-11 rounded-xl border border-white/20 shadow-lg flex items-center justify-center" style="background: linear-gradient(to bottom right, #FBBF24, #D97706)">
                            <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full" style="color: #FFFFFF; text-shadow: 0 1px 2px rgba(0,0,0,0.5)">1</span>
                        </div>
                        <div class="relative w-11 h-11 rounded-xl border border-white/20 shadow-lg flex items-center justify-center" style="background: linear-gradient(to bottom right, #FBBF24, #D97706)">
                            <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full" style="color: #FFFFFF; text-shadow: 0 1px 2px rgba(0,0,0,0.5)">GK</span>
                        </div>
                    </div>
                    <div class="text-[11px] text-text-body font-medium text-center">Goalkeeper</div>
                    <div class="text-[10px] text-text-muted uppercase tracking-widest">all teams</div>
                </div>
            </div>
        </div>

        {{-- Teams grouped by competition --}}
        @foreach ($allTeams as $groupLabel => $groupTeams)
            <div x-data="shirtBadgeGroup(@js($groupTeams))" class="space-y-3">
                <h3 class="font-heading text-lg font-bold uppercase tracking-wide text-text-secondary">{{ $groupLabel }}</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <template x-for="[name, tc] in teams" :key="name">
                        <div class="flex flex-col items-center gap-2 p-3 bg-surface-800 rounded-xl border border-border-default">
                            <div class="bg-pitch-base rounded-lg p-3 flex gap-2 items-center">
                                <div class="relative w-11 h-11 rounded-xl border border-white/20 shadow-lg flex items-center justify-center" :style="getShirtStyle(tc)">
                                    <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full" :style="getNumberStyle(tc)">10</span>
                                </div>
                                <div class="relative w-11 h-11 rounded-xl border border-white/20 shadow-lg flex items-center justify-center" :style="getShirtStyle(tc)">
                                    <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full" :style="getNumberStyle(tc)" x-text="getInitials(name)"></span>
                                </div>
                            </div>
                            <div class="text-[11px] text-text-body font-medium text-center truncate w-full" x-text="name"></div>
                            <div class="text-[10px] text-text-muted uppercase tracking-widest" x-text="tc.pattern"></div>
                        </div>
                    </template>
                </div>
            </div>
        @endforeach
    </div>

    <script>
        function shirtBadgeGroup(teamData) {
            return {
                teams: Object.entries(teamData),

                getShirtStyle(tc) {
                    const p = tc.primary;
                    const s = tc.secondary;
                    switch (tc.pattern) {
                        case 'stripes':
                            return `background: linear-gradient(90deg, ${s} 3px, ${p} 3px, ${p} 9px, ${s} 9px); background-size: 12px 100%; background-position: center`;
                        case 'hoops':
                            return `background: linear-gradient(0deg, ${s} 3px, ${p} 3px, ${p} 9px, ${s} 9px); background-size: 100% 12px; background-position: center`;
                        case 'sash':
                            return `background: linear-gradient(135deg, ${p} 0%, ${p} 35%, ${s} 35%, ${s} 65%, ${p} 65%, ${p} 100%)`;
                        case 'bar':
                            return `background: linear-gradient(90deg, ${p} 0%, ${p} 35%, ${s} 35%, ${s} 65%, ${p} 65%, ${p} 100%)`;
                        case 'halves':
                            return `background: linear-gradient(90deg, ${p} 50%, ${s} 50%)`;
                        case 'quarters':
                            return `background: conic-gradient(from 90deg, ${p} 0deg 90deg, ${s} 90deg 180deg, ${p} 180deg 270deg, ${s} 270deg 360deg)`;
                        case 'chevron': {
                            const svg = `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' preserveAspectRatio='none'><path d='M0 14 L50 74 L100 14 L100 34 L50 94 L0 34 Z' fill='${s}'/></svg>`;
                            return `background-color: ${p}; background-image: url("data:image/svg+xml,${encodeURIComponent(svg)}"); background-size: 100% 100%; background-repeat: no-repeat`;
                        }
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
