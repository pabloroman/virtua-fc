export default function lineupManager(config) {
    return {
        // State
        activeLineupTab: 'squad',
        selectedPlayers: config.currentLineup || [],
        selectedFormation: config.currentFormation,
        selectedMentality: config.currentMentality,
        autoLineup: config.autoLineup || [],

        // Manual slot assignments: { slotId: playerId }
        // When a user explicitly assigns a player to a slot, it's tracked here.
        manualAssignments: config.currentSlotAssignments || {},

        // Currently selected slot for manual assignment (null = no slot selected)
        selectedSlot: null,

        // Server data
        playersData: config.playersData,
        formationSlots: config.formationSlots,
        slotCompatibility: config.slotCompatibility,
        autoLineupUrl: config.autoLineupUrl,
        teamColors: config.teamColors,
        translations: config.translations,

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

        // Select a slot on the pitch for manual assignment
        selectSlot(slotId) {
            if (this.selectedSlot === slotId) {
                // Clicking same slot again deselects
                this.selectedSlot = null;
            } else {
                this.selectedSlot = slotId;
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

            // If this slot already has a manual assignment, remove it
            const previousPlayerId = this.manualAssignments[slotId];

            // Set the manual assignment
            this.manualAssignments = { ...this.manualAssignments, [slotId]: playerId };

            // Ensure the player is in the selected 11
            if (!this.isSelected(playerId)) {
                if (this.selectedCount >= 11) {
                    // Remove the player who was in this slot (if manually assigned) to make room
                    if (previousPlayerId && this.isSelected(previousPlayerId)) {
                        this.selectedPlayers = this.selectedPlayers.filter(p => p !== previousPlayerId);
                        this._removePlayerFromManualAssignments(previousPlayerId);
                    }
                }
                if (this.selectedCount < 11) {
                    this.selectedPlayers.push(playerId);
                }
            }

            this.selectedSlot = null;
        },

        // Click on a filled slot on the pitch
        handleSlotClick(slotId, playerId) {
            if (this.selectedSlot === slotId) {
                // Clicking the already-selected slot deselects it
                this.selectedSlot = null;
            } else {
                // Select this slot for reassignment
                this.selectedSlot = slotId;
            }
        },

        quickSelect() {
            this.selectedPlayers = [...this.autoLineup];
            this.manualAssignments = {};
            this.selectedSlot = null;
        },

        clearSelection() {
            this.selectedPlayers = [];
            this.manualAssignments = {};
            this.selectedSlot = null;
        },

        async updateAutoLineup() {
            try {
                const response = await fetch(`${this.autoLineupUrl}?formation=${this.selectedFormation}`);
                const data = await response.json();
                this.autoLineup = data.autoLineup;
                this.selectedPlayers = [...this.autoLineup];
                this.manualAssignments = {};
                this.selectedSlot = null;
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
                    return `background: repeating-linear-gradient(90deg, ${p} 0px, ${p} 5px, ${s} 5px, ${s} 10px)`;
                case 'hoops':
                    return `background: repeating-linear-gradient(0deg, ${p} 0px, ${p} 5px, ${s} 5px, ${s} 10px)`;
                case 'sash':
                    return `background: linear-gradient(135deg, ${p} 0%, ${p} 35%, ${s} 35%, ${s} 65%, ${p} 65%, ${p} 100%)`;
                case 'halves':
                    return `background: linear-gradient(90deg, ${p} 50%, ${s} 50%)`;
                default:
                    return `background: ${p}`;
            }
        },

        // Get the number/text color for a player badge
        getNumberColor(role) {
            if (role === 'Goalkeeper') return '#FFFFFF';
            return this.teamColors?.number || '#FFFFFF';
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
    };
}
