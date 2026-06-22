// Generic lazy-loader for a server-rendered HTML fragment: fetches the URL on
// init, shows a spinner while in flight, then swaps the markup in via x-html.
// Keeps heavy panels (e.g. the scouting hub's latest-results list) off the
// initial render so the host page stays fast.
export default function fragmentLoader(config) {
    return {
        loading: true,
        content: '',

        init() {
            fetch(config.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => r.text())
                .then((html) => {
                    this.content = html;
                    this.loading = false;
                })
                .catch(() => {
                    this.loading = false;
                });
        },
    };
}
