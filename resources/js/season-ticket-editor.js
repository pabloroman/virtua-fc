export default function seasonTicketEditor(config) {
    return {
        presets: config.presets,
        selected: config.current,

        // Fixed inputs to the walk-up matchday projection (see
        // BudgetProjectionService::matchdayProjectionFactors). Only the
        // season-ticket holder count varies per preset, so the taquilla figure
        // is recomputed here as the user toggles presets — no save round-trip.
        expectedAttendance: config.expectedAttendance,
        perAttendeeCents: config.perAttendeeCents,

        // The payload (areas, fill, sold, revenue) for the selected preset.
        // Every preset is precomputed server-side, so switching is instant and
        // needs no network round-trip.
        get current() {
            return this.presets[this.selected] ?? Object.values(this.presets)[0] ?? {
                areas: [], overall_fill: 0, total_sold: 0, total_revenue: 0,
            };
        },

        // Projected walk-up matchday revenue for the selected preset. Mirrors
        // BudgetProjectionService::calculateMatchdayRevenue exactly: holders
        // are subtracted from the expected gate, and the server's (int) cast
        // matches Math.trunc here. Selling more season tickets (a fuller
        // preset) shrinks walk-up, so this falls as season-ticket revenue rises.
        get matchday() {
            const walkup = Math.max(0, this.expectedAttendance - (this.current.total_sold ?? 0));
            return Math.trunc(walkup * this.perAttendeeCents);
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
