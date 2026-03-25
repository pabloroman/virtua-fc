import {
    createCanvasContext,
    fillBackground,
    drawTeamCrest,
    drawTeamName,
    drawDivider,
    drawSectionLabel,
    drawBrandFooter,
    trimAndDownload,
} from './modules/canvas-image';

export default function tournamentSummary(config) {
    return {
        teamName: config.teamName,
        teamCrestUrl: config.teamCrestUrl,
        resultLabel: config.resultLabel,
        isChampion: config.isChampion,
        record: config.record,
        squadByGroup: config.squadByGroup,
        groupLabels: config.groupLabels,
        statLabels: config.statLabels,

        async downloadTournamentImage() {
            const { canvas, ctx, width, padding, contentWidth } = createCanvasContext(800, 2000);
            fillBackground(ctx, width, 2000);

            await document.fonts.ready;

            let y = padding;
            const crestHeight = 52;
            const crestWidth = 69; // 4:3 flag ratio
            const crest = await drawTeamCrest(ctx, this.teamCrestUrl, padding, y, crestWidth, crestHeight);

            const textX = crest.loaded ? padding + crestWidth + 16 : padding;
            drawTeamName(ctx, this.teamName, textX, y + 22);

            // Result badge
            ctx.fillStyle = this.isChampion ? '#f59e0b' : '#94a3b8';
            ctx.font = '700 12px Inter, sans-serif';
            ctx.fillText(this.resultLabel.toUpperCase(), textX, y + 44);

            y += crestHeight + 28;
            drawDivider(ctx, padding, width - padding, y);
            y += 20;

            // Stats row
            const gd = this.record.goalsFor - this.record.goalsAgainst;
            const stats = [
                { label: this.statLabels.played, value: this.record.played, color: '#ffffff' },
                { label: this.statLabels.won, value: this.record.won, color: '#22c55e' },
                { label: this.statLabels.drawn, value: this.record.drawn, color: '#94a3b8' },
                { label: this.statLabels.lost, value: this.record.lost, color: '#ef4444' },
                { label: this.statLabels.gf, value: this.record.goalsFor, color: '#ffffff' },
                { label: this.statLabels.ga, value: this.record.goalsAgainst, color: '#ffffff' },
                { label: this.statLabels.gd, value: (gd >= 0 ? '+' : '') + gd, color: gd >= 0 ? '#22c55e' : '#ef4444' },
            ];

            const colWidth = contentWidth / stats.length;
            for (let i = 0; i < stats.length; i++) {
                const cx = padding + colWidth * i + colWidth / 2;

                ctx.fillStyle = stats[i].color;
                ctx.font = 'bold 22px Inter, sans-serif';
                const valText = String(stats[i].value);
                ctx.fillText(valText, cx - ctx.measureText(valText).width / 2, y + 4);

                ctx.fillStyle = '#64748b';
                ctx.font = '600 9px Inter, sans-serif';
                const lblText = stats[i].label.toUpperCase();
                ctx.fillText(lblText, cx - ctx.measureText(lblText).width / 2, y + 20);
            }
            y += 40;

            drawDivider(ctx, padding, width - padding, y);
            y += 20;

            // Column headers for squad
            const nameColX = padding;
            const appsColX = width - padding - 120;
            const goalsColX = width - padding - 70;
            const assistsColX = width - padding - 20;

            ctx.fillStyle = '#64748b';
            ctx.font = '600 9px Inter, sans-serif';
            let hdr = this.statLabels.apps.toUpperCase();
            ctx.fillText(hdr, appsColX - ctx.measureText(hdr).width / 2, y);
            hdr = this.statLabels.goals.toUpperCase();
            ctx.fillText(hdr, goalsColX - ctx.measureText(hdr).width / 2, y);
            hdr = this.statLabels.assists.toUpperCase();
            ctx.fillText(hdr, assistsColX - ctx.measureText(hdr).width / 2, y);
            y += 18;

            // Squad by position group
            const groupOrder = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];
            for (const group of groupOrder) {
                const players = this.squadByGroup[group];
                if (!players || players.length === 0) continue;

                drawSectionLabel(ctx, this.groupLabels[group] || group, padding, y);
                y += 18;

                for (const p of players) {
                    ctx.fillStyle = '#e2e8f0';
                    ctx.font = '400 14px Inter, sans-serif';
                    ctx.fillText(p.name, nameColX, y);

                    ctx.fillStyle = '#cbd5e1';
                    ctx.font = '600 13px Inter, sans-serif';
                    let val = String(p.appearances);
                    ctx.fillText(val, appsColX - ctx.measureText(val).width / 2, y);

                    ctx.fillStyle = p.goals > 0 ? '#cbd5e1' : '#475569';
                    val = String(p.goals);
                    ctx.fillText(val, goalsColX - ctx.measureText(val).width / 2, y);

                    ctx.fillStyle = p.assists > 0 ? '#cbd5e1' : '#475569';
                    val = String(p.assists);
                    ctx.fillText(val, assistsColX - ctx.measureText(val).width / 2, y);

                    y += 20;
                }
                y += 10;
            }

            y = drawBrandFooter(ctx, width, y);
            trimAndDownload(canvas, y, this.teamName.replace(/[^a-zA-Z0-9]/g, '_') + '_tournament.png');
        },
    };
}
