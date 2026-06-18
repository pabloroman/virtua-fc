// Alpine component for the squad overview page (resources/views/squad.blade.php):
// view-mode tabs, position/availability/status filters, URL state sync, and
// client-side column sorting via the CSS `order` property on each .player-row.
export default function squadOverview() {
    const param = (key, fallback) => new URLSearchParams(window.location.search).get(key) || fallback;

    return {
        viewMode: param('mode', 'tactical'),
        posFilter: param('pos', 'all'),
        availFilter: param('avail', 'all'),
        statusFilter: 'all',
        sortCol: param('sort', null),
        sortDir: param('dir', 'desc'),
        sidebarOpen: true,

        init() {
            const sync = () => {
                const url = new URL(window.location.href);
                const set = (key, value, defaultValue) => {
                    if (value && value !== defaultValue) {
                        url.searchParams.set(key, value);
                    } else {
                        url.searchParams.delete(key);
                    }
                };
                set('mode', this.viewMode, 'tactical');
                set('pos', this.posFilter, 'all');
                set('avail', this.availFilter, 'all');
                set('sort', this.sortCol, null);
                set('dir', this.sortDir, 'desc');
                history.replaceState({}, '', url);
            };
            this.$watch('viewMode', sync);
            this.$watch('posFilter', sync);
            this.$watch('availFilter', sync);
            this.$watch('sortCol', () => { this.applySort(); sync(); });
            this.$watch('sortDir', () => { this.applySort(); sync(); });
            sync();
            this.applySort();
        },

        // Text columns compare as strings; every other column compares numerically
        // (including 'pos', which sorts on a GK→DEF→MID→FWD position-group ordinal).
        textCols: ['name'],
        // Columns whose first click sorts ascending; all others sort descending first.
        ascFirstCols: ['name', 'pos', 'number'],

        toggleSort(col) {
            if (this.sortCol === col) {
                const firstDir = this.ascFirstCols.includes(col) ? 'asc' : 'desc';
                if (this.sortDir === firstDir) {
                    // Second click: flip direction.
                    this.sortDir = firstDir === 'asc' ? 'desc' : 'asc';
                } else {
                    // Third click: restore default (position-then-rating) order.
                    this.sortCol = null;
                    this.sortDir = 'desc';
                }
            } else {
                this.sortCol = col;
                this.sortDir = this.ascFirstCols.includes(col) ? 'asc' : 'desc';
            }
        },

        applySort() {
            const rows = this.$refs.rows ? Array.from(this.$refs.rows.querySelectorAll('.player-row')) : [];
            if (!this.sortCol) {
                rows.forEach((el) => { el.style.order = ''; });
                return;
            }
            const col = this.sortCol;
            const isText = this.textCols.includes(col);
            const dir = this.sortDir === 'asc' ? 1 : -1;
            const sorted = rows.slice().sort((a, b) => {
                const av = a.dataset['sort' + col.charAt(0).toUpperCase() + col.slice(1)] ?? '';
                const bv = b.dataset['sort' + col.charAt(0).toUpperCase() + col.slice(1)] ?? '';
                if (isText) return dir * String(av).localeCompare(String(bv));
                return dir * ((parseFloat(av) || 0) - (parseFloat(bv) || 0));
            });
            sorted.forEach((el, i) => { el.style.order = i; });
        },

        isVisible(group, available, status) {
            if (this.posFilter !== 'all' && group !== this.posFilter) return false;
            if (this.availFilter === 'available' && !available) return false;
            if (this.availFilter === 'unavailable' && available) return false;
            if (this.statusFilter !== 'all' && status !== this.statusFilter) return false;
            return true;
        },

        activeFilterCount() {
            let c = 0;
            if (this.posFilter !== 'all') c++;
            if (this.availFilter !== 'all') c++;
            if (this.statusFilter !== 'all') c++;
            return c;
        },

        clearFilters() {
            this.posFilter = 'all';
            this.availFilter = 'all';
            this.statusFilter = 'all';
        },
    };
}
