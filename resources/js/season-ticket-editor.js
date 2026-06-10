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
        capacity: config.capacity,
        noShowRate: config.noShowRate,

        // The aggregates (fill, sold, revenue) for the selected preset. Every
        // preset is precomputed server-side, so switching is instant and needs
        // no network round-trip.
        get current() {
            return this.presets[this.selected] ?? Object.values(this.presets)[0] ?? {
                overall_fill: 0, total_sold: 0, total_revenue: 0,
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

        // Projected typical match-day attendance for the selected preset:
        // attending holders (after no-show) + walk-up demand beyond the abono
        // base. Mirrors MatchAttendanceService::composeSeasonTicketAttendance,
        // so the bar shows how full the ground gets — distinct from the abono
        // penetration. A pricier preset sells fewer abonos but walk-up takes up
        // the slack, so occupancy moves far less than the abono count.
        get matchdayAttendance() {
            const holders = this.current.total_sold ?? 0;
            const attendingHolders = Math.round(holders * (1 - this.noShowRate));
            const walkup = Math.max(0, this.expectedAttendance - holders);
            return Math.min(this.capacity, attendingHolders + walkup);
        },

        get matchdayFillPercent() {
            return this.capacity > 0 ? Math.round((this.matchdayAttendance / this.capacity) * 100) : 0;
        },

        select(key) {
            this.selected = key;
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
