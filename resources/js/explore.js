export default function explore(config) {
    const initialFilters = config.initialFilters || {};
    const searchMode = !!config.searchMode;
    const initialTeam = config.initialTeam || null;
    const initialCompetitionId = config.initialCompetitionId || null;
    const initialPoolId = config.initialPoolId || null;

    return {
        competitions: config.competitions || [],
        pools: config.pools || [],
        leagueKindLabel: config.labels.leagueKind,
        poolKindLabel: config.labels.poolKind,
        searchKindLabel: config.labels.searchKind,
        searchScopeLabel: config.labels.searchScope,
        assetUrl: config.assetUrl || '',
        gameId: config.gameId,
        viewMode: searchMode ? 'search' : 'competition',
        scopePickerOpen: false,
        selectedCompetition: null,
        activePoolId: null,
        activePoolHint: '',
        teams: [],
        selectedTeam: null,
        squadHtml: '',
        loadingTeams: false,
        loadingSquad: false,
        loadingPool: false,
        poolGroups: [],
        searchQuery: initialFilters.name || '',
        searching: false,
        mobileView: 'teams',
        filtersOpen: searchMode,
        filters: {
            position: initialFilters.position || '',
            nationality: initialFilters.nationality || '',
            competition_id: initialFilters.competition_id || '',
            max_contract_year: initialFilters.max_contract_year || null,
        },

        // When true, in-flight selection helpers skip the history.pushState
        // call. Set during init() (so deep-link bootstrapping doesn't append
        // history entries) and during popstate handling (so back/forward
        // navigation doesn't push a fresh entry on top of itself).
        _suppressHistory: false,

        // Dual-range bounds (mirrors scout-search-modal pattern)
        AGE_MIN_BOUND: 16,
        AGE_MAX_BOUND: 40,
        OVERALL_MIN_BOUND: 50,
        OVERALL_MAX_BOUND: 99,
        valueSteps: [0, 500000, 1000000, 2000000, 5000000, 10000000, 20000000, 50000000, 100000000, 200000000],

        ageMin: null,
        ageMax: null,
        overallMin: null,
        overallMax: null,
        valueStepMin: null,
        valueStepMax: null,

        enforceAgeMin() { if (this.ageMin > this.ageMax) this.ageMax = this.ageMin; },
        enforceAgeMax() { if (this.ageMax < this.ageMin) this.ageMin = this.ageMax; },
        enforceOverallMin() { if (this.overallMin > this.overallMax) this.overallMax = this.overallMin; },
        enforceOverallMax() { if (this.overallMax < this.overallMin) this.overallMin = this.overallMax; },
        enforceValueMin() { if (this.valueStepMin > this.valueStepMax) this.valueStepMax = this.valueStepMin; },
        enforceValueMax() { if (this.valueStepMax < this.valueStepMin) this.valueStepMin = this.valueStepMax; },

        ageTrackLeft() { return ((this.ageMin - this.AGE_MIN_BOUND) / (this.AGE_MAX_BOUND - this.AGE_MIN_BOUND)) * 100 + '%'; },
        ageTrackWidth() { return ((this.ageMax - this.ageMin) / (this.AGE_MAX_BOUND - this.AGE_MIN_BOUND)) * 100 + '%'; },
        overallTrackLeft() { return ((this.overallMin - this.OVERALL_MIN_BOUND) / (this.OVERALL_MAX_BOUND - this.OVERALL_MIN_BOUND)) * 100 + '%'; },
        overallTrackWidth() { return ((this.overallMax - this.overallMin) / (this.OVERALL_MAX_BOUND - this.OVERALL_MIN_BOUND)) * 100 + '%'; },
        valueTrackLeft() { return (this.valueStepMin / (this.valueSteps.length - 1)) * 100 + '%'; },
        valueTrackWidth() { return ((this.valueStepMax - this.valueStepMin) / (this.valueSteps.length - 1)) * 100 + '%'; },

        valueMin() { return this.valueSteps[this.valueStepMin]; },
        valueMax() { return this.valueSteps[this.valueStepMax]; },
        formatValue(val) {
            if (val === 0) return '€0';
            if (val >= 1000000) return '€' + (val / 1000000) + 'M';
            if (val >= 1000) return '€' + (val / 1000) + 'K';
            return '€' + val;
        },

        get ageActive() { return this.ageMin > this.AGE_MIN_BOUND || this.ageMax < this.AGE_MAX_BOUND; },
        get overallActive() { return this.overallMin > this.OVERALL_MIN_BOUND || this.overallMax < this.OVERALL_MAX_BOUND; },
        get valueActive() { return this.valueStepMin > 0 || this.valueStepMax < this.valueSteps.length - 1; },

        get activeFilterCount() {
            let n = 0;
            if (this.filters.position) n++;
            if (this.filters.nationality) n++;
            if (this.filters.competition_id) n++;
            if (this.filters.max_contract_year) n++;
            if (this.ageActive) n++;
            if (this.overallActive) n++;
            if (this.valueActive) n++;
            return n;
        },
        get hasAnyCriteria() {
            return this.searchQuery.trim().length >= 2 || this.activeFilterCount > 0;
        },

        async init() {
            this.initRangesFromFilters();

            // The <select name="competition_id"> renders its options via
            // <template x-for>. Alpine evaluates x-model before x-for has
            // populated those <option> nodes, so a server-prefilled
            // competition_id silently falls back to the empty default.
            // Re-assign on the next tick once the options exist so the
            // select actually reflects the search criteria the user
            // submitted.
            if (initialFilters.competition_id) {
                this.$nextTick(() => {
                    const sel = this.$el.querySelector('select[name="competition_id"]');
                    if (sel) sel.value = initialFilters.competition_id;
                });
            }

            window.addEventListener('popstate', () => this._handlePopState());

            // Deep-link bootstrap: server resolved a slug → preselect the
            // team's competition (or pool), then load its squad. Suppress
            // history pushes so the bootstrap path doesn't litter the
            // browser history with intermediate entries.
            if (initialTeam) {
                this._suppressHistory = true;
                try {
                    if (initialPoolId) {
                        const pool = this.pools.find(p => p.id === initialPoolId);
                        if (pool) {
                            await this.selectPool(pool);
                            await this.selectTeam(initialTeam);
                        }
                    } else if (initialCompetitionId) {
                        const comp = this.competitions.find(c => c.id === initialCompetitionId);
                        if (comp) {
                            await this.selectCompetition(comp);
                            await this.selectTeam(initialTeam);
                        }
                    }
                } finally {
                    this._suppressHistory = false;
                }
                return;
            }

            if (!searchMode && this.competitions.length > 0) {
                this._suppressHistory = true;
                try {
                    await this.selectCompetition(this.competitions[0]);
                } finally {
                    this._suppressHistory = false;
                }
            }
        },

        // Push a target path only when it actually differs from the current
        // URL, so cycling between competitions (which all share the base
        // /explore path) doesn't bloat the browser history.
        _syncUrl(targetPath) {
            if (this._suppressHistory) return;
            if (window.location.pathname === targetPath) return;
            history.pushState({}, '', targetPath);
        },

        _handlePopState() {
            this._suppressHistory = true;
            const m = window.location.pathname.match(/\/explore\/team\/([^/]+)$/);
            if (m) {
                const slug = m[1];
                const fromComp = this.teams.find(t => t.slug === slug);
                const fromPool = !fromComp
                    ? this.poolGroups.flatMap(g => g.teams || []).find(t => t.slug === slug)
                    : null;
                const team = fromComp || fromPool;
                if (team) {
                    this.selectTeam(team).finally(() => {
                        this._suppressHistory = false;
                    });
                    return;
                }
                // Cross-competition jump: the team isn't in the currently
                // loaded list. Reloading is the simplest correct fallback —
                // the server-side resolver will rebuild the right scope.
                window.location.reload();
                return;
            }
            // Base /explore URL: clear any team selection.
            this.selectedTeam = null;
            this.squadHtml = '';
            if (this.$refs.squadPanel) this.$refs.squadPanel.innerHTML = '';
            if (this.$refs.poolSquadPanel) this.$refs.poolSquadPanel.innerHTML = '';
            this._suppressHistory = false;
        },

        initRangesFromFilters() {
            const f = initialFilters;
            this.ageMin = f.min_age ? Number(f.min_age) : this.AGE_MIN_BOUND;
            this.ageMax = f.max_age ? Number(f.max_age) : this.AGE_MAX_BOUND;
            this.overallMin = f.min_overall ? Number(f.min_overall) : this.OVERALL_MIN_BOUND;
            this.overallMax = f.max_overall ? Number(f.max_overall) : this.OVERALL_MAX_BOUND;
            this.valueStepMin = f.min_value ? this.stepForValue(Number(f.min_value), 0) : 0;
            this.valueStepMax = f.max_value ? this.stepForValue(Number(f.max_value), this.valueSteps.length - 1) : this.valueSteps.length - 1;
        },

        stepForValue(value, fallback) {
            const idx = this.valueSteps.indexOf(value);
            return idx >= 0 ? idx : fallback;
        },

        async selectCompetition(comp) {
            this.viewMode = 'competition';
            this.selectedCompetition = comp;
            this.selectedTeam = null;
            this.squadHtml = '';
            if (this.$refs.squadPanel) this.$refs.squadPanel.innerHTML = '';
            if (this.$refs.poolSquadPanel) this.$refs.poolSquadPanel.innerHTML = '';
            this.loadingTeams = true;
            this._syncUrl(`/game/${this.gameId}/explore`);

            try {
                const response = await fetch(`/game/${this.gameId}/explore/teams/${comp.id}`);
                this.teams = await response.json();
            } catch (e) {
                this.teams = [];
            } finally {
                this.loadingTeams = false;
            }
        },

        async selectTeam(team) {
            this.selectedTeam = team;
            this.loadingSquad = true;
            this.mobileView = 'squad';

            const panel = this.viewMode === 'pool' ? this.$refs.poolSquadPanel : this.$refs.squadPanel;

            try {
                const response = await fetch(`/game/${this.gameId}/explore/squad/${team.id}`);
                const html = await response.text();
                this.squadHtml = html;
                if (panel) {
                    panel.innerHTML = html;
                    this.$nextTick(() => window.Alpine.initTree(panel));
                }
                // Push the deep-link URL only after a successful squad load
                // so a failed fetch never leaves a misleading slug in the
                // address bar.
                if (team.slug) {
                    this._syncUrl(`/game/${this.gameId}/explore/team/${team.slug}`);
                }
            } catch (e) {
                this.squadHtml = '';
                if (panel) panel.innerHTML = '';
            } finally {
                this.loadingSquad = false;
            }
        },

        async selectPool(pool) {
            this.viewMode = 'pool';
            this.selectedCompetition = null;
            this.selectedTeam = null;
            this.squadHtml = '';
            if (this.$refs.poolSquadPanel) this.$refs.poolSquadPanel.innerHTML = '';
            this.mobileView = 'teams';
            this._syncUrl(`/game/${this.gameId}/explore`);

            const switching = this.activePoolId !== pool.id;
            this.activePoolId = pool.id;
            this.activePoolHint = pool.hint || '';

            if (!switching && this.poolGroups.length > 0) return;

            this.poolGroups = [];
            this.loadingPool = true;
            try {
                const response = await fetch(`/game/${this.gameId}/explore/pool-teams/${pool.id}`);
                this.poolGroups = await response.json();
            } catch (e) {
                this.poolGroups = [];
            } finally {
                this.loadingPool = false;
            }
        },

        get activeScope() {
            if (this.viewMode === 'competition' && this.selectedCompetition) {
                return {
                    kindLabel: this.leagueKindLabel,
                    label: this.selectedCompetition.name,
                    flag: this.selectedCompetition.flag || null,
                    emoji: null,
                    icon: null,
                    count: this.selectedCompetition.teamCount,
                };
            }
            if (this.viewMode === 'pool' && this.activePoolId) {
                const pool = this.pools.find(p => p.id === this.activePoolId);
                if (pool) {
                    return {
                        kindLabel: this.poolKindLabel,
                        label: pool.label,
                        flag: pool.flag,
                        emoji: pool.emoji || null,
                        icon: null,
                        count: pool.count,
                    };
                }
            }
            if (this.viewMode === 'search') {
                return {
                    kindLabel: this.searchKindLabel,
                    label: this.searchScopeLabel,
                    flag: null,
                    emoji: null,
                    icon: 'search',
                    count: null,
                };
            }
            // Fallback for the brief moment before the first scope is selected.
            const firstComp = this.competitions[0];
            return firstComp
                ? { kindLabel: this.leagueKindLabel, label: firstComp.name, flag: firstComp.flag || null, emoji: null, icon: null, count: firstComp.teamCount }
                : { kindLabel: '', label: '—', flag: null, emoji: null, icon: null, count: 0 };
        },

    };
}
