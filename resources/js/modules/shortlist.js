// Shared client for the scouting shortlist. Every shortlist surface — the star
// on each <x-explore-player-row>, the dossier modal's remove control, and the
// scouting hub board — talks to the server the same way and coordinates through
// a single `shortlist-toggled` window event, so the fetch + dispatch lives here
// once instead of being duplicated across three inline x-data blocks.

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function post(url) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    }).then((r) => r.json());
}

// Broadcast a shortlist change so every star + the board stay in sync.
function announce(detail) {
    window.dispatchEvent(new CustomEvent('shortlist-toggled', { detail }));
}

// Toggle a player on/off the shortlist. Resolves with the server payload
// ({ success, action, playerId, player, message }); announces only on success.
export async function toggleShortlist(url) {
    const data = await post(url);
    if (data.success) {
        announce({ action: data.action, playerId: data.playerId, player: data.player || null });
    }
    return data;
}

// Remove a player from the shortlist (dossier modal). Announces the removal
// using the known playerId; resolves with the server payload.
export async function removeFromShortlist(url, playerId) {
    const data = await post(url);
    announce({ action: 'removed', playerId });
    return data;
}
