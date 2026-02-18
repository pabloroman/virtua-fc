export default function lineupManager(config) {
    return {
        // State
        activeLineupTab: 'squad',
        selectedPlayers: config.currentLineup || [],
        selectedFormation: config.currentFormation,
        selectedMentality: config.currentMentality,
        autoLineup: config.autoLineup || [],

        // Server data
        playersData: config.playersData,
        formationSlots: config.formationSlots,
        slotCompatibility: config.slotCompatibility,
        autoLineupUrl: config.autoLineupUrl,
        translations: config.translations,

        // Computed
        get selectedCount() { return this.selectedPlayers.length },
        get currentSlots() { return this.formationSlots[this.selectedFormation] || [] },

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
            // Map selected players to slots based on slot compatibility scores
            const slots = this.currentSlots.map(slot => ({ ...slot, player: null, compatibility: 0 }));
            const assigned = new Set();

            // Get all selected players
            const selectedPlayerData = this.selectedPlayers
                .map(id => this.playersData[id])
                .filter(p => p);

            const rolePriority = { 'Goalkeeper': 0, 'Forward': 1, 'Defender': 2, 'Midfielder': 3 };
            const sortedSlots = [...slots].sort((a, b) => {
                const aPriority = rolePriority[a.role] ?? 99;
                const bPriority = rolePriority[b.role] ?? 99;
                if (aPriority !== bPriority) return aPriority - bPriority;

                // Within same role, sort by specificity (fewer compatible positions first)
                const aCompat = Object.keys(this.slotCompatibility[a.label] || {}).length;
                const bCompat = Object.keys(this.slotCompatibility[b.label] || {}).length;
                return aCompat - bCompat;
            });

            // First pass: assign players with matching position group and compatibility > 0
            sortedSlots.forEach(slot => {
                let bestPlayer = null;
                let bestScore = -1;

                selectedPlayerData.forEach(player => {
                    if (assigned.has(player.id)) return;

                    // Only consider players whose position group matches the slot's role
                    // (Defenders in defense, Midfielders in midfield, etc.)
                    if (player.positionGroup !== slot.role) return;

                    const compatibility = this.getSlotCompatibility(player.position, slot.label);
                    if (compatibility === 0) return;

                    // Weighted score: 70% player rating, 30% compatibility
                    const weightedScore = (player.overallScore * 0.7) + (compatibility * 0.3);

                    if (weightedScore > bestScore) {
                        bestScore = weightedScore;
                        bestPlayer = { ...player, compatibility };
                    }
                });

                // Find the original slot and assign
                const originalSlot = slots.find(s => s.id === slot.id);
                if (originalSlot && bestPlayer) {
                    originalSlot.player = bestPlayer;
                    originalSlot.compatibility = bestPlayer.compatibility;
                    assigned.add(bestPlayer.id);
                }
            });

            // Second pass: fill any remaining empty slots with unassigned players (even with 0 compatibility)
            const emptySlots = slots.filter(s => !s.player);
            const unassignedPlayers = selectedPlayerData.filter(p => !assigned.has(p.id));

            emptySlots.forEach((slot, index) => {
                if (unassignedPlayers[index]) {
                    const player = unassignedPlayers[index];
                    const compatibility = this.getSlotCompatibility(player.position, slot.label);
                    slot.player = { ...player, compatibility };
                    slot.compatibility = compatibility;
                }
            });

            return slots;
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

        toggle(id, isUnavailable) {
            if (isUnavailable) return;
            if (this.isSelected(id)) {
                this.selectedPlayers = this.selectedPlayers.filter(p => p !== id);
            } else if (this.selectedCount < 11) {
                this.selectedPlayers.push(id);
            }
        },

        quickSelect() { this.selectedPlayers = [...this.autoLineup] },
        clearSelection() { this.selectedPlayers = [] },

        async updateAutoLineup() {
            try {
                const response = await fetch(`${this.autoLineupUrl}?formation=${this.selectedFormation}`);
                const data = await response.json();
                this.autoLineup = data.autoLineup;
                this.selectedPlayers = [...this.autoLineup];
            } catch (e) {
                console.error('Failed to fetch auto lineup', e);
            }
        },

        getPositionColor(role) {
            return {
                'Goalkeeper': 'bg-amber-500',
                'Defender': 'bg-blue-600',
                'Midfielder': 'bg-emerald-600',
                'Forward': 'bg-red-600',
            }[role] || 'bg-slate-500';
        },

        removeFromSlot(playerId) {
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
            if (!name) return '??';
            const parts = name.trim().split(/\s+/);
            if (parts.length === 1) {
                // Single name: take first 2 characters
                return parts[0].substring(0, 2).toUpperCase();
            }
            // Multiple names: first letter of first name + first letter of last name
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        },

        // Get gradient colors for position
        getPositionGradient(role) {
            return {
                'Goalkeeper': 'from-amber-400 to-amber-600',
                'Defender': 'from-blue-500 to-blue-700',
                'Midfielder': 'from-emerald-500 to-emerald-700',
                'Forward': 'from-red-500 to-red-700',
            }[role] || 'from-slate-400 to-slate-600';
        },
    };
}
