import {
    cellToCoords as _cellToCoords,
    getEffectivePosition as _getEffectivePosition,
    getInitials as _getInitials,
    getShirtStyle as _getShirtStyle,
    getNumberStyle as _getNumberStyle,
    getEventCoords as _getEventCoords,
    getZoneColorClass as _getZoneColorClass,
    isValidGridCell as _isValidGridCell,
} from './modules/pitch-renderer.js';
import { createPitchGrid } from './modules/pitch-grid.js';
import { calculateXgPreview } from './modules/xg-calculator.js';
import { generateCoachTips } from './modules/coach-tips.js';
import {
    swapSlots,
    placeInFirstMatchingSlot,
    removePlayer,
    buildSlotView,
    getPlayerCompatibility,
    normalizeSlotMap,
} from './modules/slot-map.js';

/**
 * Copy all own properties from source to target. Regular properties are
 * assigned normally (compatible with Alpine's reactive proxy), while
 * getter/setter descriptors are defined via Object.defineProperty so
 * they remain live computed properties instead of being evaluated once.
 */
function mixinModule(target, source) {
    for (const key of Object.keys(source)) {
        const desc = Object.getOwnPropertyDescriptor(source, key);
        if (desc.get || desc.set) {
            Object.defineProperty(target, key, desc);
        } else {
            target[key] = desc.value;
        }
    }
}

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

        // The authoritative slot map: { slotId: playerId }. This is reactive
        // state, not a computed value — drag-drop, click-to-assign, add, and
        // remove all mutate it directly. Formation change and the Auto
        // button POST to the backend and replace the whole map with the
        // response. The algorithm lives on the server, not here.
        slotMap: config.currentSlotMap || {},

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
        _initialSlotMap: null,
        _initialPitchPositions: null,
        _isSaving: false,
        _isFetchingSlots: false,

        // Server data
        playersData: config.playersData,
        formationSlots: config.formationSlots,
        slotCompatibility: config.slotCompatibility,
        autoLineupUrl: config.autoLineupUrl,
        computeSlotsUrl: config.computeSlotsUrl,
        teamColors: config.teamColors,
        translations: config.translations,
        formationModifiers: config.formationModifiers || {},
        opponentAverage: config.opponentAverage || 0,
        opponentFormation: config.opponentFormation || null,
        opponentMentality: config.opponentMentality || null,
        opponentPlayingStyle: config.opponentPlayingStyle || 'balanced',
        opponentPressing: config.opponentPressing || 'standard',
        opponentDefLine: config.opponentDefLine || 'normal',
        xgConfig: config.xgConfig || null,
        userTeamAverage: config.userTeamAverage || 0,
        isHome: config.isHome || false,

        init() {
            // If we just saved/overwrote a preset, load it so it appears active
            // in the UI. Run before dirty snapshotting so it counts as the
            // initial state and doesn't trigger the unsaved-changes warning.
            if (config.activePresetIdOnLoad) {
                const saved = this.presets.find(p => p.id === config.activePresetIdOnLoad);
                if (saved) {
                    this.loadPreset(saved);
                }
            }

            // Filter out players no longer in the squad (e.g. sold after lineup was saved)
            this.selectedPlayers = this.selectedPlayers.filter(id => this.playersData[id]);
            if (Object.keys(this.slotMap).length > 0) {
                const clean = {};
                for (const [slotId, playerId] of Object.entries(this.slotMap)) {
                    if (this.playersData[playerId] && this.selectedPlayers.includes(playerId)) {
                        clean[slotId] = playerId;
                    }
                }
                this.slotMap = clean;
            }
            // Defense-in-depth: force any selected player missing from the
            // slot map onto the pitch so we can never start with a ghost.
            this.slotMap = normalizeSlotMap(
                this.slotMap,
                this.selectedPlayers,
                this.currentSlots,
                this.playersData,
            );

            // Snapshot the initial state for dirty detection (after filtering)
            this._initialPlayers = [...this.selectedPlayers].sort();
            this._initialFormation = config.currentFormation;
            this._initialMentality = config.currentMentality;
            this._initialPlayingStyle = config.currentPlayingStyle || 'balanced';
            this._initialPressing = config.currentPressing || 'standard';
            this._initialDefLine = config.currentDefLine || 'normal';
            this._initialSlotMap = JSON.stringify(this.slotMap);
            this._initialPitchPositions = JSON.stringify(config.currentPitchPositions || {});

            // Warn on navigation away with unsaved changes
            window.addEventListener('beforeunload', (e) => {
                if (this._isSaving) return;
                if (this.isDirty) {
                    e.preventDefault();
                }
            });

            // Integrate shared pitch grid module (positioning + drag-and-drop)
            const grid = createPitchGrid(() => this, {
                allowGkDrag: true,
                allowGkReposition: true,
                allowGkSwap: true,
                dragThreshold: 0,
                onTapFallback: null,
                onPositionChanged: null,
                onSwap: (draggedSlot, occupyingSlot) => {
                    // Drag-drop is the user's explicit intent — no algorithm,
                    // no compat scoring, no reshuffling. Just exchange two
                    // entries in the slot map. If the target is empty, the
                    // source becomes empty.
                    this.slotMap = swapSlots(this.slotMap, draggedSlot.id, occupyingSlot.id);
                },
                pitchElementId: 'pitch-field',
                getPositions: () => this.pitchPositions,
                setPositions: (p) => { this.pitchPositions = p },
                getFormationGuard: null,
            });
            mixinModule(this, grid);

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
            if (JSON.stringify(this.slotMap) !== this._initialSlotMap) return true;
            if (JSON.stringify(this.pitchPositions) !== this._initialPitchPositions) return true;
            if (JSON.stringify([...this.selectedPlayers].sort()) !== JSON.stringify(this._initialPlayers)) return true;
            return false;
        },

        // Computed
        get selectedCount() { return this.selectedPlayers.length },
        get currentSlots() { return this.formationSlots[this.selectedFormation] || [] },

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
            this.pitchPositions = preset.pitch_positions ? { ...preset.pitch_positions } : {};

            if (preset.slot_assignments && Object.keys(preset.slot_assignments).length > 0) {
                this.slotMap = normalizeSlotMap(
                    { ...preset.slot_assignments },
                    this.selectedPlayers,
                    this.currentSlots,
                    this.playersData,
                );
            } else {
                // Legacy preset without a stored slot map — ask the backend.
                // _refreshSlotMapFromServer normalizes its own response.
                this.slotMap = {};
                this._refreshSlotMapFromServer();
            }
        },

        get teamAverage() {
            if (this.selectedPlayers.length === 0) return 0;

            // Build a {playerId → slotLabel} map from the current slot assignments
            // so we can score each player against the slot they actually occupy,
            // applying the out-of-position penalty when relevant.
            const playerToSlotLabel = {};
            for (const slot of this.currentSlots) {
                const playerId = this.slotMap?.[slot.id];
                if (playerId) {
                    playerToSlotLabel[playerId] = slot.label;
                }
            }

            let total = 0;
            this.selectedPlayers.forEach(id => {
                const player = this.playersData[id];
                if (!player) return;
                const slotLabel = playerToSlotLabel[id];
                if (slotLabel) {
                    const compat = getPlayerCompatibility(player, slotLabel, this.slotCompatibility);
                    // Natural (100) and Very Good (80) play without penalty — see PositionSlotMapper::NATURAL_POSITION_THRESHOLD
                    total += compat < 80 ? player.overallScore * 0.75 : player.overallScore;
                } else {
                    total += player.overallScore;
                }
            });
            return Math.round(total / 11);
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
            return generateCoachTips({
                selectedPlayers: this.selectedPlayers,
                playersData: this.playersData,
                translations: this.translations,
                opponentAverage: this.opponentAverage,
                teamAverage: this.teamAverage,
                userTeamAverage: this.userTeamAverage,
                opponentFormation: this.opponentFormation,
                opponentMentality: this.opponentMentality,
                selectedFormation: this.selectedFormation,
                selectedMentality: this.selectedMentality,
                formationModifiers: this.formationModifiers,
                isHome: this.isHome,
            });
        },

        /**
         * Pre-match xG preview based on current lineup and tactical selections.
         * Delegates to the pure xg-calculator module.
         */
        get xgPreview() {
            if (!this.xgConfig || this.selectedPlayers.length === 0 || !this.opponentAverage) return null;
            if (!this.teamAverage) return null;

            return calculateXgPreview({
                userAvg: this.teamAverage,
                opponentAvg: this.opponentAverage,
                isHome: this.isHome,
                userFormation: this.selectedFormation,
                userMentality: this.selectedMentality,
                userStyle: this.selectedPlayingStyle,
                userPressing: this.selectedPressing,
                userDefLine: this.selectedDefLine,
                opponentFormation: this.opponentFormation,
                opponentMentality: this.opponentMentality,
                opponentStyle: this.opponentPlayingStyle,
                opponentPressing: this.opponentPressing,
                opponentDefLine: this.opponentDefLine,
                formationModifiers: this.formationModifiers,
                xgConfig: this.xgConfig,
            });
        },

        get slotAssignments() {
            // Pure projection of the authoritative slot map onto the slot list.
            // No algorithm, no reshuffling — what the map says, the pitch shows.
            return buildSlotView(this.slotMap, this.currentSlots, this.playersData, this.slotCompatibility);
        },

        // Methods

        /**
         * Get compatibility score for a player in a slot, considering secondary positions.
         * Accepts either a player ID or a position string for backwards compatibility.
         */
        getSlotCompatibility(positionOrPlayerId, slotCode) {
            const player = this.playersData[positionOrPlayerId];
            if (player) {
                return getPlayerCompatibility(player, slotCode, this.slotCompatibility);
            }
            // Fallback: treat first arg as a position string
            return this.slotCompatibility[slotCode]?.[positionOrPlayerId] ?? 0;
        },

        getCompatibilityDisplay(positionOrPlayerId, slotCode) {
            const score = this.getSlotCompatibility(positionOrPlayerId, slotCode);
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
            if (isUnavailable && !this.isSelected(id)) return;

            // Suppress click after a list-to-pitch drag gesture
            if (this._listDragMoved) {
                this._listDragMoved = false;
                return;
            }

            // If an empty slot is waiting for assignment, drop this player into it.
            if (this.assigningSlotId !== null && !this.isSelected(id)) {
                if (this.selectedCount < 11) {
                    this.slotMap = { ...this.slotMap, [this.assigningSlotId]: id };
                    this.selectedPlayers.push(id);
                }
                this.assigningSlotId = null;
                return;
            }

            if (this.isSelected(id)) {
                // Remove: clear their slot and drop them from the lineup.
                this.slotMap = removePlayer(this.slotMap, id);
                this.selectedPlayers = this.selectedPlayers.filter(p => p !== id);
            } else if (this.selectedCount < 11) {
                // Add: place in the first empty slot matching their primary,
                // falling back to any empty slot. Local only — no round trip.
                this.slotMap = placeInFirstMatchingSlot(this.slotMap, this.currentSlots, this.playersData[id]);
                this.selectedPlayers.push(id);
            }
            this.assigningSlotId = null;
        },

        async quickSelect() {
            this.selectedPlayers = [...this.autoLineup];
            this.pitchPositions = {};
            this.positioningSlotId = null;
            this.assigningSlotId = null;
            // Ask the backend for the fresh slot map — the Auto button is the
            // only client-side action that deliberately invokes the full
            // algorithm.
            await this._refreshSlotMapFromServer();
        },

        clearSelection() {
            this.selectedPlayers = [];
            this.slotMap = {};
            this.pitchPositions = {};
            this.positioningSlotId = null;
            this.assigningSlotId = null;
        },

        async updateAutoLineup() {
            // Clear slot-specific state synchronously — slot IDs carry
            // formation-specific semantics, so the old map is meaningless
            // against the new pitch shape. Doing this before the fetch
            // avoids a brief visual glitch where old slot IDs flash against
            // the wrong labels during the network round-trip.
            this.slotMap = {};
            this.pitchPositions = {};
            this.positioningSlotId = null;
            this.assigningSlotId = null;

            try {
                const response = await fetch(`${this.autoLineupUrl}?formation=${this.selectedFormation}`);
                const data = await response.json();
                this.autoLineup = data.autoLineup;
            } catch (e) {
                console.error('Failed to fetch auto lineup', e);
            }

            if (this.selectedPlayers.length > 0) {
                // Fetch a fresh slot map for the new formation + current squad.
                // _refreshSlotMapFromServer runs normalizeSlotMap on the response.
                await this._refreshSlotMapFromServer();
            }
        },

        /**
         * POST to the backend to compute the authoritative slot map for the
         * current formation + selected players, and replace local state with
         * the response. The only two paths that call this are the "Auto"
         * button and formation change — both rare, both expected to have a
         * tiny network latency.
         */
        async _refreshSlotMapFromServer() {
            if (!this.computeSlotsUrl || this.selectedPlayers.length === 0) {
                return;
            }

            this._isFetchingSlots = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const response = await fetch(this.computeSlotsUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        formation: this.selectedFormation,
                        player_ids: this.selectedPlayers,
                        manual_assignments: {},
                    }),
                });
                if (!response.ok) return;
                const data = await response.json();
                // Backend Pass 5 should already guarantee a full map, but
                // run the frontend mirror just in case the server returned
                // a short map (old data, server error, whatever).
                this.slotMap = normalizeSlotMap(
                    data.slot_assignments ?? {},
                    this.selectedPlayers,
                    this.currentSlots,
                    this.playersData,
                );
            } catch (e) {
                console.error('Failed to compute slot assignments', e);
            } finally {
                this._isFetchingSlots = false;
            }
        },

        removeFromSlot(playerId) {
            this.slotMap = removePlayer(this.slotMap, playerId);
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

        // =====================================================================
        // Grid Positioning — provided by pitch-grid module via Object.assign in init()
        // Methods: getSlotCell, isCellOccupied, selectForRepositioning,
        //          setSlotGridPosition, handleGridCellClick, getGridCellState,
        //          startDrag, _findSlotAtCell, _getPitchElement
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
         * Check if a grid cell is valid for a slot (delegation for Blade templates).
         */
        isValidGridCell(slotLabel, col, row) {
            return _isValidGridCell(slotLabel, col, row, this.gridConfig);
        },

        /**
         * Get the zone highlight color class for a position group.
         */
        getZoneColorClass(role) {
            return _getZoneColorClass(role);
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

            // Enter pending state — not yet dragging until 5px threshold.
            // Don't clear assigningSlotId here — it fires on mousedown before
            // the click handler (toggle), which needs assigningSlotId intact.
            // Interaction modes are cleared when the drag actually activates.
            const coords = _getEventCoords(event);
            this._listDragPendingId = playerId;
            this._listDragStartCoords = { x: coords.clientX, y: coords.clientY };
            this._listDragMoved = false;

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

                // Activate drag — clear other interaction modes now (not on mousedown)
                this.assigningSlotId = null;
                this.positioningSlotId = null;
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
                    // Explicit drop target — use it.
                    this.slotMap = { ...this.slotMap, [this.listDragNearestSlotId]: playerId };
                } else {
                    // No explicit target — fall through to the primary-match helper.
                    this.slotMap = placeInFirstMatchingSlot(
                        this.slotMap,
                        this.currentSlots,
                        this.playersData[playerId],
                    );
                }
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

                const compatibility = this.getSlotCompatibility(player.id, slot.label);
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
