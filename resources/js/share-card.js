import html2canvas from 'html2canvas';

export default () => ({
    generating: false,
    ready: false,
    imageUrl: null,
    showModal: false,

    async generateImage() {
        this.generating = true;
        this.ready = false;

        try {
            const cardEl = this.$refs.shareCard;
            if (!cardEl) return;

            // Ensure the card is visible for rendering
            cardEl.style.position = 'absolute';
            cardEl.style.left = '-9999px';
            cardEl.style.display = 'block';

            const canvas = await html2canvas(cardEl, {
                backgroundColor: null,
                scale: 2,
                useCORS: true,
                allowTaint: true,
                width: 440,
                height: 660,
            });

            // Hide the card again
            cardEl.style.display = 'none';
            cardEl.style.position = '';
            cardEl.style.left = '';

            this.imageUrl = canvas.toDataURL('image/png');
            this.ready = true;
        } catch (error) {
            console.error('Share card generation failed:', error);
        } finally {
            this.generating = false;
        }
    },

    async openShareModal() {
        this.showModal = true;
        if (!this.ready) {
            await this.generateImage();
        }
    },

    closeModal() {
        this.showModal = false;
    },

    async downloadImage() {
        if (!this.imageUrl) return;

        const link = document.createElement('a');
        link.download = 'virtua-fc-challenge.png';
        link.href = this.imageUrl;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    },

    async shareImage() {
        if (!this.imageUrl) return;

        try {
            // Convert data URL to blob for Web Share API
            const response = await fetch(this.imageUrl);
            const blob = await response.blob();
            const file = new File([blob], 'virtua-fc-challenge.png', { type: 'image/png' });

            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                await navigator.share({
                    files: [file],
                    title: 'VirtuaFC',
                    text: this.$refs.shareText?.value || '',
                });
            } else if (navigator.share) {
                // Fallback: share text only
                await navigator.share({
                    text: this.$refs.shareText?.value || '',
                });
            } else {
                // Final fallback: download
                this.downloadImage();
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                // User cancelled share, that's ok
                this.downloadImage();
            }
        }
    },
});
