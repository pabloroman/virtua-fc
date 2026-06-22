// Pre-season setup wizard: pick a friendly opponent per fixture slot from the
// grouped team list, search within the picker modal, and toggle home/away. Each
// selection is mirrored into hidden inputs in the blade via :value bindings, so
// this component only owns the in-flight choices, not the form submission.
export default function preseasonSetup(config) {
    return {
        teams: config.teams || [],
        assetUrl: config.assetUrl,
        selections: Array.from({ length: config.slotCount }, () => ({
            teamId: null,
            teamName: '',
            teamImage: '',
            isHome: true,
        })),
        openSlot: null,
        searchQuery: '',

        get filteredGroups() {
            const q = this.searchQuery.trim().toLowerCase();
            if (!q) return this.teams;
            return this.teams
                .map((g) => ({ ...g, teams: g.teams.filter((t) => t.name.toLowerCase().includes(q)) }))
                .filter((g) => g.teams.length > 0);
        },

        choose(team) {
            const s = this.selections[this.openSlot];
            s.teamId = team.id;
            s.teamName = team.name;
            s.teamImage = team.image;
            this.closeModal();
        },

        clear(i) {
            const s = this.selections[i];
            s.teamId = null;
            s.teamName = '';
            s.teamImage = '';
            s.isHome = true;
        },

        closeModal() {
            this.openSlot = null;
            this.searchQuery = '';
        },
    };
}
