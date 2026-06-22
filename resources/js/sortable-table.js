// Alpine component for the scouting/transfer-market tables built from
// <x-explore-player-row>. It mirrors the squad overview's sort state machine
// (resources/js/squad-overview.js) — same 3-click cycle and header UX via the
// shared <x-squad.sort-header>/<x-squad.sort-pill> buttons — but those rows live
// in a real <table>, where the CSS `order` property does NOT apply. So instead
// of reordering flex children, we physically reorder the <tr> nodes inside each
// <tbody data-sortable> with appendChild.
//
// Critical: the appendChild moves MUST run inside Alpine.mutateDom(). Each row
// has been initialised by Alpine (it carries its own x-data dossier-click
// handler, plus the offer button and shortlist-star nested x-data). appendChild
// on an already-initialised node is reported to Alpine's MutationObserver as a
// node *removal* — and Alpine responds by running destroyTree() on it, tearing
// down every directive and event listener in the row (the offer button and
// shortlist star silently stop working, with no console error). mutateDom pauses
// the observer for the duration of the reorder so the moves are invisible to it
// and the rows keep their live state. This is the same guard Alpine's own x-sort
// plugin uses when it reorders nodes.
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
            // Pause Alpine's MutationObserver while we move the <tr> nodes, so the
            // reorder doesn't trip destroyTree() on each already-initialised row
            // (see the file header for why). The sort comparison itself is pure;
            // only the appendChild moves need to be hidden from the observer.
            window.Alpine.mutateDom(() => {
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
            });
        },
    };
}
