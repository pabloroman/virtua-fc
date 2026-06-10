export default function seasonTicketEditor(config) {
    return {
        presets: config.presets,
        selected: config.current,

        // The payload (areas, fill, sold, revenue) for the selected preset.
        // Every preset is precomputed server-side, so switching is instant and
        // needs no network round-trip.
        get current() {
            return this.presets[this.selected] ?? Object.values(this.presets)[0] ?? {
                areas: [], overall_fill: 0, total_sold: 0, total_revenue: 0,
            };
        },

        select(key) {
            this.selected = key;
        },

        formatPrice(cents) {
            return '€ ' + new Intl.NumberFormat('es-ES').format(Math.round(cents / 100));
        },

        formatRevenue(cents) {
            const euros = cents / 100;
            if (euros >= 1_000_000) return '€ ' + (euros / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
            if (euros >= 1_000) return '€ ' + Math.round(euros / 1_000) + 'K';
            return '€ ' + Math.round(euros);
        },

        fmt(n) {
            return new Intl.NumberFormat('es-ES').format(n ?? 0);
        },
    };
}
