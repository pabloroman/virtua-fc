// Infrastructure budget allocator. Three tier sliders (youth academy, medical,
// scouting) drive live amounts from the per-area threshold table; the remaining
// surplus becomes the transfer budget. All figures are recomputed reactively and
// serialised to hidden inputs (euros) for submission by the host form.
export default function budgetAllocation(config) {
    return {
        availableSurplus: config.availableSurplus,
        thresholds: config.thresholds,
        minimumTier: config.minimumTier,
        youth_academy_tier: config.tiers.youth_academy,
        medical_tier: config.tiers.medical,
        scouting_tier: config.tiers.scouting,

        getAmount(area, tier) {
            const areaThresholds = this.thresholds[area] || {};
            return areaThresholds[tier] ?? 0;
        },

        get youth_academy_amount() { return this.getAmount('youth_academy', parseInt(this.youth_academy_tier)); },
        get medical_amount() { return this.getAmount('medical', parseInt(this.medical_tier)); },
        get scouting_amount() { return this.getAmount('scouting', parseInt(this.scouting_tier)); },

        get infrastructureTotal() {
            return this.youth_academy_amount + this.medical_amount + this.scouting_amount;
        },

        get transfer_budget() {
            return Math.max(0, this.availableSurplus - this.infrastructureTotal);
        },

        get exceedsBudget() {
            return this.infrastructureTotal > this.availableSurplus;
        },

        get meetsMinimumRequirements() {
            return parseInt(this.youth_academy_tier) >= this.minimumTier
                && parseInt(this.medical_tier) >= this.minimumTier
                && parseInt(this.scouting_tier) >= this.minimumTier;
        },

        fillPercent(tier) {
            const span = 4 - this.minimumTier;
            if (span <= 0) return 0;
            return ((parseInt(tier) - this.minimumTier) / span) * 100;
        },

        formatMoney(cents) {
            const euros = cents / 100;
            if (euros >= 1000000000) return '€' + (euros / 1000000000).toFixed(1) + 'B';
            if (euros >= 1000000) return '€' + (euros / 1000000).toFixed(1) + 'M';
            if (euros >= 1000) return '€' + (euros / 1000).toFixed(0) + 'K';
            return '€' + euros.toFixed(0);
        },

        getTierColor(tier) {
            const t = parseInt(tier);
            const colors = { 0: 'text-accent-red', 1: 'text-accent-gold', 2: 'text-accent-green', 3: 'text-accent-blue', 4: 'text-purple-400' };
            return colors[t] || 'text-text-secondary';
        },
    };
}
