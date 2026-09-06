/**
 * Reveals an already-decided cup draw one tie at a time.
 *
 * The pairings were written when the round was drawn — this is presentation
 * only, so skipping simply jumps to the end state rather than resolving
 * anything. Step time is clamped at both ends: a 16-tie round would otherwise
 * crawl, and a 2-tie semi-final would flash past without reading as a reveal.
 */
export default function drawCeremony(count) {
    return {
        revealed: 0,
        timer: null,

        start() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this.revealed = count;
                return;
            }

            const step = Math.min(900, Math.max(250, 5000 / count));

            this.timer = setInterval(() => {
                this.revealed++;

                // Sixteen ghost rows are taller than a 375px viewport, so without
                // this the ties land off-screen while the user stares at nothing.
                this.$el.querySelectorAll('[data-tie]')[this.revealed - 1]
                    ?.scrollIntoView({ block: 'center', behavior: 'smooth' });

                if (this.revealed >= count) {
                    this.skip();
                }
            }, step);
        },

        skip() {
            clearInterval(this.timer);
            this.revealed = count;
        },
    };
}
