import {
    cellToCoords as _cellToCoords,
    getEffectivePosition as _getEffectivePosition,
    getInitials as _getInitials,
    getShirtStyle as _getShirtStyle,
    getNumberStyle as _getNumberStyle,
    getEventCoords as _getEventCoords,
    getDragPosition,
    getCellFromClientCoords,
    isValidGridCell as _isValidGridCell,
    getZoneColorClass as _getZoneColorClass,
} from './modules/pitch-renderer.js';

export default function lineupManager(config) {
    return {
        // State
        activeLineupTab: 'squad',
        presets: config.presets || [],
        selectedPlayers: config.currentLineup || [],
        selectedFormation: config.currentFormation,
        selectedMentality: config.currentMentality,
        selectedPlayingStyle: config.currentPlayingStyle || 'balanced',
        selectedPressing: config.currentPressing || 'standard',
        selectedDefLine: config.currentDefLine || 'normal',
        formationOptions: config.formationOptions || [],
        mentalityOptions: config.mentalityOptions || [],
        playingStyles: config.playingStyles || [],
        pressingOptions: config.pressingOptions || [],
        defensiveLineOptions: config.defensiveLineOptions || [],
        autoLineup: config.autoLineup || [],

        // Manual slot assignments: { slotId: playerId }
        // When a user explicitly assigns a player to a slot, it's tracked here.
        manualAssignments: config.currentSlotAssignments || {},

        // Player being hovered in the list (for pitch highlight)
        hoveredPlayerId: null,

        // Grid positioning state
        gridConfig: config.gridConfig || null,
        pitchPositions: config.currentPitchPositions || {},
        positioningSlotId: null,
        assigningSlotId: null, // empty slot waiting for a player to be picked from the list
        draggingSlotId: null,
        dragPosition: null, // { x, y } in percentage coordinates during drag
        _pitchEl: null, // reference to pitch container DOM element

        // List-to-pitch drag state
        listDragPlayerId: null,       // player being dragged from list
        listDragGhostPos: null,       // {x, y} viewport pixels for fixed ghost
        listDragOverPitch: false,     // cursor is over pitch element
        listDragNearestSlotId: null,  // nearest compatible empty slot
        _listDragMoved: false,        // distance threshold reached (5px)
        _listDragStartCoords: null,   // {x, y} for distance threshold
        _listDragPendingId: null,     // player ID pending drag activation

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
            // Filter out players no longer in the squad (e.g. sold after lineup was saved)
            this.selectedPlayers = this.selectedPlayers.filter(id => this.playersData[id]);
            if (Object.keys(this.manualAssignments).length > 0) {
                const clean = {};
                for (const [slotId, playerId] of Object.entries(this.manualAssignments)) {
                    if (this.playersData[playerId]) {
                        clean[slotId] = playerId;
                    }
                }
                this.manualAssignments = clean;
            }

            // Snapshot the initial state for dirty detection (after filtering)
            this._initialPlayers = [...this.selectedPlayers].sort();
            this._initialFormation = config.currentFormation;
            this._initialMentality = config.currentMentality;
            this._initialPlayingStyle = config.currentPlayingStyle || 'balanced';
            this._initialPressing = config.currentPressing || 'standard';
            this._initialDefLine = config.currentDefLine || 'normal';
            this._initialAssignments = JSON.stringify(this.manualAssignments);
            this._initialPitchPositions = JSON.stringify(config.currentPitchPositions || {});

            // Warn on navigation away with unsaved changes
            window.addEventListener('beforeunload', (e) => {
                if (this._isSaving) return;
                if (this.isDirty) {
                    e.preventDefault();
                }
            });

            // Bound drag handlers (created once, registered lazily during drag)
            this._boundDragMove = (e) => this._onDragMove(e);
            this._boundDragEnd = (e) => this._onDragEnd(e);

            // Bound list-to-pitch drag handlers
            this._boundListDragMove = (e) => this._onListDragMove(e);
            this._boundListDragEnd = (e) => this._onListDragEnd(e);
        },

        get isDirty() {
            // Cheap comparisons first, expensive serialization last
            if (this.selectedFormation !== this._initialFormation) return true;
            if (this.selectedMentality !== this._initialMentality) return true;
            if (this.selectedPlayingStyle !== this._initialPlayingStyle) return true;
            if (this.selectedPressing !== this._initialPressing) return true;
            if (this.selectedDefLine !== this._initialDefLine) return true;
            if (JSON.stringify(this.manualAssignments) !== this._initialAssignments) return true;
            if (JSON.stringify(this.pitchPositions) !== this._initialPitchPositions) return true;
            if (JSON.stringify([...this.selectedPlayers].sort()) !== JSON.stringify(this._initialPlayers)) return true;
            return false;
        },

        // Computed
        get selectedCount() { return this.selectedPlayers.length },
        get currentSlots() { return this.formationSlots[this.selectedFormation] || [] },
        get hasManualAssignments() { return Object.keys(this.manualAssignments).length > 0 },

        get activePresetId() {
            const sorted = [...this.selectedPlayers].sort();
            return this.presets.find(p =>
                p.formation === this.selectedFormation &&
                p.mentality === this.selectedMentality &&
                p.playing_style === this.selectedPlayingStyle &&
                p.pressing === this.selectedPressing &&
                p.defensive_line === this.selectedDefLine &&
                JSON.stringify(p.lineup) === JSON.stringify(sorted)
            )?.id ?? null;
        },

        loadPreset(preset) {
            this.selectedFormation = preset.formation;
            this.selectedMentality = preset.mentality;
            this.selectedPlayingStyle = preset.playing_style;
            this.selectedPressing = preset.pressing;
            this.selectedDefLine = preset.defensive_line;
            this.selectedPlayers = [...preset.lineup];
            this.manualAssignments = preset.slot_assignments ? { ...preset.slot_assignments } : {};
            this.pitchPositions = preset.pitch_positions ? { ...preset.pitch_positions } : {};
        },

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

        get averageFitness() {
            if (this.selectedPlayers.length === 0) return 0;
            let total = 0;
            this.selectedPlayers.forEach(id => {
                if (this.playersData[id]) {
                    total += this.playersData[id].fitness;
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

            // Suppress click after a list-to-pitch drag gesture
            if (this._listDragMoved) {
                this._listDragMoved = false;
                return;
            }

            // If an empty slot is waiting for assignment, assign this player to it
            if (this.assigningSlotId !== null && !this.isSelected(id)) {
                if (this.selectedCount < 11) {
                    this.selectedPlayers.push(id);
                    this.manualAssignments = { ...this.manualAssignments, [this.assigningSlotId]: id };
                }
                this.assigningSlotId = null;
                return;
            }

            if (this.isSelected(id)) {
                // Preserve current slot assignments so remaining players don't get reshuffled
                this._preserveCurrentAssignments(id);
                this.selectedPlayers = this.selectedPlayers.filter(p => p !== id);
            } else if (this.selectedCount < 11) {
                this.selectedPlayers.push(id);
            }
            this.assigningSlotId = null;
        },

        quickSelect() {
            this.selectedPlayers = [...this.autoLineup];
            this.manualAssignments = {};
            this.pitchPositions = {};
            this.positioningSlotId = null;
            this.assigningSlotId = null;
        },

        clearSelection() {
            this.selectedPlayers = [];
            this.manualAssignments = {};
            this.pitchPositions = {};
            this.positioningSlotId = null;
            this.assigningSlotId = null;
        },

        async updateAutoLineup() {
            try {
                const response = await fetch(`${this.autoLineupUrl}?formation=${this.selectedFormation}`);
                const data = await response.json();
                this.autoLineup = data.autoLineup;

                // Clear slot-specific state (slot IDs change per formation)
                this.manualAssignments = {};
                this.pitchPositions = {};
                this.positioningSlotId = null;
                this.assigningSlotId = null;

                // Keep currently selected players — _autoAssignToSlots will
                // re-slot them into the new formation's positions automatically.
            } catch (e) {
                console.error('Failed to fetch auto lineup', e);
            }
        },

        removeFromSlot(playerId) {
            // Preserve current slot assignments so remaining players don't get reshuffled
            this._preserveCurrentAssignments(playerId);
            this.selectedPlayers = this.selectedPlayers.filter(p => p !== playerId);
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
            return _getInitials(name);
        },

        getAvatarDisplay(player) {
            return player?.number || this.getInitials(player?.name);
        },

        getAvatarCircleClasses(positionGroup) {
            const map = {
                'Goalkeeper': 'bg-linear-to-br from-amber-500/20 to-amber-600/10 border-amber-500/20',
                'Defender':   'bg-linear-to-br from-blue-500/20 to-blue-600/10 border-blue-500/20',
                'Midfielder': 'bg-linear-to-br from-green-500/20 to-green-600/10 border-green-500/20',
                'Forward':    'bg-linear-to-br from-rose-500/20 to-rose-600/10 border-rose-500/20',
            };
            return map[positionGroup] || map['Midfielder'];
        },

        getAvatarTextClasses(positionGroup) {
            const map = {
                'Goalkeeper': 'text-amber-400',
                'Defender':   'text-blue-400',
                'Midfielder': 'text-green-400',
                'Forward':    'text-rose-400',
            };
            return map[positionGroup] || map['Midfielder'];
        },

        // Delegate to shared pitch-renderer module
        getShirtStyle(role) {
            return _getShirtStyle(role, this.teamColors);
        },

        getNumberStyle(role) {
            return _getNumberStyle(role, this.teamColors);
        },

        // Snapshot all current slot assignments (manual + auto) as manual,
        // excluding a specific player. This prevents remaining players from
        // being reshuffled when someone is deselected.
        _preserveCurrentAssignments(excludePlayerId) {
            const current = this.slotAssignments;
            const newManual = {};
            for (const slot of current) {
                if (slot.player && slot.player.id !== excludePlayerId) {
                    newManual[slot.id] = slot.player.id;
                }
            }
            this.manualAssignments = newManual;
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
            return _cellToCoords(col, row, gc.cols, gc.rows);
        },

        /**
         * Get effective (x, y) position for a slot, using custom grid position if set.
         * Falls back to the formation's default coordinate.
         */
        getEffectivePosition(slotId) {
            const gc = this.gridConfig;
            if (!gc) return null;
            return _getEffectivePosition(slotId, this.pitchPositions, this.currentSlots, gc.cols, gc.rows);
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
         * Check if a grid cell is valid for a slot.
         * GK stays locked to its zone; outfield players can go anywhere on the outfield (rows 1-13).
         */
        isValidGridCell(slotLabel, col, row) {
            return _isValidGridCell(slotLabel, col, row, this.gridConfig);
        },

        /**
         * Check if a cell is occupied by another slot.
         */
        isCellOccupied(col, row, excludeSlotId) {
            return this._findSlotAtCell(col, row, excludeSlotId) !== null;
        },

        /**
         * Find the slot occupying a cell (excluding a given slot).
         * Returns the slot object or null.
         */
        _findSlotAtCell(col, row, excludeSlotId) {
            const assignments = this.slotAssignments;
            for (const slot of assignments) {
                if (slot.id === excludeSlotId || !slot.player) continue;
                const cell = this.getSlotCell(slot.id);
                if (cell && cell.col === col && cell.row === row) return slot;
            }
            return null;
        },

        /**
         * Select a slot for grid repositioning (click-to-place mode).
         */
        selectForRepositioning(slotId) {
            const slot = this.currentSlots.find(s => s.id === slotId);
            this.assigningSlotId = null;
            if (this.positioningSlotId === slotId) {
                this.positioningSlotId = null;
            } else {
                this.positioningSlotId = slotId;
            }
        },

        /**
         * Place a slot at a specific grid cell. If occupied, swap positions.
         */
        setSlotGridPosition(slotId, col, row) {
            const slot = this.currentSlots.find(s => s.id === slotId);
            if (!slot) return;

            if (!this.isValidGridCell(slot.label, col, row)) return;

            const occupying = this._findSlotAtCell(col, row, slotId);
            const newPositions = { ...this.pitchPositions };

            if (occupying) {
                // Move occupying slot to dragged slot's old cell (validate reverse direction)
                const draggedCell = this.getSlotCell(slotId);
                if (draggedCell && !this.isValidGridCell(occupying.label, draggedCell.col, draggedCell.row)) return;
                if (draggedCell) {
                    newPositions[String(occupying.id)] = [draggedCell.col, draggedCell.row];
                }
            }

            newPositions[String(slotId)] = [col, row];
            this.pitchPositions = newPositions;
            this.positioningSlotId = null;
        },

        /**
         * Handle clicking a grid cell (in grid mode, when a slot is selected for repositioning).
         */
        handleGridCellClick(col, row) {
            if (this.positioningSlotId === null) return;
            this.setSlotGridPosition(this.positioningSlotId, col, row);
        },

        /**
         * Get the zone highlight color class for a position group.
         */
        getZoneColorClass(role) {
            return _getZoneColorClass(role);
        },

        /**
         * Get the cell state for a grid cell relative to the currently positioning slot.
         * Returns: 'valid-def', 'valid-mid', 'valid-fwd', 'valid' (GK), 'occupied', 'invalid', or 'neutral'
         */
        getGridCellState(col, row) {
            if (this.positioningSlotId === null && this.draggingSlotId === null) return 'neutral';

            const activeSlotId = this.positioningSlotId ?? this.draggingSlotId;
            const slot = this.currentSlots.find(s => s.id === activeSlotId);
            if (!slot) return 'neutral';

            if (!this.isValidGridCell(slot.label, col, row)) return 'invalid';

            // Occupied cells are swappable (treat as valid with appropriate color)
            const occupying = this._findSlotAtCell(col, row, activeSlotId);
            if (occupying && occupying.role === 'Goalkeeper') return 'valid-gk';

            // GK repositioning: show zone-colored hints for outfield cells
            if (slot.role === 'Goalkeeper') {
                if (row === 0) return 'valid';
                if (row <= 4) return 'valid-def';
                if (row <= 9) return 'valid-mid';
                return 'valid-fwd';
            }

            // Color outfield cells by zone for visual guidance
            if (row <= 4) return 'valid-def';
            if (row <= 9) return 'valid-mid';
            return 'valid-fwd';
        },

        // ----- Drag-and-drop -----

        /**
         * Begin dragging a player badge in grid mode.
         */
        startDrag(slotId, event) {
            const slot = this.currentSlots.find(s => s.id === slotId);

            // Prevent default to avoid text selection and scroll on touch
            event.preventDefault();

            this.draggingSlotId = slotId;
            this.positioningSlotId = null;

            // Register drag listeners lazily to avoid permanent non-passive touchmove
            document.addEventListener('mousemove', this._boundDragMove);
            document.addEventListener('mouseup', this._boundDragEnd);
            document.addEventListener('touchmove', this._boundDragMove, { passive: false });
            document.addEventListener('touchend', this._boundDragEnd);

            const coords = _getEventCoords(event);
            this.dragPosition = getDragPosition(coords.clientX, coords.clientY, this._getPitchElement());
        },

        _onDragMove(event) {
            if (this.draggingSlotId === null) return;
            event.preventDefault();

            const coords = _getEventCoords(event);
            this.dragPosition = getDragPosition(coords.clientX, coords.clientY, this._getPitchElement());
        },

        _onDragEnd(event) {
            if (this.draggingSlotId === null) return;

            const coords = _getEventCoords(event);
            const cell = getCellFromClientCoords(coords.clientX, coords.clientY, this._getPitchElement(), this.gridConfig);

            if (cell) {
                this.setSlotGridPosition(this.draggingSlotId, cell.col, cell.row);
            }

            this.draggingSlotId = null;
            this.dragPosition = null;

            // Clean up drag listeners
            document.removeEventListener('mousemove', this._boundDragMove);
            document.removeEventListener('mouseup', this._boundDragEnd);
            document.removeEventListener('touchmove', this._boundDragMove);
            document.removeEventListener('touchend', this._boundDragEnd);
        },

        _getPitchElement() {
            if (!this._pitchEl) {
                this._pitchEl = document.getElementById('pitch-field');
            }
            return this._pitchEl;
        },

        // =====================================================================
        // List-to-Pitch Drag (drag from player sidebar to pitch)
        // =====================================================================

        /**
         * Get the role string for a player (for getShirtStyle).
         */
        getPlayerRole(playerId) {
            const p = this.playersData[playerId];
            return p?.positionGroup || 'Midfielder';
        },

        /**
         * Start a list-to-pitch drag from a player row.
         * Uses a 5px threshold before activating to disambiguate from clicks.
         */
        startListDrag(playerId, event) {
            // Desktop only (lg breakpoint = 1024px)
            if (window.innerWidth < 1024) return;
            // Guards: already selected, squad full, unavailable
            if (this.isSelected(playerId)) return;
            if (this.selectedCount >= 11) return;
            const player = this.playersData[playerId];
            if (!player || !player.isAvailable) return;

            event.preventDefault();

            // Enter pending state — not yet dragging until 5px threshold
            const coords = _getEventCoords(event);
            this._listDragPendingId = playerId;
            this._listDragStartCoords = { x: coords.clientX, y: coords.clientY };
            this._listDragMoved = false;

            // Clear other interaction modes
            this.assigningSlotId = null;
            this.positioningSlotId = null;

            document.addEventListener('mousemove', this._boundListDragMove);
            document.addEventListener('mouseup', this._boundListDragEnd);
            document.addEventListener('touchmove', this._boundListDragMove, { passive: false });
            document.addEventListener('touchend', this._boundListDragEnd);
        },

        _onListDragMove(event) {
            if (!this._listDragPendingId && !this.listDragPlayerId) return;
            event.preventDefault();

            const coords = _getEventCoords(event);

            // Check 5px threshold before activating drag
            if (this._listDragPendingId && !this.listDragPlayerId) {
                const dx = coords.clientX - this._listDragStartCoords.x;
                const dy = coords.clientY - this._listDragStartCoords.y;
                if (Math.sqrt(dx * dx + dy * dy) < 5) return;

                // Activate drag
                this.listDragPlayerId = this._listDragPendingId;
                this._listDragPendingId = null;
                this._listDragMoved = true;
            }

            // Update ghost position (viewport pixels for fixed positioning)
            this.listDragGhostPos = { x: coords.clientX, y: coords.clientY };

            // Hit-test against pitch element
            const pitchEl = this._getPitchElement();
            if (!pitchEl) return;

            const rect = pitchEl.getBoundingClientRect();
            const overPitch = coords.clientX >= rect.left && coords.clientX <= rect.right
                           && coords.clientY >= rect.top && coords.clientY <= rect.bottom;
            this.listDragOverPitch = overPitch;

            // Find nearest compatible empty slot
            if (overPitch) {
                this.listDragNearestSlotId = this._findNearestEmptySlot(coords.clientX, coords.clientY);
            } else {
                this.listDragNearestSlotId = null;
            }
        },

        _onListDragEnd(event) {
            // Clean up listeners
            document.removeEventListener('mousemove', this._boundListDragMove);
            document.removeEventListener('mouseup', this._boundListDragEnd);
            document.removeEventListener('touchmove', this._boundListDragMove);
            document.removeEventListener('touchend', this._boundListDragEnd);

            // If threshold was never reached, let click fire normally
            if (!this.listDragPlayerId) {
                this._listDragPendingId = null;
                this._listDragStartCoords = null;
                return;
            }

            const playerId = this.listDragPlayerId;

            if (this.listDragOverPitch) {
                // Select the player
                this.selectedPlayers.push(playerId);

                if (this.listDragNearestSlotId !== null) {
                    // Assign to the nearest compatible empty slot
                    this.manualAssignments = { ...this.manualAssignments, [this.listDragNearestSlotId]: playerId };
                }
                // If no nearest slot, auto-assign handles it via slotAssignments computed
            }
            // If not over pitch, cancel (don't select)

            // Reset all list-drag state
            this.listDragPlayerId = null;
            this.listDragGhostPos = null;
            this.listDragOverPitch = false;
            this.listDragNearestSlotId = null;
            this._listDragPendingId = null;
            this._listDragStartCoords = null;
        },

        /**
         * Find the nearest empty, compatible slot to the cursor position.
         * Returns slot ID or null.
         */
        _findNearestEmptySlot(clientX, clientY) {
            const pitchEl = this._getPitchElement();
            if (!pitchEl) return null;

            const rect = pitchEl.getBoundingClientRect();
            const cursorXPct = ((clientX - rect.left) / rect.width) * 100;
            const cursorYPct = ((clientY - rect.top) / rect.height) * 100;

            const player = this.playersData[this.listDragPlayerId];
            if (!player) return null;

            let bestSlotId = null;
            let bestDist = Infinity;

            for (const slot of this.slotAssignments) {
                if (slot.player) continue; // skip filled slots

                const compatibility = this.getSlotCompatibility(player.position, slot.label);
                if (compatibility < 20) continue; // skip incompatible slots

                const pos = this.getEffectivePosition(slot.id);
                if (!pos) continue;

                // pos.x/pos.y are pitch percentages; screen renders y inverted (top: 100-pos.y%)
                const slotScreenXPct = pos.x;
                const slotScreenYPct = 100 - pos.y;

                const dx = cursorXPct - slotScreenXPct;
                const dy = cursorYPct - slotScreenYPct;
                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < bestDist) {
                    bestDist = dist;
                    bestSlotId = slot.id;
                }
            }

            return bestSlotId;
        },

    };
}
