/**
 * Shared pitch grid positioning and drag-and-drop module.
 *
 * Used by both the lineup page (pre-match squad building) and the live match
 * tactical center (in-game repositioning). Behavioral differences between the
 * two contexts are expressed through the `options` object — see the
 * "Shared vs. Divergent Behavior Map" in the refactoring plan.
 *
 * @param {Function} ctx - Returns the Alpine component instance (for reactive state access)
 * @param {Object} options - Context-specific configuration
 * @param {boolean} options.allowGkDrag - Whether GK badges can be dragged (lineup: true, live: false)
 * @param {boolean} options.allowGkReposition - Whether GK can be click-repositioned (lineup: true, live: false)
 * @param {boolean} options.allowGkSwap - Whether cells occupied by GK are swappable (lineup: true, live: false)
 * @param {number} options.dragThreshold - Pixels moved before drag activates (lineup: 0, live: 5)
 * @param {Function|null} options.onTapFallback - Called with (slot) when drag threshold isn't met (live: handlePitchPlayerClick)
 * @param {Function|null} options.onPositionChanged - Called with (newPositions) after a position change (live: track formation, backup)
 * @param {string} options.pitchElementId - DOM id of the pitch container element
 * @param {Function} options.getPositions - Returns current pitch positions object
 * @param {Function} options.setPositions - Sets pitch positions object
 * @param {Function|null} options.getFormationGuard - Returns {effective, tracked} for formation mismatch checks (live only)
 */

import {
    getEventCoords,
    getDragPosition,
    getCellFromClientCoords,
    isValidGridCell,
} from './pitch-renderer.js';

