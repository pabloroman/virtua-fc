/**
 * Shared pitch rendering utilities used by both the lineup page and the live match tactical panel.
 *
 * This module extracts common logic for:
 * - Converting formation slot grid cells to pitch coordinates
 * - Generating shirt badge styles (team colors, patterns)
 * - Player number/initial display
 * - OVR badge color classes
 * - Position badge colors
 * - Energy display helpers (live match context)
 * - Auto-assigning players to formation slots
 */

/**
 * Convert a grid cell coordinate to a pitch percentage coordinate.
 */
export function cellToCoords(col, row, gridCols, gridRows) {
    return {
        x: col * (100 / gridCols) + (100 / (gridCols * 2)),
        y: row * (100 / gridRows) + (100 / (gridRows * 2)),
    };
}

/**
 * Get effective (x, y) position for a slot, using custom pitch position if set.
 * Falls back to formation default cell.
 */
export function getEffectivePosition(slotId, pitchPositions, currentSlots, gridCols, gridRows) {
    const customPos = pitchPositions?.[String(slotId)];
    if (customPos) {
        return cellToCoords(customPos[0], customPos[1], gridCols, gridRows);
    }
    const slot = currentSlots.find(s => s.id === slotId);
    return slot ? cellToCoords(slot.col, slot.row, gridCols, gridRows) : null;
}

/**
 * Get initials from a player name.
 */
export function getInitials(name) {
    if (!name) return '??';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) {
        return parts[0].substring(0, 2).toUpperCase();
    }
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

/**
 * Generate inline CSS for the player badge background based on team shirt colors.
 */
export function getShirtStyle(role, teamColors) {
    if (role === 'Goalkeeper') {
        return 'background: linear-gradient(to bottom right, #FBBF24, #D97706)';
    }

    const tc = teamColors;
    if (!tc) return 'background: linear-gradient(to bottom right, #3B82F6, #1D4ED8)';

    const p = tc.primary;
    const s = tc.secondary;

    switch (tc.pattern) {
        case 'stripes':
            return `background: linear-gradient(90deg, ${s} 3px, ${p} 3px, ${p} 9px, ${s} 9px); background-size: 12px 100%; background-position: center`;
        case 'hoops':
            return `background: linear-gradient(0deg, ${s} 3px, ${p} 3px, ${p} 9px, ${s} 9px); background-size: 100% 12px; background-position: center`;
        case 'sash':
            return `background: linear-gradient(135deg, ${p} 0%, ${p} 35%, ${s} 35%, ${s} 65%, ${p} 65%, ${p} 100%)`;
        case 'bar':
            return `background: linear-gradient(90deg, ${p} 0%, ${p} 35%, ${s} 35%, ${s} 65%, ${p} 65%, ${p} 100%)`;
        case 'halves':
            return `background: linear-gradient(90deg, ${p} 50%, ${s} 50%)`;
        default:
            return `background: ${p}`;
    }
}

/**
 * Get inline style for the player number text, including backdrop for patterned shirts.
 */
export function getNumberStyle(role, teamColors) {
    if (role === 'Goalkeeper') {
        return 'color: #FFFFFF; text-shadow: 0 1px 2px rgba(0,0,0,0.5)';
    }
    const tc = teamColors;
    if (!tc) return 'color: #FFFFFF; text-shadow: 0 1px 2px rgba(0,0,0,0.5)';

    const color = tc.number || '#FFFFFF';

    if (tc.pattern !== 'solid') {
        const backdrop = getBackdropColor(tc);
        return `color: ${color}; background: ${backdrop}CC; text-shadow: 0 1px 2px rgba(0,0,0,0.15)`;
    }

    return `color: ${color}; text-shadow: 0 1px 2px rgba(0,0,0,0.2)`;
}

function getBackdropColor(tc) {
    const numLum = hexLuminance(tc.number);
    const priLum = hexLuminance(tc.primary);
    const secLum = hexLuminance(tc.secondary);
    return Math.abs(numLum - priLum) >= Math.abs(numLum - secLum) ? tc.primary : tc.secondary;
}

function hexLuminance(hex) {
    if (!hex || hex.length < 7) return 0.5;
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;
    return 0.299 * r + 0.587 * g + 0.114 * b;
}

/**
 * Get CSS class for OVR badge based on score.
 */
export function getOvrBadgeClasses(score) {
    if (score >= 80) return 'bg-accent-green text-white';
    if (score >= 70) return 'bg-lime-500 text-white';
    if (score >= 60) return 'bg-accent-gold text-white';
    return 'bg-accent-orange text-white';
}

/**
 * Get CSS class for position badge background color.
 */
export function getPositionBadgeColor(group) {
    const colors = {
        'Goalkeeper': 'bg-amber-500',
        'Defender': 'bg-blue-600',
        'Midfielder': 'bg-emerald-600',
        'Forward': 'bg-red-600',
    };
    return colors[group] || 'bg-emerald-600';
}

