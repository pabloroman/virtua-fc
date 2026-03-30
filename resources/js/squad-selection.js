import {
    createCanvasContext,
    fillBackground,
    drawTeamHeader,
    drawSectionLabel,
    drawBrandFooter,
    trimAndDownload,
    wrapText,
} from './modules/canvas-image';

export default function squadSelection(config) {
    return {
        selectedIds: [],
        activeTab: 'goalkeepers',
        players: config.players,
        maxPlayers: 26,
        groupLabels: config.groupLabels,
        teamName: config.teamName,
        teamCrestUrl: config.teamCrestUrl,
        fifaCode: config.fifaCode,
        gameId: config.gameId,

        togglePlayer(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx > -1) {
                this.selectedIds.splice(idx, 1);
            } else if (this.selectedIds.length < this.maxPlayers) {
                this.selectedIds.push(id);
            }
        },

        isSelected(id) {
            return this.selectedIds.includes(id);
        },

        get totalSelected() {
            return this.selectedIds.length;
        },

        countByGroup(group) {
            return this.players[group].filter(p => this.selectedIds.includes(p.transfermarkt_id)).length;
        },

        get canConfirm() {
            return this.totalSelected === this.maxPlayers;
        },

        get isMaxed() {
            return this.totalSelected >= this.maxPlayers;
        },

        selectedByGroup(group) {
            return this.players[group].filter(p => this.selectedIds.includes(p.transfermarkt_id));
        },

        async downloadSquadImage() {
            const { canvas, ctx, width, padding, contentWidth } = createCanvasContext(800, 1400);
            fillBackground(ctx, width, 1400);

            await document.fonts.ready;

            let y = padding;
            y = await drawTeamHeader(ctx, {
                crestUrl: this.teamCrestUrl,
                name: this.teamName,
                crestRatio: 4 / 3,
                padding, width, y,
            });
            y += 4; // extra spacing before squad list

            const groups = ['goalkeepers', 'defenders', 'midfielders', 'forwards'];
            for (const group of groups) {
                const players = this.selectedByGroup(group);
                if (players.length === 0) continue;

                drawSectionLabel(ctx, this.groupLabels[group], padding, y);
                y += 20;

                ctx.fillStyle = '#e2e8f0';
                ctx.font = '400 15px Inter, sans-serif';
                const text = players.map(p => p.name).join(', ');
                const lines = wrapText(ctx, text, contentWidth);
                for (const line of lines) {
                    ctx.fillText(line, padding, y);
                    y += 22;
                }
                y += 20;
            }

            y = drawBrandFooter(ctx, width, y);
            trimAndDownload(canvas, y, `virtuafc_${this.fifaCode}_${this.gameId}.png`);
        },
    };
}
