// Pre-match flow for the "advance" control in the game header. On request it
// either auto-advances (when the user has opted into auto-lineup, locally or per
// the server's `lineupReady` JSON) by submitting the hidden form, or it fetches
// the pre-match confirmation fragment and opens the modal.
export default function preMatchLoader() {
    return {
        loading: false,
        submitting: false,
        content: '',

        loadPreMatch(url) {
            if (this.submitting) return;
            if (localStorage.getItem('autoLineup') === '1') {
                this.submitting = true;
                window.dispatchEvent(new CustomEvent('matchday-advance-starting'));
                this.$refs.autoAdvanceForm.submit();
                return;
            }
            this.content = '';
            this.loading = true;
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => {
                    const contentType = r.headers.get('content-type') || '';
                    if (contentType.includes('application/json')) {
                        return r.json().then((data) => {
                            if (data.lineupReady && !this.submitting) {
                                this.submitting = true;
                                window.dispatchEvent(new CustomEvent('matchday-advance-starting'));
                                this.$refs.autoAdvanceForm.submit();
                            }
                        });
                    }
                    this.$dispatch('open-modal', 'pre-match');
                    return r.text().then((html) => {
                        this.content = html;
                        this.loading = false;
                    });
                })
                .catch(() => {
                    this.loading = false;
                });
        },
    };
}
