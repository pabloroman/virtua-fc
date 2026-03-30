import {
    createCanvasContext,
    fillBackground,
    drawTeamHeader,
    drawStatsRow,
    drawDivider,
    drawSectionLabel,
    drawBrandFooter,
    trimAndDownload,
} from './modules/canvas-image';

export default function seasonSummary(config) {
    return {
        teamName: config.teamName,
        teamCrestUrl: config.teamCrestUrl,
        subtitle: config.subtitle,
        subtitleColor: config.subtitleColor,
        record: config.record,
        highlights: config.highlights,
        homeRecord: config.homeRecord,
        awayRecord: config.awayRecord,
        otherCompetitions: config.otherCompetitions,
        labels: config.labels,

        async downloadSeasonImage() {
            const { canvas, ctx, width, padding, contentWidth } = createCanvasContext(800, 1400);
            fillBackground(ctx, width, 1400);

            await document.fonts.ready;

            let y = padding;

            // Team header
            y = await drawTeamHeader(ctx, {
                crestUrl: this.teamCrestUrl,
                name: this.teamName,
                subtitle: this.subtitle,
                subtitleColor: this.subtitleColor,
                padding, width, y,
            });

            // Stats row: P / W / D / L / GF / GA / GD / Pts
            const gd = this.record.gf - this.record.ga;
            y = drawStatsRow(ctx, [
                { label: this.labels.played, value: this.record.played, color: '#ffffff' },
                { label: this.labels.won, value: this.record.won, color: '#22c55e' },
                { label: this.labels.drawn, value: this.record.drawn, color: '#94a3b8' },
                { label: this.labels.lost, value: this.record.lost, color: '#ef4444' },
                { label: this.labels.gf, value: this.record.gf, color: '#ffffff' },
                { label: this.labels.ga, value: this.record.ga, color: '#ffffff' },
                { label: this.labels.gd, value: (gd >= 0 ? '+' : '') + gd, color: gd >= 0 ? '#22c55e' : '#ef4444' },
                { label: this.labels.pts, value: this.record.pts, color: '#f59e0b' },
            ], { padding, contentWidth, y });

            // Team highlights (top scorer, assister, appearances, MVP)
            if (this.highlights.length > 0) {
                drawSectionLabel(ctx, this.labels.teamHighlights, padding, y);
                y += 18;

                for (const h of this.highlights) {
                    ctx.fillStyle = '#e2e8f0';
                    ctx.font = '400 14px Inter, sans-serif';
                    ctx.fillText(h.playerName, padding, y);

                    // Value + label on the right
                    ctx.fillStyle = '#cbd5e1';
                    ctx.font = '600 13px Inter, sans-serif';
                    const valText = String(h.value);
                    const rightEdge = width - padding;
                    const valWidth = ctx.measureText(valText).width;

                    ctx.fillStyle = '#64748b';
                    ctx.font = '600 10px Inter, sans-serif';
                    const lblText = h.label.toUpperCase();
                    const lblWidth = ctx.measureText(lblText).width;
                    ctx.fillText(lblText, rightEdge - lblWidth, y);

                    ctx.fillStyle = '#cbd5e1';
                    ctx.font = '600 13px Inter, sans-serif';
                    ctx.fillText(valText, rightEdge - lblWidth - valWidth - 6, y);

                    y += 22;
                }
                y += 14;

                drawDivider(ctx, padding, width - padding, y);
                y += 14;
            }

            // Home / Away records
            y += 8;
            drawSectionLabel(ctx, this.labels.homeRecord, padding, y);
            drawSectionLabel(ctx, this.labels.awayRecord, padding + contentWidth / 2, y);
            y += 18;

            const drawRecord = (record, x) => {
                const items = [
                    { value: record.w, color: '#22c55e', label: this.labels.won },
                    { value: record.d, color: '#94a3b8', label: this.labels.drawn },
                    { value: record.l, color: '#ef4444', label: this.labels.lost },
                ];
                let offsetX = x;
                for (const item of items) {
                    ctx.fillStyle = item.color;
                    ctx.font = 'bold 20px Inter, sans-serif';
                    const vText = String(item.value);
                    ctx.fillText(vText, offsetX, y + 2);
                    offsetX += ctx.measureText(vText).width + 3;

                    ctx.fillStyle = '#64748b';
                    ctx.font = '600 10px Inter, sans-serif';
                    ctx.fillText(item.label, offsetX, y + 2);
                    offsetX += ctx.measureText(item.label).width + 12;
                }
            };

            drawRecord(this.homeRecord, padding);
            drawRecord(this.awayRecord, padding + contentWidth / 2);
            y += 20;

            // Other competitions
            if (this.otherCompetitions.length > 0) {
                drawDivider(ctx, padding, width - padding, y);
                y += 20;

                drawSectionLabel(ctx, this.labels.otherCompetitions, padding, y);
                y += 18;

                for (const comp of this.otherCompetitions) {
                    ctx.fillStyle = '#e2e8f0';
                    ctx.font = '600 14px Inter, sans-serif';
                    ctx.fillText(comp.name, padding, y);

                    ctx.fillStyle = comp.isChampion ? '#f59e0b' : '#94a3b8';
                    ctx.font = '400 13px Inter, sans-serif';
                    const resultText = comp.result;
                    ctx.fillText(resultText, width - padding - ctx.measureText(resultText).width, y);

                    y += 22;
                }
            }

            y = drawBrandFooter(ctx, width, y, { tagline: 'Dirige a tu club en virtuafc.com' });
            trimAndDownload(canvas, y, this.teamName.replace(/[^a-zA-Z0-9]/g, '_') + '_season.png');
        },
    };
}
