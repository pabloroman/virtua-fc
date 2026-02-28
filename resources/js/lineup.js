export default function lineupManager(config) {
    return {
        // State
        activeLineupTab: 'squad',
        selectedPlayers: config.currentLineup || [],
        selectedFormation: config.currentFormation,
        selectedMentality: config.currentMentality,
        selectedPlayingStyle: config.currentPlayingStyle || 'balanced',
        selectedPressing: config.currentPressing || 'standard',
        selectedDefLine: config.currentDefLine || 'normal',
        playingStyles: config.playingStyles || [],
        pressingOptions: config.pressingOptions || [],
        defensiveLineOptions: config.defensiveLineOptions || [],
        autoLineup: config.autoLineup || [],

        // Manual slot assignments: { slotId: playerId }
        // When a user explicitly assigns a player to a slot, it's tracked here.
        manualAssignments: config.currentSlotAssignments || {},

        // Currently selected slot for manual assignment (null = no slot selected)
        selectedSlot: null,
        // Player currently occupying the selected slot (for replacement tracking)
        selectedSlotPlayer: null,
        // Player being hovered in the list (for pitch highlight)
        hoveredPlayerId: null,

        // Grid positioning state
        gridConfig: config.gridConfig || null,
        pitchPositions: config.currentPitchPositions || {},
        gridMode: false,
        positioningSlotId: null,
        draggingSlotId: null,
        dragPosition: null, // { x, y } in percentage coordinates during drag
        _pitchEl: null, // reference to pitch container DOM element

        // Dirty tracking: snapshot of initial state to detect unsaved changes
        _initialPlayers: null,
        _initialFormation: null,
        _initialMentality: null,
        _initialPlayingStyle: null,
        _initialPressing: null,
        _initialDefLine: null,
        _initialAssignments: null,
        _initialPitchPositions: null,
        _isSaving: false,

        // Server data
        playersData: config.playersData,
        formationSlots: config.formationSlots,
        slotCompatibility: config.slotCompatibility,
        autoLineupUrl: config.autoLineupUrl,
        teamColors: config.teamColors,
        translations: config.translations,
        formationModifiers: config.formationModifiers || {},
        opponentAverage: config.opponentAverage || 0,
        opponentFormation: config.opponentFormation || null,
        opponentMentality: config.opponentMentality || null,
        userTeamAverage: config.userTeamAverage || 0,
        isHome: config.isHome || false,

        init() {
            // Snapshot the initial state for dirty detection
            this._initialPlayers = [...(config.currentLineup || [])].sort();
            this._initialFormation = config.currentFormation;
            this._initialMentality = config.currentMentality;
            this._initialPlayingStyle = config.currentPlayingStyle || 'balanced';
            this._initialPressing = config.currentPressing || 'standard';
            this._initialDefLine = config.currentDefLine || 'normal';
            this._initialAssignments = JSON.stringify(config.currentSlotAssignments || {});
            this._initialPitchPositions = JSON.stringify(config.currentPitchPositions || {});

            // Warn on navigation away with unsaved changes
            window.addEventListener('beforeunload', (e) => {
                if (this._isSaving) return;
                if (this.isDirty) {
                    e.preventDefault();
                }
            });

            // Global event listeners for drag-and-drop (cleaned up is not needed since page-scoped)
            document.addEventListener('mousemove', (e) => this._onDragMove(e));
            document.addEventListener('mouseup', (e) => this._onDragEnd(e));
            document.addEventListener('touchmove', (e) => this._onDragMove(e), { passive: false });
            document.addEventListener('touchend', (e) => this._onDragEnd(e));
        },

        get isDirty() {
            const playersChanged = JSON.stringify([...this.selectedPlayers].sort()) !== JSON.stringify(this._initialPlayers);
            const formationChanged = this.selectedFormation !== this._initialFormation;
            const mentalityChanged = this.selectedMentality !== this._initialMentality;
            const styleChanged = this.selectedPlayingStyle !== this._initialPlayingStyle;
            const pressingChanged = this.selectedPressing !== this._initialPressing;
            const defLineChanged = this.selectedDefLine !== this._initialDefLine;
            const assignmentsChanged = JSON.stringify(this.manualAssignments) !== this._initialAssignments;
            const positionsChanged = JSON.stringify(this.pitchPositions) !== this._initialPitchPositions;
            return playersChanged || formationChanged || mentalityChanged || styleChanged || pressingChanged || defLineChanged || assignmentsChanged || positionsChanged;
        },

        // Computed
        get selectedCount() { return this.selectedPlayers.length },
        get currentSlots() { return this.formationSlots[this.selectedFormation] || [] },
        get hasManualAssignments() { return Object.keys(this.manualAssignments).length > 0 },

        get teamAverage() {
            if (this.selectedPlayers.length === 0) return 0;
            let total = 0;
            this.selectedPlayers.forEach(id => {
                if (this.playersData[id]) {
                    total += this.playersData[id].overallScore;
                }
            });
            return Math.round(total / this.selectedPlayers.length);
        },

        get coachTips() {
            const tips = [];
            const t = this.translations;
            const oppAvg = this.opponentAverage;
            const userAvg = this.teamAverage || this.userTeamAverage;
            const diff = userAvg - oppAvg;
            const isWeaker = oppAvg > 0 && diff <= -5;
            const isStronger = oppAvg > 0 && diff >= 5;
            const fmMods = this.formationModifiers[this.selectedFormation];
            const oppFm = this.opponentFormation;
            const oppMent = this.opponentMentality;
            const oppMentLabel = oppMent ? (t['mentality_' + oppMent] || oppMent) : '';

            // Opponent tactical tips (using predicted formation/mentality)
            if (oppMent === 'defensive' && oppFm) {
                tips.push({ id: 'opp_defensive', priority: 1, type: 'info', message: t.coach_opponent_defensive_setup.replace(':formation', oppFm).replace(':mentality', oppMentLabel) });
            } else if (oppMent === 'attacking' && oppFm) {
                tips.push({ id: 'opp_attacking', priority: 1, type: 'info', message: t.coach_opponent_attacking_setup.replace(':formation', oppFm).replace(':mentality', oppMentLabel) });
            } else if (isWeaker && this.selectedMentality !== 'defensive') {
                tips.push({ id: 'defensive_recommended', priority: 1, type: 'warning', message: t.coach_defensive_recommended });
            }
            if (isStronger && this.selectedMentality !== 'attacking' && oppMent !== 'attacking') {
                tips.push({ id: 'attacking_vs_weaker', priority: 4, type: 'info', message: t.coach_attacking_recommended });
            }
            if (isWeaker && fmMods && fmMods.attack > 1.0) {
                tips.push({ id: 'risky_formation', priority: 2, type: 'warning', message: t.coach_risky_formation });
            }
            // Opponent playing 5-at-the-back
            if (oppFm && (oppFm.startsWith('5-') || oppFm === '5-3-2' || oppFm === '5-4-1')) {
                tips.push({ id: 'opp_deep_block', priority: 3, type: 'info', message: t.coach_opponent_deep_block });
            }

            // Fitness tips (only if lineup has players)
            if (this.selectedPlayers.length > 0) {
                const criticalNames = [];
                let lowFitnessCount = 0;
                this.selectedPlayers.forEach(id => {
                    const p = this.playersData[id];
                    if (!p) return;
                    if (p.fitness < 50) criticalNames.push(p.name);
                    else if (p.fitness < 70) lowFitnessCount++;
                });
                if (criticalNames.length > 0) {
                    tips.push({ id: 'critical_fitness', priority: 0, type: 'warning', message: t.coach_critical_fitness.replace(':names', criticalNames.join(', ')) });
                }
                if (lowFitnessCount > 0) {
                    tips.push({ id: 'low_fitness', priority: 1, type: 'warning', message: t.coach_low_fitness.replace(':count', lowFitnessCount) });
                }

                // Morale tips
                let lowMoraleCount = 0;
                this.selectedPlayers.forEach(id => {
                    const p = this.playersData[id];
                    if (p && p.morale < 60) lowMoraleCount++;
                });
                if (lowMoraleCount > 0) {
                    tips.push({ id: 'low_morale', priority: 2, type: 'warning', message: t.coach_low_morale.replace(':count', lowMoraleCount) });
                }
            }

            // Bench frustration: quality non-selected players losing morale
            const selectedSet = new Set(this.selectedPlayers);
            let benchFrustrationCount = 0;
            Object.values(this.playersData).forEach(p => {
                if (!selectedSet.has(p.id) && p.isAvailable && p.overallScore >= 70 && p.morale < 65) {
                    benchFrustrationCount++;
                }
            });
            if (benchFrustrationCount >= 2) {
                tips.push({ id: 'bench_frustration', priority: 4, type: 'info', message: t.coach_bench_frustration.replace(':count', benchFrustrationCount) });
            }

            // Home advantage (low priority filler)
            if (this.isHome) {
                tips.push({ id: 'home_advantage', priority: 5, type: 'info', message: t.coach_home_advantage });
            }

            // Sort by priority (lower = more important), limit to 4
            tips.sort((a, b) => a.priority - b.priority);

            // If we have 4+ tips, drop home_advantage to make room for more important ones
            const filtered = tips.length > 4 ? tips.filter(t => t.id !== 'home_advantage') : tips;
            return filtered.slice(0, 4);
        },

        get slotAssignments() {
            const slots = this.currentSlots.map(slot => ({ ...slot, player: null, compatibility: 0, isManual: false }));
            const assigned = new Set();

            // Get all selected players
            const selectedPlayerData = this.selectedPlayers
                .map(id => this.playersData[id])
                .filter(p => p);

            // First: honor manual assignments
            for (const [slotId, playerId] of Object.entries(this.manualAssignments)) {
                const slot = slots.find(s => s.id === parseInt(slotId));
                const player = selectedPlayerData.find(p => p.id === playerId);
                if (slot && player && !assigned.has(player.id)) {
                    const compatibility = this.getSlotCompatibility(player.position, slot.label);
                    slot.player = { ...player, compatibility };
                    slot.compatibility = compatibility;
                    slot.isManual = true;
                    assigned.add(player.id);
                }
            }

            // Auto-assign remaining players to remaining slots
            const emptySlots = slots.filter(s => !s.player);
            const unassignedPlayers = selectedPlayerData.filter(p => !assigned.has(p.id));

            if (emptySlots.length > 0 && unassignedPlayers.length > 0) {
                this._autoAssignToSlots(emptySlots, unassignedPlayers, assigned, slots);
            }

            return slots;
        },

        // Auto-assign players to empty slots (cross-group flexible)
        _autoAssignToSlots(emptySlots, unassignedPlayers, assigned, allSlots) {
            const rolePriority = { 'Goalkeeper': 0, 'Forward': 1, 'Defender': 2, 'Midfielder': 3 };
            const sortedEmpty = [...emptySlots].sort((a, b) => {
                const aPriority = rolePriority[a.role] ?? 99;
                const bPriority = rolePriority[b.role] ?? 99;
                if (aPriority !== bPriority) return aPriority - bPriority;
                const aCompat = Object.keys(this.slotCompatibility[a.label] || {}).length;
                const bCompat = Object.keys(this.slotCompatibility[b.label] || {}).length;
                return aCompat - bCompat;
            });

            // First pass: assign players with acceptable compatibility (>= 40)
            sortedEmpty.forEach(slot => {
                let bestPlayer = null;
                let bestScore = -1;

                unassignedPlayers.forEach(player => {
                    if (assigned.has(player.id)) return;

                    const compatibility = this.getSlotCompatibility(player.position, slot.label);
                    if (compatibility < 40) return;

                    // Weighted score: 70% player rating, 30% compatibility
                    const weightedScore = (player.overallScore * 0.7) + (compatibility * 0.3);

                    if (weightedScore > bestScore) {
                        bestScore = weightedScore;
                        bestPlayer = { ...player, compatibility };
                    }
                });

                const originalSlot = allSlots.find(s => s.id === slot.id);
                if (originalSlot && bestPlayer) {
                    originalSlot.player = bestPlayer;
                    originalSlot.compatibility = bestPlayer.compatibility;
                    assigned.add(bestPlayer.id);
                }
            });

            // Second pass: fill remaining empty slots with leftover players
            const stillEmpty = allSlots.filter(s => !s.player);
            const stillUnassigned = unassignedPlayers.filter(p => !assigned.has(p.id));

            stillEmpty.forEach((slot, index) => {
                if (stillUnassigned[index]) {
                    const player = stillUnassigned[index];
                    const compatibility = this.getSlotCompatibility(player.position, slot.label);
                    slot.player = { ...player, compatibility };
                    slot.compatibility = compatibility;
                }
            });
        },

        // Methods
        getSlotCompatibility(position, slotCode) {
            return this.slotCompatibility[slotCode]?.[position] ?? 0;
        },

        getCompatibilityDisplay(position, slotCode) {
            const score = this.getSlotCompatibility(position, slotCode);
            if (score >= 100) return { label: this.translations.natural, class: 'text-green-600', ring: 'ring-green-500', score };
            if (score >= 80) return { label: this.translations.veryGood, class: 'text-emerald-600', ring: 'ring-emerald-500', score };
            if (score >= 60) return { label: this.translations.good, class: 'text-lime-600', ring: 'ring-lime-500', score };
            if (score >= 40) return { label: this.translations.okay, class: 'text-yellow-600', ring: 'ring-yellow-500', score };
            if (score >= 20) return { label: this.translations.poor, class: 'text-orange-500', ring: 'ring-orange-500', score };
            return { label: this.translations.unsuitable, class: 'text-red-600', ring: 'ring-red-500', score };
        },

        isSelected(id) { return this.selectedPlayers.includes(id) },

        // Toggle player selection (from player list)
        toggle(id, isUnavailable) {
            if (isUnavailable) return;

            // If a slot is selected, assign this player to that slot instead of toggling
            if (this.selectedSlot !== null) {
                this.assignPlayerToSelectedSlot(id, isUnavailable);
                return;
            }

            if (this.isSelected(id)) {
                this.selectedPlayers = this.selectedPlayers.filter(p => p !== id);
                // Remove any manual assignments for this player
                this._removePlayerFromManualAssignments(id);
            } else if (this.selectedCount < 11) {
                this.selectedPlayers.push(id);
            }
        },

        // Select an empty slot on the pitch for manual assignment
        selectSlot(slotId) {
            if (this.selectedSlot === slotId) {
                this.selectedSlot = null;
                this.selectedSlotPlayer = null;
            } else {
                this.selectedSlot = slotId;
                this.selectedSlotPlayer = null;
            }
        },

        // Assign a player to the currently selected slot
        assignPlayerToSelectedSlot(playerId, isUnavailable) {
            if (isUnavailable || this.selectedSlot === null) return;

            const slotId = this.selectedSlot;
            const slot = this.currentSlots.find(s => s.id === slotId);
            if (!slot) return;

            // Enforce minimum 40 compatibility for manual assignments
            const player = this.playersData[playerId];
            if (player) {
                const compatibility = this.getSlotCompatibility(player.position, slot.label);
                if (compatibility < 40) return;
            }

            // If this player is already manually assigned elsewhere, remove old assignment
            this._removePlayerFromManualAssignments(playerId);

            // Find who currently occupies this slot (manual or auto-assigned)
            const previousPlayerId = this.selectedSlotPlayer || this.manualAssignments[slotId];

            // Set the manual assignment
            this.manualAssignments = { ...this.manualAssignments, [slotId]: playerId };

            // Ensure the player is in the selected 11
            if (!this.isSelected(playerId)) {
                if (this.selectedCount >= 11 && previousPlayerId && this.isSelected(previousPlayerId)) {
                    // Remove the current occupant to make room
                    this.selectedPlayers = this.selectedPlayers.filter(p => p !== previousPlayerId);
                    this._removePlayerFromManualAssignments(previousPlayerId);
                }
                if (this.selectedCount < 11) {
                    this.selectedPlayers.push(playerId);
                }
            }

            this.selectedSlot = null;
            this.selectedSlotPlayer = null;
        },

        // Click on a filled slot on the pitch
        handleSlotClick(slotId, playerId) {
            if (this.selectedSlot === slotId) {
                this.selectedSlot = null;
                this.selectedSlotPlayer = null;
            } else {
                this.selectedSlot = slotId;
                this.selectedSlotPlayer = playerId || null;
            }
        },

        quickSelect() {
            this.selectedPlayers = [...this.autoLineup];
            this.manualAssignments = {};
            this.pitchPositions = {};
            this.selectedSlot = null;
            this.selectedSlotPlayer = null;
            this.positioningSlotId = null;
        },

        clearSelection() {
            this.selectedPlayers = [];
            this.manualAssignments = {};
            this.pitchPositions = {};
            this.selectedSlot = null;
            this.selectedSlotPlayer = null;
            this.positioningSlotId = null;
        },

        async updateAutoLineup() {
            try {
                const response = await fetch(`${this.autoLineupUrl}?formation=${this.selectedFormation}`);
                const data = await response.json();
                this.autoLineup = data.autoLineup;
                this.selectedPlayers = [...this.autoLineup];
                this.manualAssignments = {};
                this.pitchPositions = {};
                this.selectedSlot = null;
                this.selectedSlotPlayer = null;
                this.positioningSlotId = null;
            } catch (e) {
                console.error('Failed to fetch auto lineup', e);
            }
        },

        removeFromSlot(playerId) {
            this.selectedPlayers = this.selectedPlayers.filter(p => p !== playerId);
            this._removePlayerFromManualAssignments(playerId);
        },

        // Find which slot a player is assigned to (internal code for compatibility lookup)
        getPlayerSlot(playerId) {
            const assignment = this.slotAssignments.find(s => s.player?.id === playerId);
            return assignment?.label || null;
        },

        // Find display label (Spanish abbreviation) for a player's assigned slot
        getPlayerSlotDisplay(playerId) {
            const assignment = this.slotAssignments.find(s => s.player?.id === playerId);
            return assignment?.displayLabel || null;
        },

        getInitials(name) {
            if (!name) return '??';
            const parts = name.trim().split(/\s+/);
            if (parts.length === 1) {
                return parts[0].substring(0, 2).toUpperCase();
            }
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        },

        // Generate inline CSS for the player badge background based on team shirt
        getShirtStyle(role) {
            // Goalkeeper always gets a distinct amber kit
            if (role === 'Goalkeeper') {
                return 'background: linear-gradient(to bottom right, #FBBF24, #D97706)';
            }

            const tc = this.teamColors;
            if (!tc) return 'background: linear-gradient(to bottom right, #3B82F6, #1D4ED8)';

            const p = tc.primary;
            const s = tc.secondary;

            switch (tc.pattern) {
                case 'stripes':
                    return `background: repeating-linear-gradient(90deg, ${p} 0px, ${p} 6px, ${s} 6px, ${s} 12px)`;
                case 'hoops':
                    return `background: repeating-linear-gradient(0deg, ${p} 0px, ${p} 6px, ${s} 6px, ${s} 12px)`;
                case 'sash':
                    return `background: linear-gradient(135deg, ${p} 0%, ${p} 35%, ${s} 35%, ${s} 65%, ${p} 65%, ${p} 100%)`;
                case 'bar':
                    return `background: linear-gradient(90deg, ${p} 0%, ${p} 35%, ${s} 35%, ${s} 65%, ${p} 65%, ${p} 100%)`;
                case 'halves':
                    return `background: linear-gradient(90deg, ${p} 50%, ${s} 50%)`;
                default:
                    return `background: ${p}`;
            }
        },

        // Get the complete inline style for the player number including backdrop for patterned shirts
        getNumberStyle(role) {
            if (role === 'Goalkeeper') {
                return 'color: #FFFFFF; text-shadow: 0 1px 2px rgba(0,0,0,0.5)';
            }
            const tc = this.teamColors;
            if (!tc) return 'color: #FFFFFF; text-shadow: 0 1px 2px rgba(0,0,0,0.5)';

            const color = tc.number || '#FFFFFF';

            if (tc.pattern !== 'solid') {
                // For patterned shirts, add a semi-transparent circular backdrop
                const backdrop = this._getBackdropColor(tc);
                return `color: ${color}; background: ${backdrop}CC; text-shadow: 0 1px 2px rgba(0,0,0,0.15)`;
            }

            return `color: ${color}; text-shadow: 0 1px 2px rgba(0,0,0,0.2)`;
        },

        // Pick the team color (primary or secondary) that best contrasts with the number color
        _getBackdropColor(tc) {
            const numLum = this._hexLuminance(tc.number);
            const priLum = this._hexLuminance(tc.primary);
            const secLum = this._hexLuminance(tc.secondary);
            return Math.abs(numLum - priLum) >= Math.abs(numLum - secLum) ? tc.primary : tc.secondary;
        },

        _hexLuminance(hex) {
            if (!hex || hex.length < 7) return 0.5;
            const r = parseInt(hex.slice(1, 3), 16) / 255;
            const g = parseInt(hex.slice(3, 5), 16) / 255;
            const b = parseInt(hex.slice(5, 7), 16) / 255;
            return 0.299 * r + 0.587 * g + 0.114 * b;
        },

        // Get the compatibility display for a player in the currently selected slot
        getSelectedSlotCompatibility(position) {
            if (this.selectedSlot === null) return null;
            const slot = this.currentSlots.find(s => s.id === this.selectedSlot);
            if (!slot) return null;
            return this.getCompatibilityDisplay(position, slot.label);
        },

        // Remove a player from all manual assignments
        _removePlayerFromManualAssignments(playerId) {
            const newAssignments = {};
            for (const [slotId, pid] of Object.entries(this.manualAssignments)) {
                if (pid !== playerId) {
                    newAssignments[slotId] = pid;
                }
            }
            this.manualAssignments = newAssignments;
        },

        // =====================================================================
        // Grid Positioning System
        // =====================================================================

        /**
         * Convert a grid cell to pitch coordinates (0-100%).
         */
        cellToCoords(col, row) {
            const gc = this.gridConfig;
            if (!gc) return null;
            return {
                x: col * (100 / gc.cols) + (100 / (gc.cols * 2)),
                y: row * (100 / gc.rows) + (100 / (gc.rows * 2)),
            };
        },

        /**
         * Get effective (x, y) position for a slot, using custom grid position if set.
         * Falls back to the formation's default coordinate.
         */
        getEffectivePosition(slotId) {
            const customPos = this.pitchPositions[String(slotId)];
            if (customPos) {
                return this.cellToCoords(customPos[0], customPos[1]);
            }
            // Fall back to formation default
            const slot = this.currentSlots.find(s => s.id === slotId);
            return slot ? { x: slot.x, y: slot.y } : null;
        },

        /**
         * Get the grid cell for a slot (custom or default).
         */
        getSlotCell(slotId) {
            const customPos = this.pitchPositions[String(slotId)];
            if (customPos) return { col: customPos[0], row: customPos[1] };

            // Use formation default cell
            const gc = this.gridConfig;
            if (!gc) return null;
            const defaultCells = gc.defaultCells[this.selectedFormation];
            return defaultCells ? defaultCells[slotId] : null;
        },

        /**
         * Check if a grid cell is within a slot's valid zone.
         */
        isValidGridCell(slotLabel, col, row) {
            const gc = this.gridConfig;
            if (!gc) return false;
            const zone = gc.zones[slotLabel];
            if (!zone) return false;
            return col >= zone[0] && col <= zone[1] && row >= zone[2] && row <= zone[3];
        },

        /**
         * Check if a cell is occupied by another slot.
         */
        isCellOccupied(col, row, excludeSlotId) {
            const assignments = this.slotAssignments;
            for (const slot of assignments) {
                if (slot.id === excludeSlotId || !slot.player) continue;
                const cell = this.getSlotCell(slot.id);
                if (cell && cell.col === col && cell.row === row) return true;
            }
            return false;
        },

        /**
         * Toggle grid positioning mode.
         */
        toggleGridMode() {
            this.gridMode = !this.gridMode;
            this.positioningSlotId = null;
            this.draggingSlotId = null;
            this.dragPosition = null;
            if (this.gridMode) {
                // Deselect any slot assignment mode
                this.selectedSlot = null;
                this.selectedSlotPlayer = null;
            }
        },

        /**
         * Select a slot for grid repositioning (click-to-place mode).
         */
        selectForRepositioning(slotId) {
            if (!this.gridMode) return;
            if (this.positioningSlotId === slotId) {
                this.positioningSlotId = null;
            } else {
                this.positioningSlotId = slotId;
            }
        },

        /**
         * Place a slot at a specific grid cell.
         */
        setSlotGridPosition(slotId, col, row) {
            const slot = this.currentSlots.find(s => s.id === slotId);
            if (!slot) return;

            if (!this.isValidGridCell(slot.label, col, row)) return;
            if (this.isCellOccupied(col, row, slotId)) return;

            this.pitchPositions = { ...this.pitchPositions, [String(slotId)]: [col, row] };
            this.positioningSlotId = null;
        },

        /**
         * Reset a single slot's position to formation default.
         */
        resetSlotPosition(slotId) {
            const newPositions = { ...this.pitchPositions };
            delete newPositions[String(slotId)];
            this.pitchPositions = newPositions;
        },

        /**
         * Reset all custom positions.
         */
        resetAllPositions() {
            this.pitchPositions = {};
            this.positioningSlotId = null;
        },

        /**
         * Handle clicking a grid cell (in grid mode, when a slot is selected for repositioning).
         */
        handleGridCellClick(col, row) {
            if (!this.gridMode || this.positioningSlotId === null) return;
            this.setSlotGridPosition(this.positioningSlotId, col, row);
        },

        /**
         * Get the zone highlight color class for a position group.
         */
        getZoneColorClass(role) {
            switch (role) {
                case 'Goalkeeper': return 'bg-amber-500/30 border-amber-400';
                case 'Defender': return 'bg-blue-500/30 border-blue-400';
                case 'Midfielder': return 'bg-emerald-500/30 border-emerald-400';
                case 'Forward': return 'bg-red-500/30 border-red-400';
                default: return 'bg-white/20 border-white/40';
            }
        },

        /**
         * Get the cell state for a grid cell relative to the currently positioning slot.
         * Returns: 'valid', 'occupied', 'invalid', or 'neutral'
         */
        getGridCellState(col, row) {
            if (this.positioningSlotId === null && this.draggingSlotId === null) return 'neutral';

            const activeSlotId = this.positioningSlotId ?? this.draggingSlotId;
            const slot = this.currentSlots.find(s => s.id === activeSlotId);
            if (!slot) return 'neutral';

            if (!this.isValidGridCell(slot.label, col, row)) return 'invalid';
            if (this.isCellOccupied(col, row, activeSlotId)) return 'occupied';
            return 'valid';
        },

        // ----- Drag-and-drop -----

        /**
         * Begin dragging a player badge in grid mode.
         */
        startDrag(slotId, event) {
            if (!this.gridMode) return;

            // Prevent default to avoid text selection and scroll on touch
            event.preventDefault();

            this.draggingSlotId = slotId;
            this.positioningSlotId = null;

            const coords = this._getEventCoords(event);
            this._updateDragPosition(coords.clientX, coords.clientY);
        },

        _onDragMove(event) {
            if (this.draggingSlotId === null) return;
            event.preventDefault();

            const coords = this._getEventCoords(event);
            this._updateDragPosition(coords.clientX, coords.clientY);
        },

        _onDragEnd(event) {
            if (this.draggingSlotId === null) return;

            const coords = this._getEventCoords(event);
            const cell = this._getCellFromClientCoords(coords.clientX, coords.clientY);

            if (cell) {
                const slot = this.currentSlots.find(s => s.id === this.draggingSlotId);
                if (slot && this.isValidGridCell(slot.label, cell.col, cell.row) && !this.isCellOccupied(cell.col, cell.row, this.draggingSlotId)) {
                    this.pitchPositions = { ...this.pitchPositions, [String(this.draggingSlotId)]: [cell.col, cell.row] };
                }
            }

            this.draggingSlotId = null;
            this.dragPosition = null;
        },

        _updateDragPosition(clientX, clientY) {
            const pitchEl = this._getPitchElement();
            if (!pitchEl) return;

            const rect = pitchEl.getBoundingClientRect();
            const x = ((clientX - rect.left) / rect.width) * 100;
            const y = ((clientY - rect.top) / rect.height) * 100;

            this.dragPosition = {
                x: Math.max(0, Math.min(100, x)),
                y: Math.max(0, Math.min(100, y)),
            };
        },

        _getCellFromClientCoords(clientX, clientY) {
            const pitchEl = this._getPitchElement();
            if (!pitchEl) return null;

            const rect = pitchEl.getBoundingClientRect();
            const xPct = ((clientX - rect.left) / rect.width) * 100;
            const yPct = ((clientY - rect.top) / rect.height) * 100;

            // Convert to grid coordinates (y is inverted: top of element = high row)
            // The pitch renders y=0 at bottom, so top: 100-y%
            // Screen top = y=100, screen bottom = y=0
            const pitchY = 100 - yPct;

            const gc = this.gridConfig;
            if (!gc) return null;

            const col = Math.round((xPct - 100 / (gc.cols * 2)) / (100 / gc.cols));
            const row = Math.round((pitchY - 100 / (gc.rows * 2)) / (100 / gc.rows));

            return {
                col: Math.max(0, Math.min(gc.cols - 1, col)),
                row: Math.max(0, Math.min(gc.rows - 1, row)),
            };
        },

        _getEventCoords(event) {
            if (event.touches && event.touches.length > 0) {
                return { clientX: event.touches[0].clientX, clientY: event.touches[0].clientY };
            }
            if (event.changedTouches && event.changedTouches.length > 0) {
                return { clientX: event.changedTouches[0].clientX, clientY: event.changedTouches[0].clientY };
            }
            return { clientX: event.clientX, clientY: event.clientY };
        },

        _getPitchElement() {
            if (!this._pitchEl) {
                this._pitchEl = document.getElementById('pitch-container');
            }
            return this._pitchEl;
        },

        get hasCustomPositions() {
            return Object.keys(this.pitchPositions).length > 0;
        },
    };
}
