export default function squadRegistration(config) {
    return {
        players: config.players,
        slots: config.slots,
        academyPlayers: config.academyPlayers,

        // Drag state
        dragPlayerId: null,
        dragSourceSlot: null, // number for first-team slot, 'academy' for academy, null for unregistered
        dropTargetSlot: null,

        // Touch drag state
        touchActive: false,
        touchTimer: null,
        touchClone: null,
        touchStartX: 0,
        touchStartY: 0,

        get unregisteredIds() {
            const assigned = new Set(Object.values(this.slots).filter(Boolean));
            for (const ap of this.academyPlayers) {
                assigned.add(ap.id);
            }
            return Object.keys(this.players).filter(id => !assigned.has(id));
        },

        get registeredCount() {
            return Object.values(this.slots).filter(Boolean).length + this.academyPlayers.length;
        },

        get firstTeamCount() {
            let count = 0;
            for (let i = 1; i <= 25; i++) {
                if (this.slots[i]) count++;
            }
            return count;
        },

        sortedUnregistered() {
            return this.unregisteredIds.slice().sort((a, b) => {
                const pa = this.players[a];
                const pb = this.players[b];
                const groupOrder = { Goalkeeper: 0, Defender: 1, Midfielder: 2, Forward: 3 };
                const ga = groupOrder[pa.position_group] ?? 9;
                const gb = groupOrder[pb.position_group] ?? 9;
                if (ga !== gb) return ga - gb;
                return pb.overall - pa.overall;
            });
        },

        getPlayer(slotNumber) {
            const id = this.slots[slotNumber];
            return id ? this.players[id] : null;
        },

        // --- Academy helpers ---

        allTakenNumbers() {
            const taken = new Set();
            for (let i = 1; i <= 25; i++) {
                if (this.slots[i]) taken.add(i);
            }
            for (const ap of this.academyPlayers) {
                taken.add(ap.number);
            }
            return taken;
        },

        nextAvailableAcademyNumber() {
            const taken = this.allTakenNumbers();
            for (let n = 26; n <= 99; n++) {
                if (!taken.has(n)) return n;
            }
            return 26;
        },

        canBeAcademy(playerId) {
            return this.players[playerId].age < 23;
        },

        addToAcademy(playerId) {
            if (!this.canBeAcademy(playerId)) return;
            // Remove from first-team slot if there
            for (let i = 1; i <= 25; i++) {
                if (this.slots[i] === playerId) {
                    this.slots[i] = null;
                    break;
                }
            }
            // Don't add if already in academy
            if (this.academyPlayers.some(ap => ap.id === playerId)) return;
            this.academyPlayers.push({ id: playerId, number: this.nextAvailableAcademyNumber() });
        },

        removeFromAcademy(playerId) {
            this.academyPlayers = this.academyPlayers.filter(ap => ap.id !== playerId);
        },

        isAcademyNumberValid(number, playerId) {
            if (number < 26 || number > 99) return false;
            // Check against other academy players
            for (const ap of this.academyPlayers) {
                if (ap.id !== playerId && ap.number === number) return false;
            }
            // Check against first-team slots (shouldn't overlap but be safe)
            for (let i = 1; i <= 25; i++) {
                if (this.slots[i] && i === number) return false;
            }
            return true;
        },

        updateAcademyNumber(playerId, rawValue) {
            const number = parseInt(rawValue, 10);
            if (isNaN(number)) return;
            const entry = this.academyPlayers.find(ap => ap.id === playerId);
            if (entry) entry.number = number;
        },

        // --- Style helpers ---

        positionBadgeClass(group) {
            return {
                'bg-amber-500': group === 'Goalkeeper',
                'bg-blue-600': group === 'Defender',
                'bg-emerald-600': group === 'Midfielder',
                'bg-red-600': group === 'Forward',
            };
        },

        ratingBadgeClass(value) {
            if (value >= 80) return 'rating-elite';
            if (value >= 70) return 'rating-good';
            if (value >= 60) return 'rating-average';
            if (value >= 50) return 'rating-below';
            return 'rating-poor';
        },

        // --- HTML5 Drag and Drop ---

        onDragStart(event, playerId, sourceSlot) {
            this.dragPlayerId = playerId;
            this.dragSourceSlot = sourceSlot;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', playerId);
            event.target.classList.add('opacity-50');
        },

        onDragEnd(event) {
            event.target.classList.remove('opacity-50');
            this.dragPlayerId = null;
            this.dragSourceSlot = null;
            this.dropTargetSlot = null;
        },

        onDragOverSlot(event, slotNumber) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            this.dropTargetSlot = slotNumber;
        },

        onDragOverUnregistered(event) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            this.dropTargetSlot = 'unregistered';
        },

        onDragOverAcademy(event) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            this.dropTargetSlot = 'academy';
        },

        onDragLeave() {
            this.dropTargetSlot = null;
        },

        onDropSlot(event, targetSlot) {
            event.preventDefault();
            this.dropTargetSlot = null;
            if (!this.dragPlayerId) return;

            // Remove from academy if dragging from there
            if (this.dragSourceSlot === 'academy') {
                this.removeFromAcademy(this.dragPlayerId);
            }

            const targetOccupant = this.slots[targetSlot];

            if (typeof this.dragSourceSlot === 'number') {
                // Dragging from another first-team slot — swap
                this.slots[targetSlot] = this.dragPlayerId;
                this.slots[this.dragSourceSlot] = targetOccupant;
            } else {
                // Dragging from unregistered or academy
                this.slots[targetSlot] = this.dragPlayerId;
            }

            this.dragPlayerId = null;
            this.dragSourceSlot = null;
        },

        onDropUnregistered(event) {
            event.preventDefault();
            this.dropTargetSlot = null;
            if (!this.dragPlayerId) return;

            if (typeof this.dragSourceSlot === 'number') {
                this.slots[this.dragSourceSlot] = null;
            } else if (this.dragSourceSlot === 'academy') {
                this.removeFromAcademy(this.dragPlayerId);
            }

            this.dragPlayerId = null;
            this.dragSourceSlot = null;
        },

        onDropAcademy(event) {
            event.preventDefault();
            this.dropTargetSlot = null;
            if (!this.dragPlayerId) return;

            // Remove from first-team slot if needed
            if (typeof this.dragSourceSlot === 'number') {
                this.slots[this.dragSourceSlot] = null;
            }

            // Add to academy (addToAcademy handles dedup)
            this.addToAcademy(this.dragPlayerId);

            this.dragPlayerId = null;
            this.dragSourceSlot = null;
        },

        removeFromSlot(slotNumber) {
            this.slots[slotNumber] = null;
        },

        assignToNextSlot(playerId) {
            for (let i = 1; i <= 25; i++) {
                if (!this.slots[i]) {
                    this.slots[i] = playerId;
                    return;
                }
            }
        },

        // --- Touch support ---

        onTouchStart(event, playerId, sourceSlot) {
            this.touchStartX = event.touches[0].clientX;
            this.touchStartY = event.touches[0].clientY;

            this.touchTimer = setTimeout(() => {
                this.touchActive = true;
                this.dragPlayerId = playerId;
                this.dragSourceSlot = sourceSlot;

                const el = event.target.closest('[data-draggable]');
                if (!el) return;

                const rect = el.getBoundingClientRect();
                const clone = el.cloneNode(true);
                clone.style.position = 'fixed';
                clone.style.width = rect.width + 'px';
                clone.style.top = (event.touches[0].clientY - rect.height / 2) + 'px';
                clone.style.left = (event.touches[0].clientX - rect.width / 2) + 'px';
                clone.style.opacity = '0.85';
                clone.style.zIndex = '9999';
                clone.style.pointerEvents = 'none';
                clone.style.transform = 'scale(1.05)';
                clone.style.transition = 'transform 0.1s';
                clone.classList.add('touch-drag-clone');
                document.body.appendChild(clone);
                this.touchClone = clone;

                if (navigator.vibrate) navigator.vibrate(30);
            }, 200);
        },

        onTouchMove(event) {
            if (!this.touchActive) {
                const dx = event.touches[0].clientX - this.touchStartX;
                const dy = event.touches[0].clientY - this.touchStartY;
                if (Math.abs(dx) > 10 || Math.abs(dy) > 10) {
                    clearTimeout(this.touchTimer);
                }
                return;
            }

            event.preventDefault();
            const touch = event.touches[0];

            if (this.touchClone) {
                const rect = this.touchClone.getBoundingClientRect();
                this.touchClone.style.top = (touch.clientY - rect.height / 2) + 'px';
                this.touchClone.style.left = (touch.clientX - rect.width / 2) + 'px';
            }

            if (this.touchClone) this.touchClone.style.display = 'none';
            const target = document.elementFromPoint(touch.clientX, touch.clientY);
            if (this.touchClone) this.touchClone.style.display = '';

            if (target) {
                const slotEl = target.closest('[data-slot]');
                const unregEl = target.closest('[data-unregistered-zone]');
                const acadEl = target.closest('[data-academy-zone]');
                if (slotEl) {
                    this.dropTargetSlot = parseInt(slotEl.dataset.slot);
                } else if (acadEl) {
                    this.dropTargetSlot = 'academy';
                } else if (unregEl) {
                    this.dropTargetSlot = 'unregistered';
                } else {
                    this.dropTargetSlot = null;
                }
            }
        },

        onTouchEnd() {
            clearTimeout(this.touchTimer);

            if (!this.touchActive) {
                this.touchActive = false;
                return;
            }

            if (this.touchClone) {
                this.touchClone.remove();
                this.touchClone = null;
            }

            if (this.dropTargetSlot === 'unregistered') {
                if (typeof this.dragSourceSlot === 'number') {
                    this.slots[this.dragSourceSlot] = null;
                } else if (this.dragSourceSlot === 'academy') {
                    this.removeFromAcademy(this.dragPlayerId);
                }
            } else if (this.dropTargetSlot === 'academy') {
                if (typeof this.dragSourceSlot === 'number') {
                    this.slots[this.dragSourceSlot] = null;
                }
                this.addToAcademy(this.dragPlayerId);
            } else if (typeof this.dropTargetSlot === 'number') {
                if (this.dragSourceSlot === 'academy') {
                    this.removeFromAcademy(this.dragPlayerId);
                }
                const targetOccupant = this.slots[this.dropTargetSlot];
                if (typeof this.dragSourceSlot === 'number') {
                    this.slots[this.dropTargetSlot] = this.dragPlayerId;
                    this.slots[this.dragSourceSlot] = targetOccupant;
                } else {
                    this.slots[this.dropTargetSlot] = this.dragPlayerId;
                }
            }

            this.touchActive = false;
            this.dragPlayerId = null;
            this.dragSourceSlot = null;
            this.dropTargetSlot = null;
        },
    };
}
