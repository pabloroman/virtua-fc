/**
 * Shared Canvas drawing primitives for generating downloadable PNG images.
 * Used by squad-selection.js, tournament-summary.js, and season-summary.js.
 */

const DEFAULT_WIDTH = 800;
const DEFAULT_PADDING = 40;
const SCALE = 2;
const COLORS = {
    background: '#0b1120',
    white: '#ffffff',
    muted: '#64748b',
    text: '#e2e8f0',
    divider: 'rgba(255, 255, 255, 0.08)',
    badgeRed: '#dc2626',
};

export function createCanvasContext(width = DEFAULT_WIDTH, height = 1400) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const padding = DEFAULT_PADDING;

    canvas.width = width * SCALE;
    canvas.height = height * SCALE;
    ctx.scale(SCALE, SCALE);

    return { canvas, ctx, width, padding, contentWidth: width - padding * 2 };
}

export function fillBackground(ctx, width, height) {
    ctx.fillStyle = COLORS.background;
    ctx.fillRect(0, 0, width, height);
}

export async function drawTeamCrest(ctx, crestUrl, x, y, width, height) {
    try {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = reject;
            img.src = crestUrl;
        });
        const radius = 4;
        ctx.save();
        ctx.beginPath();
        ctx.roundRect(x, y, width, height, radius);
        ctx.clip();
        ctx.drawImage(img, x, y, width, height);
        ctx.restore();
        return { loaded: true, width, height };
    } catch {
        return { loaded: false, width, height };
    }
}

export function drawTeamName(ctx, name, x, y) {
    ctx.fillStyle = COLORS.white;
    ctx.font = 'bold 24px Inter, sans-serif';
    ctx.fillText(name, x, y);
}

export function drawDivider(ctx, x1, x2, y) {
    ctx.strokeStyle = COLORS.divider;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(x1, y);
    ctx.lineTo(x2, y);
    ctx.stroke();
}

export function drawSectionLabel(ctx, text, x, y) {
    ctx.fillStyle = COLORS.muted;
    ctx.font = '600 11px Inter, sans-serif';
    ctx.fillText(text.toUpperCase(), x, y);
}

/**
 * Draw a team header block: crest + name + optional subtitle line.
 * Returns the new y position after the header (including divider).
 */
export async function drawTeamHeader(ctx, { crestUrl, name, subtitle, subtitleColor, padding, width, y, crestRatio = 1 }) {
    const crestHeight = 52;
    const crestWidth = Math.round(crestHeight * crestRatio);
    const crest = await drawTeamCrest(ctx, crestUrl, padding, y, crestWidth, crestHeight);

    const textX = crest.loaded ? padding + crestWidth + 16 : padding;
    drawTeamName(ctx, name, textX, y + 22);

    if (subtitle) {
        ctx.fillStyle = subtitleColor || COLORS.muted;
        ctx.font = '700 12px Inter, sans-serif';
        ctx.fillText(subtitle.toUpperCase(), textX, y + 44);
    }

    y += crestHeight + 24;
    drawDivider(ctx, padding, width - padding, y);
    y += 24;
    return y;
}

/**
 * Draw a centered stats row with columns of value + label.
 * Each stat: { label: string, value: string|number, color: string }.
 * Returns the new y position after the row (including bottom divider).
 */
export function drawStatsRow(ctx, stats, { padding, contentWidth, y }) {
    const colWidth = contentWidth / stats.length;
    for (let i = 0; i < stats.length; i++) {
        const cx = padding + colWidth * i + colWidth / 2;

        ctx.fillStyle = stats[i].color;
        ctx.font = 'bold 22px Inter, sans-serif';
        const valText = String(stats[i].value);
        ctx.fillText(valText, cx - ctx.measureText(valText).width / 2, y + 4);

        ctx.fillStyle = COLORS.muted;
        ctx.font = '600 9px Inter, sans-serif';
        const lblText = stats[i].label.toUpperCase();
        ctx.fillText(lblText, cx - ctx.measureText(lblText).width / 2, y + 20);
    }
    y += 40;

    drawDivider(ctx, padding, padding + contentWidth, y);
    y += 20;
    return y;
}

export function drawBrandFooter(ctx, width, y, { tagline = 'Juega a ser seleccionador en virtuafc.com' } = {}) {
    const padding = DEFAULT_PADDING;

    y += 16;
    drawDivider(ctx, padding, width - padding, y);
    y += 16;

    // Virtua FC badge — compensate skew to visually center
    const badgeText = 'Virtua FC';
    ctx.font = '800 14px "Barlow Semi Condensed", sans-serif';
    const badgeWidth = ctx.measureText(badgeText).width + 16;
    const badgeHeight = 22;
    const skewFactor = 0.21;
    const badgeX = (width - badgeWidth) / 2 + skewFactor * (y + badgeHeight / 2);

    ctx.save();
    ctx.transform(1, 0, -skewFactor, 1, 0, 0); // skew-x ~-12deg
    ctx.fillStyle = COLORS.badgeRed;
    ctx.fillRect(badgeX, y, badgeWidth, badgeHeight);
    ctx.fillStyle = COLORS.white;
    ctx.fillText(badgeText, badgeX + 8, y + 16);
    ctx.restore();

    y += badgeHeight + 18;

    // Tagline
    ctx.fillStyle = COLORS.muted;
    ctx.font = '400 11px Inter, sans-serif';
    const tagWidth = ctx.measureText(tagline).width;
    ctx.fillText(tagline, (width - tagWidth) / 2, y);

    y += padding;
    return y;
}

export function trimAndDownload(canvas, y, filename) {
    const finalCanvas = document.createElement('canvas');
    finalCanvas.width = canvas.width;
    finalCanvas.height = y * SCALE;
    const fctx = finalCanvas.getContext('2d');
    fctx.drawImage(canvas, 0, 0);

    const link = document.createElement('a');
    link.download = filename;
    link.href = finalCanvas.toDataURL('image/png');
    link.click();
}

export function wrapText(ctx, text, maxWidth) {
    const words = text.split(' ');
    const lines = [];
    let line = '';
    for (const word of words) {
        const test = line ? line + ' ' + word : word;
        if (ctx.measureText(test).width > maxWidth && line) {
            lines.push(line);
            line = word;
        } else {
            line = test;
        }
    }
    if (line) lines.push(line);
    return lines;
}