export function createPitchGrid(ctx, options) {
    // Internal state for drag handler binding (created once per instance)
    let _boundDragMove = null;
    let _boundDragEnd = null;
    let _pitchEl = null;

    // Pending drag state (for tap-vs-drag threshold)
    let _pendingDragSlotId = null;
    let _dragStartCoords = null;
    let _wasDragging = false;

    // =========================================================================
    // Internal helpers
    // =========================================================================

    function _getPitchElement() {
        if (!_pitchEl) {
            _pitchEl = document.getElementById(options.pitchElementId);
        }
        return _pitchEl;
    }

    /**
     * Get effective positions, respecting formation guard if configured.
     * When the formation has changed, returns empty positions (old slot IDs are invalid).
     */
    function _getGuardedPositions() {
        const guard = options.getFormationGuard?.();
        if (guard && guard.effective !== guard.tracked) {
            return {};
        }
        return options.getPositions();
    }

    /**
     * Get the grid cell for a slot (custom position or formation default).
     */
    function getSlotCell(slotId) {
        const positions = _getGuardedPositions();
        const customPos = positions[String(slotId)];
        if (customPos) return { col: customPos[0], row: customPos[1] };

        const state = ctx();
        const gc = state.gridConfig;
        if (!gc) return null;

        // Determine effective formation for default cell lookup
        const guard = options.getFormationGuard?.();
        const formation = guard
            ? guard.effective
            : state.selectedFormation;

        const defaultCells = gc.defaultCells[formation];
        return defaultCells ? defaultCells[slotId] : null;
    }

    /**
     * Find the slot occupying a cell (excluding a given slot).
     */
    function _findSlotAtCell(col, row, excludeSlotId) {
        const assignments = ctx().slotAssignments;
        for (const slot of assignments) {
            if (slot.id === excludeSlotId || !slot.player) continue;
            const cell = getSlotCell(slot.id);
            if (cell && cell.col === col && cell.row === row) return slot;
        }
        return null;
    }

    // =========================================================================
    // Grid positioning
    // =========================================================================

    /**
     * Check if a grid cell is occupied by another slot.
     */
    function isCellOccupied(col, row, excludeSlotId) {
        return _findSlotAtCell(col, row, excludeSlotId) !== null;
    }

    /**
     * Toggle click-to-place repositioning mode for a slot.
     */
    function selectForRepositioning(slotId) {
        const state = ctx();
        const slot = state.currentSlots.find(s => s.id === slotId);

        // Block GK repositioning if not allowed
        if (!options.allowGkReposition && slot && slot.role === 'Goalkeeper') return;

        // Clear slot assignment mode (lineup only, harmless if absent)
        if ('assigningSlotId' in state) {
            state.assigningSlotId = null;
        }

        if (state.positioningSlotId === slotId) {
            state.positioningSlotId = null;
        } else {
            state.positioningSlotId = slotId;
        }
    }

    /**
     * Place a slot at a specific grid cell. If occupied, swap positions.
     */
    function setSlotGridPosition(slotId, col, row) {
        const state = ctx();
        const slot = state.currentSlots.find(s => s.id === slotId);
        if (!slot) return;

        if (!isValidGridCell(slot.label, col, row, state.gridConfig)) return;

        const occupying = _findSlotAtCell(col, row, slotId);

        // Block swap with GK if not allowed
        if (occupying && !options.allowGkSwap && occupying.role === 'Goalkeeper') return;

        // Start with current positions (respecting formation guard)
        const newPositions = { ..._getGuardedPositions() };

        if (occupying) {
            // Move occupying slot to dragged slot's old cell (validate reverse direction)
            const draggedCell = getSlotCell(slotId);
            if (draggedCell && !isValidGridCell(occupying.label, draggedCell.col, draggedCell.row, state.gridConfig)) return;
            if (draggedCell) {
                newPositions[String(occupying.id)] = [draggedCell.col, draggedCell.row];
            }
        }

        newPositions[String(slotId)] = [col, row];
        options.setPositions(newPositions);
        state.positioningSlotId = null;

        // Notify context of position change (live-match uses this for formation tracking, backup, UI flag)
        options.onPositionChanged?.(newPositions);
    }

    /**
     * Handle clicking a grid cell (when a slot is selected for repositioning).
     */
    function handleGridCellClick(col, row) {
        if (ctx().positioningSlotId === null) return;
        setSlotGridPosition(ctx().positioningSlotId, col, row);
    }

    /**
     * Get the cell state for rendering grid overlays.
     * Returns: 'valid-def', 'valid-mid', 'valid-fwd', 'valid', 'valid-gk', 'occupied', 'invalid', or 'neutral'
     */
    function getGridCellState(col, row) {
        const state = ctx();
        if (state.positioningSlotId === null && state.draggingSlotId === null) return 'neutral';

        const activeSlotId = state.positioningSlotId ?? state.draggingSlotId;
        const slot = state.currentSlots.find(s => s.id === activeSlotId);
        if (!slot) return 'neutral';

        if (!isValidGridCell(slot.label, col, row, state.gridConfig)) return 'invalid';

        // Check if cell is occupied by another player
        const occupying = _findSlotAtCell(col, row, activeSlotId);

        if (occupying) {
            if (!options.allowGkSwap && occupying.role === 'Goalkeeper') return 'occupied';
            if (options.allowGkSwap && occupying.role === 'Goalkeeper') return 'valid-gk';
        }

        // GK repositioning hints
        if (slot.role === 'Goalkeeper') {
            if (!options.allowGkReposition) return 'valid';
            // In lineup, show zone-colored hints for GK
            if (row === 0) return 'valid';
            if (row <= 4) return 'valid-def';
            if (row <= 9) return 'valid-mid';
            return 'valid-fwd';
        }

        // Outfield zone coloring
        if (row <= 4) return 'valid-def';
        if (row <= 9) return 'valid-mid';
        return 'valid-fwd';
    }

    // =========================================================================
    // Drag-and-drop
    // =========================================================================

    function _onDragMove(event) {
        const state = ctx();

        // Handle threshold check (when dragThreshold > 0)
        if (_pendingDragSlotId && state.draggingSlotId === null) {
            const coords = getEventCoords(event);
            const dx = coords.clientX - _dragStartCoords.x;
            const dy = coords.clientY - _dragStartCoords.y;
            if (Math.sqrt(dx * dx + dy * dy) < options.dragThreshold) return;

            // Threshold exceeded — activate drag
            state.draggingSlotId = _pendingDragSlotId;
            _pendingDragSlotId = null;
            _wasDragging = true;
            state.positioningSlotId = null;
        }

        if (state.draggingSlotId === null && !_pendingDragSlotId) return;
        event.preventDefault();

        const coords = getEventCoords(event);
        state.dragPosition = getDragPosition(coords.clientX, coords.clientY, _getPitchElement());
    }

    function _onDragEnd(event) {
        const state = ctx();

        // If threshold was never reached, treat as a tap
        if (_pendingDragSlotId) {
            const slot = state.currentSlots.find(s => s.id === _pendingDragSlotId);
            _pendingDragSlotId = null;
            _dragStartCoords = null;
            _cleanupDragListeners();
            if (slot && options.onTapFallback) {
                options.onTapFallback(slot);
            }
            return;
        }

        if (state.draggingSlotId !== null) {
            const coords = getEventCoords(event);
            const cell = getCellFromClientCoords(coords.clientX, coords.clientY, _getPitchElement(), state.gridConfig);
            if (cell) {
                setSlotGridPosition(state.draggingSlotId, cell.col, cell.row);
            }
        }

        state.draggingSlotId = null;
        state.dragPosition = null;
        _dragStartCoords = null;
        _cleanupDragListeners();
    }

    function _cleanupDragListeners() {
        document.removeEventListener('mousemove', _boundDragMove);
        document.removeEventListener('mouseup', _boundDragEnd);
        document.removeEventListener('touchmove', _boundDragMove);
        document.removeEventListener('touchend', _boundDragEnd);
    }

    /**
     * Begin dragging a player badge on the pitch.
     */
    function startDrag(slotId, event) {
        const state = ctx();
        const slot = state.currentSlots.find(s => s.id === slotId);

        // Block GK drag if not allowed
        if (!options.allowGkDrag && slot && slot.role === 'Goalkeeper') return;

        event.preventDefault();

        const coords = getEventCoords(event);

        if (options.dragThreshold > 0) {
            // Deferred activation: wait for threshold before entering drag mode
            _pendingDragSlotId = slotId;
            _dragStartCoords = { x: coords.clientX, y: coords.clientY };
            _wasDragging = false;
        } else {
            // Immediate drag (lineup mode)
            state.draggingSlotId = slotId;
            state.positioningSlotId = null;
            state.dragPosition = getDragPosition(coords.clientX, coords.clientY, _getPitchElement());
        }

        // Register drag listeners
        document.addEventListener('mousemove', _boundDragMove);
        document.addEventListener('mouseup', _boundDragEnd);
        document.addEventListener('touchmove', _boundDragMove, { passive: false });
        document.addEventListener('touchend', _boundDragEnd);
    }

    // =========================================================================
    // Initialization
    // =========================================================================

    // Create bound handlers once
    _boundDragMove = (e) => _onDragMove(e);
    _boundDragEnd = (e) => _onDragEnd(e);

    // =========================================================================
    // Public API — these are spread onto the Alpine component via Object.assign
    // =========================================================================

    return {
        getSlotCell,
        isCellOccupied,
        selectForRepositioning,
        setSlotGridPosition,
        handleGridCellClick,
        getGridCellState,
        startDrag,

        // Expose for internal use by slotAssignments and getEffectivePosition
        _findSlotAtCell,
        _getPitchElement() { return _getPitchElement(); },

        /**
         * Whether a drag just completed (for suppressing click events in live-match).
         * Read and reset by the consumer.
         */
        get _wasDragging() { return _wasDragging; },
        set _wasDragging(v) { _wasDragging = v; },
    };
}