// =====================================================================
// Energy / Stamina helpers (for live match context)
// =====================================================================

export function calculateDrainRate(physicalAbility, age, positionGroup) {
    const baseDrain = 0.75;
    const physicalBonus = (physicalAbility - 50) * 0.005;
    const agePenalty = Math.max(0, (age - 28)) * 0.015;
    let drain = baseDrain - physicalBonus + agePenalty;
    if (positionGroup === 'Goalkeeper') drain *= 0.5;
    return Math.max(0, drain);
}

export function getPlayerEnergy(player, currentMinute) {
    if (player.minuteEntered === null || player.minuteEntered === undefined) return 100;
    const minutesPlayed = Math.max(0, Math.floor(currentMinute) - player.minuteEntered);
    const drain = calculateDrainRate(player.physicalAbility, player.age, player.positionGroup);
    return Math.max(0, Math.round(100 - drain * minutesPlayed));
}

export function getEnergyColor(energy) {
    if (energy > 60) return 'bg-emerald-500';
    if (energy > 30) return 'bg-amber-400';
    return 'bg-red-500';
}

export function getEnergyBarBg(energy) {
    if (energy > 60) return 'bg-emerald-500/20';
    if (energy > 30) return 'bg-amber-400/20';
    return 'bg-red-500/20';
}

export function getEnergyTextColor(energy) {
    if (energy > 60) return 'text-emerald-600';
    if (energy > 30) return 'text-amber-600';
    return 'text-red-600';
}

// =====================================================================
// Grid & Drag helpers (shared between lineup and live match)
// =====================================================================

/**
 * Extract clientX/clientY from a mouse or touch event.
 */
export function getEventCoords(event) {
    if (event.touches && event.touches.length > 0) {
        return { clientX: event.touches[0].clientX, clientY: event.touches[0].clientY };
    }
    if (event.changedTouches && event.changedTouches.length > 0) {
        return { clientX: event.changedTouches[0].clientX, clientY: event.changedTouches[0].clientY };
    }
    return { clientX: event.clientX, clientY: event.clientY };
}

/**
 * Convert client coordinates to pitch-percentage drag position.
 */
export function getDragPosition(clientX, clientY, pitchEl) {
    if (!pitchEl) return null;
    const rect = pitchEl.getBoundingClientRect();
    const x = ((clientX - rect.left) / rect.width) * 100;
    const y = ((clientY - rect.top) / rect.height) * 100;
    return {
        x: Math.max(0, Math.min(100, x)),
        y: Math.max(0, Math.min(100, y)),
    };
}

/**
 * Convert client coordinates to a grid cell {col, row}.
 */
export function getCellFromClientCoords(clientX, clientY, pitchEl, gridConfig) {
    if (!pitchEl || !gridConfig) return null;
    const rect = pitchEl.getBoundingClientRect();
    const xPct = ((clientX - rect.left) / rect.width) * 100;
    const yPct = ((clientY - rect.top) / rect.height) * 100;
    const pitchY = 100 - yPct;
    const col = Math.round((xPct - 100 / (gridConfig.cols * 2)) / (100 / gridConfig.cols));
    const row = Math.round((pitchY - 100 / (gridConfig.rows * 2)) / (100 / gridConfig.rows));
    return {
        col: Math.max(0, Math.min(gridConfig.cols - 1, col)),
        row: Math.max(0, Math.min(gridConfig.rows - 1, row)),
    };
}

/**
 * Check if a grid cell is valid for a given slot label.
 */
export function isValidGridCell(slotLabel, col, row, gridConfig) {
    if (!gridConfig) return false;
    if (slotLabel === 'GK') {
        const zone = gridConfig.zones['GK'];
        if (!zone) return false;
        const inOwnZone = col >= zone[0] && col <= zone[1] && row >= zone[2] && row <= zone[3];
        return inOwnZone || (row >= 1 && col >= 0 && col < gridConfig.cols);
    }
    // Outfield: rows 1-13 OR the GK cell (4,0)
    if (col >= 0 && col < gridConfig.cols && row >= 1 && row < gridConfig.rows) {
        return true;
    }
    return col === 4 && row === 0;
}

/**
 * Get the zone highlight color class for a position group.
 */
export function getZoneColorClass(role) {
    switch (role) {
        case 'Goalkeeper': return 'bg-amber-500/30 border-amber-400/40';
        case 'Defender': return 'bg-blue-500/30 border-blue-400/40';
        case 'Midfielder': return 'bg-emerald-500/30 border-emerald-400/40';
        case 'Forward': return 'bg-red-500/30 border-red-400/40';
        default: return 'bg-white/20 border-white/30';
    }
}

// Slot assignment has been extracted to modules/slot-assignment.js
