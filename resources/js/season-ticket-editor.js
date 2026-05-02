export default function seasonTicketEditor(config) {
    return {
        prices: { ...config.prices },
        baselines: { ...config.baselines },
        areas: config.initialAreas.map((a) => ({ ...a })),
        overallFill: config.initialFill,
        totalRevenue: config.initialRevenue,
        totalSold: config.initialSold,
        projectedMatchday: config.initialMatchday,
        totalCapacity: config.totalCapacity,
        minMultiplier: config.minMultiplier,
        maxMultiplier: config.maxMultiplier,
        pendingFetch: null,
        isUpdating: false,

        formatPrice(cents) {
            return '€ ' + new Intl.NumberFormat('es-ES').format(Math.round(cents / 100));
        },

        formatRevenue(cents) {
            const euros = cents / 100;
            if (euros >= 1_000_000) return '€ ' + (euros / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
            if (euros >= 1_000) return '€ ' + Math.round(euros / 1_000) + 'K';
            return '€ ' + Math.round(euros);
        },

        updatePrice(index, raw) {
            const cents = Math.round(parseFloat(raw || '0') * 100);
            this.setPriceCents(index, cents);
        },

        setPriceCents(index, raw) {
            const baseline = this.baselines[index] ?? 0;
            let cents = parseInt(raw, 10);
            if (!Number.isFinite(cents)) cents = 0;
            const min = Math.round(baseline * this.minMultiplier);
            const max = Math.round(baseline * this.maxMultiplier);
            cents = Math.max(min, Math.min(max, cents));
            this.prices = { ...this.prices, [index]: cents };
            this.refresh();
        },

        sliderFill(index) {
            const baseline = this.baselines[index] ?? 0;
            if (!baseline) return '0%';
            const min = Math.round(baseline * this.minMultiplier);
            const max = Math.round(baseline * this.maxMultiplier);
            if (max <= min) return '0%';
            const cents = this.prices[index] ?? min;
            const pct = Math.max(0, Math.min(100, ((cents - min) / (max - min)) * 100));
            return pct.toFixed(2) + '%';
        },

        resetToDefaults() {
            this.prices = { ...this.baselines };
            this.refresh();
        },

        refresh() {
            if (this.pendingFetch) clearTimeout(this.pendingFetch);
            this.isUpdating = true;
            this.pendingFetch = setTimeout(async () => {
                try {
                    const res = await fetch(config.previewUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': config.csrf,
                        },
                        body: JSON.stringify({ prices: this.prices }),
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    this.areas = data.areas ?? this.areas;
                    this.overallFill = data.overall_fill_rate ?? this.overallFill;
                    this.totalRevenue = data.total_revenue ?? this.totalRevenue;
                    this.totalSold = data.total_sold ?? this.totalSold;
                    this.projectedMatchday = data.projected_matchday_revenue ?? this.projectedMatchday;
                } catch (e) {
                    /* network blip — keep last preview */
                } finally {
                    this.isUpdating = false;
                }
            }, 250);
        },
    };
}
