// Alpine component for the scouting/transfer-market tables built from
// <x-explore-player-row>. It mirrors the squad overview's sort state machine
// (resources/js/squad-overview.js) — same 3-click cycle and header UX via the
// shared <x-squad.sort-header>/<x-squad.sort-pill> buttons — but those rows live
// in a real <table>, where the CSS `order` property does NOT apply. So instead
// of reordering flex children, we physically reorder the <tr> nodes inside each
// <tbody data-sortable> with appendChild. Moving an already-initialised node
// preserves its live Alpine state (the row's dossier-click handler and the
// shortlist-star nested x-data), so a re-sort never resets a toggled star.
//
// Each table wraps itself in its own x-data="sortableTable()" scope; the scout
// report renders one scope per bucket section, so the buckets sort independently.
export default function sortableTable() {
    return {
        sortCol: null,
        sortDir: 'desc',
        // Compared as strings; every other column compares numerically.
        textCols: ['name', 'team'],
        // Columns whose first click sorts ascending; all others sort descending first.
        ascFirstCols: ['name', 'team', 'pos'],

        init() {
            // Capture each tbody's server order so the 3rd click can restore it.
            // appendChild physically moves nodes, so (unlike the squad page's
            // CSS `order=''` reset) the original sequence must be remembered.
            this.$nextTick(() => {
                this._tbodies().forEach((tb) => {
                    tb._originalOrder = Array.from(tb.children).filter((n) => n.tagName === 'TR');
                });
                this.applySort();
            });
        },

        _tbodies() {
            return Array.from(this.$root.querySelectorAll('tbody[data-sortable]'));
        },

        toggleSort(col) {
            if (this.sortCol === col) {
                const firstDir = this.ascFirstCols.includes(col) ? 'asc' : 'desc';
                if (this.sortDir === firstDir) {
                    // Second click: flip direction.
                    this.sortDir = firstDir === 'asc' ? 'desc' : 'asc';
                } else {
                    // Third click: restore the server order.
                    this.sortCol = null;
                    this.sortDir = 'desc';
                }
            } else {
                this.sortCol = col;
                this.sortDir = this.ascFirstCols.includes(col) ? 'asc' : 'desc';
            }
            this.applySort();
        },

        applySort() {
            const col = this.sortCol;
            const isText = col !== null && this.textCols.includes(col);
            const dir = this.sortDir === 'asc' ? 1 : -1;
            this._tbodies().forEach((tb) => {
                if (col === null) {
                    (tb._originalOrder || []).forEach((tr) => tb.appendChild(tr));
                    return;
                }
                const key = 'sort' + col.charAt(0).toUpperCase() + col.slice(1);
                const rows = Array.from(tb.children).filter((n) => n.tagName === 'TR');
                rows.sort((a, b) => {
                    const av = a.dataset[key] ?? '';
                    const bv = b.dataset[key] ?? '';
                    if (isText) return dir * String(av).localeCompare(String(bv));
                    return dir * ((parseFloat(av) || 0) - (parseFloat(bv) || 0));
                }).forEach((tr) => tb.appendChild(tr));
            });
        },
    };
}
