/*
 * Sofascore player-ID finder
 * --------------------------
 * Takes the players the crosswalk doesn't cover (from
 * `php artisan app:list-unmapped-players`) and resolves each one to a Sofascore
 * player ID, emitting rows ready to paste into data/sofascore_ids_overrides.csv.
 *
 * WHY THIS RUNS IN THE BROWSER (and only on sofascore.com):
 * Same bot protection as the sibling image downloader -- /api/v1/... 403s any
 * request whose origin/referer isn't sofascore.com. curl, a file:// page and a
 * fetch from any other site all get 403; the identical fetch from a tab already
 * on sofascore.com returns 200. So: open https://www.sofascore.com and paste this
 * into the DevTools console (see README.md).
 *
 * HOW A MATCH IS MADE (and why it can be trusted):
 * Search returns name/club/country but NO date of birth, so every candidate is
 * confirmed with a second call to /api/v1/player/{id} and accepted ONLY when its
 * dateOfBirthTimestamp is the player's exact date of birth. Name similarity alone
 * is never enough -- football is full of same-name players, and a wrong ID shows
 * the wrong face on a player's profile forever. Anything unconfirmed is written
 * out as a commented-out row for a human, never guessed.
 *
 * Measured on a 15-player sample of the real 2026 gap: 10 confirmed, 5 genuinely
 * absent from Sofascore. Broadening the query (surname only) returned noise, not
 * matches, so it deliberately isn't attempted.
 *
 * OUTPUT: one .csv in data/sofascore_ids_overrides.csv's own format
 * (key_transfermarkt,key_sofascore,name). Confirmed players are real rows;
 * everything else is a `#` comment line, which app:build-sofascore-id-map skips.
 * So the file can be appended to the overrides wholesale and tidied later.
 */
(function () {
    'use strict';

    // ---- Config (also editable via the panel) ----
    const SEARCH_URL = (q) => '/api/v1/search/all?' + new URLSearchParams({ q, page: '0' });
    const PLAYER_URL = (id) => `/api/v1/player/${id}`;
    const DEFAULT_CONCURRENCY = 3;   // gentler than the image downloader: this is an API, not a CDN
    const DEFAULT_DELAY_MS = 150;    // pause between a worker's requests
    const MAX_CANDIDATES = 5;        // per-name candidates to confirm before giving up
    const FETCH_ATTEMPTS = 2;

    const NS = '__sofascoreIdFinder';
    if (window[NS] && window[NS].destroy) {
        window[NS].destroy();
    }

    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

    // ---------------------------------------------------------------------------
    // Input parsing
    // ---------------------------------------------------------------------------
    // Expects the JSON array from `php artisan app:list-unmapped-players`:
    // [{tm, name, dob, club}, ...]. dob is required -- it is the whole basis of
    // the match, so a player without one is dropped rather than guessed at.
    function parsePlayers(text) {
        const raw = (text || '').trim();
        if (!raw) return { players: [], skipped: 0 };
        let parsed;
        try {
            parsed = JSON.parse(raw);
        } catch (e) {
            throw new Error('Input is not valid JSON. Paste the output of `php artisan app:list-unmapped-players`.');
        }
        if (!Array.isArray(parsed)) throw new Error('Expected a JSON array of {tm, name, dob}.');

        const seen = new Set();
        const players = [];
        let skipped = 0;
        for (const p of parsed) {
            const tm = String((p && p.tm) || '').trim();
            const name = String((p && p.name) || '').trim();
            const dob = String((p && p.dob) || '').trim();
            if (!tm || !name || !/^\d{4}-\d{2}-\d{2}$/.test(dob)) { skipped++; continue; }
            if (seen.has(tm)) continue;
            seen.add(tm);
            players.push({ tm, name, dob, club: (p && p.club) || '' });
        }
        return { players, skipped };
    }

    // ---------------------------------------------------------------------------
    // Fetching
    // ---------------------------------------------------------------------------
    async function getJson(url) {
        for (let attempt = 1; attempt <= FETCH_ATTEMPTS; attempt++) {
            try {
                const r = await fetch(url, { headers: { accept: 'application/json' } });
                if (r.status === 404) return null;
                if (!r.ok) {
                    if (attempt === FETCH_ATTEMPTS) throw new Error(`HTTP ${r.status}`);
                    await sleep(400 * attempt);
                    continue;
                }
                return await r.json();
            } catch (e) {
                if (attempt === FETCH_ATTEMPTS) throw e;
                await sleep(400 * attempt);
            }
        }
        return null;
    }

    const isoDob = (ts) => (ts == null ? null : new Date(ts * 1000).toISOString().slice(0, 10));
    // Sofascore spells accents inconsistently ("Lacine Kone" vs "Lacine Koné"),
    // so queries are tried both ways. Matching itself never relies on this.
    const stripAccents = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '');

    async function resolve(player) {
        const queries = [player.name];
        const plain = stripAccents(player.name);
        if (plain !== player.name) queries.push(plain);

        let candidatesSeen = 0;
        const checked = new Set();

        for (const q of queries) {
            const search = await getJson(SEARCH_URL(q));
            const candidates = ((search && search.results) || [])
                .filter((r) => r.type === 'player' && r.entity && r.entity.id)
                .slice(0, MAX_CANDIDATES);

            for (const c of candidates) {
                const id = String(c.entity.id);
                if (checked.has(id)) continue;
                checked.add(id);
                candidatesSeen++;

                const detail = await getJson(PLAYER_URL(id));
                const found = detail && detail.player;
                if (found && isoDob(found.dateOfBirthTimestamp) === player.dob) {
                    return {
                        ...player,
                        ok: true,
                        sofascoreId: id,
                        matchedName: found.name || c.entity.name,
                        matchedClub: (found.team && found.team.name) || '',
                    };
                }
                await sleep(window[NS] ? window[NS].delay : DEFAULT_DELAY_MS);
            }
        }

        return {
            ...player,
            ok: false,
            reason: candidatesSeen === 0 ? 'no search hits' : `${candidatesSeen} candidate(s), none with matching DOB`,
        };
    }

    // Fixed-size worker pool, same shape as the image downloader's.
    async function runPool(players, concurrency, onResult) {
        let next = 0;
        const results = new Array(players.length);
        async function worker() {
            while (true) {
                const idx = next++;
                if (idx >= players.length) return;
                try {
                    results[idx] = await resolve(players[idx]);
                } catch (e) {
                    results[idx] = { ...players[idx], ok: false, reason: 'error: ' + ((e && e.message) || e) };
                }
                onResult(results[idx]);
                await sleep(window[NS] ? window[NS].delay : DEFAULT_DELAY_MS);
            }
        }
        const workers = [];
        const n = Math.max(1, Math.min(concurrency, players.length));
        for (let w = 0; w < n; w++) workers.push(worker());
        await Promise.all(workers);
        return results;
    }

    // ---------------------------------------------------------------------------
    // Output
    // ---------------------------------------------------------------------------
    // A CSV cell only needs quoting for , " or a newline; names can contain commas.
    function cell(value) {
        const s = String(value == null ? '' : value);
        return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    }

    function buildCsv(results) {
        const ok = results.filter((r) => r && r.ok);
        const bad = results.filter((r) => r && !r.ok);
        const stamp = new Date().toISOString().slice(0, 10);
        const lines = [
            'key_transfermarkt,key_sofascore,name',
            `# Generated by scripts/sofascore-id-finder on ${stamp}.`,
            `# ${ok.length} confirmed by exact date of birth, ${bad.length} unresolved.`,
            '# Append to data/sofascore_ids_overrides.csv, then run:',
            '#   php artisan app:build-sofascore-id-map',
        ];

        for (const r of ok) {
            lines.push([cell(r.tm), cell(r.sofascoreId), cell(r.matchedName)].join(','));
        }

        if (bad.length) {
            lines.push('#');
            lines.push('# --- Unresolved: fill in the middle column by hand and uncomment. ---');
            lines.push('# Find the id in the profile URL: sofascore.com/player/{slug}/{id}');
            for (const r of bad) {
                lines.push(`# ${cell(r.tm)},,${cell(r.name)}   (dob ${r.dob}, ${r.club || 'no club'}) -- ${r.reason}`);
            }
        }

        return lines.join('\n') + '\n';
    }

    function triggerDownload(text, filename) {
        const blob = new Blob([text], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 30000);
    }

    // ---------------------------------------------------------------------------
    // UI panel
    // ---------------------------------------------------------------------------
    const root = document.createElement('div');
    root.style.cssText = [
        'position:fixed', 'top:16px', 'right:16px', 'z-index:2147483647',
        'width:340px', 'max-width:calc(100vw - 32px)', 'background:#0f172a',
        'color:#e2e8f0', 'font:13px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
        'border:1px solid #334155', 'border-radius:10px', 'box-shadow:0 10px 30px rgba(0,0,0,.5)',
        'padding:14px', 'box-sizing:border-box',
    ].join(';');

    root.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
            <strong style="font-size:13px">Sofascore ID finder</strong>
            <button data-role="close" style="background:none;border:none;color:#94a3b8;font-size:18px;line-height:1;cursor:pointer;padding:0 2px">×</button>
        </div>
        <textarea data-role="players" rows="5" placeholder="Paste the JSON from: php artisan app:list-unmapped-players"
            style="width:100%;box-sizing:border-box;resize:vertical;background:#020617;color:#e2e8f0;border:1px solid #334155;border-radius:6px;padding:8px;font:12px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace"></textarea>
        <div style="display:flex;gap:8px;margin:8px 0">
            <label style="flex:1;color:#94a3b8">Concurrency
                <input data-role="concurrency" type="number" min="1" max="10" value="${DEFAULT_CONCURRENCY}"
                    style="width:100%;box-sizing:border-box;background:#020617;color:#e2e8f0;border:1px solid #334155;border-radius:6px;padding:4px 6px">
            </label>
            <label style="flex:1;color:#94a3b8">Delay (ms)
                <input data-role="delay" type="number" min="0" value="${DEFAULT_DELAY_MS}"
                    style="width:100%;box-sizing:border-box;background:#020617;color:#e2e8f0;border:1px solid #334155;border-radius:6px;padding:4px 6px">
            </label>
        </div>
        <button data-role="go" style="width:100%;background:#2563eb;color:#fff;border:none;border-radius:6px;padding:9px;font-weight:600;cursor:pointer">Find IDs</button>
        <div data-role="bar" style="height:6px;background:#1e293b;border-radius:3px;margin-top:10px;overflow:hidden;display:none">
            <div data-role="fill" style="height:100%;width:0;background:#22c55e;transition:width .15s"></div>
        </div>
        <pre data-role="log" style="max-height:140px;overflow:auto;margin:10px 0 0;padding:0;color:#94a3b8;font:11px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap"></pre>
    `;
    document.body.appendChild(root);

    const $ = (role) => root.querySelector(`[data-role="${role}"]`);
    const logEl = $('log');
    function log(msg) {
        logEl.textContent += (logEl.textContent ? '\n' : '') + msg;
        logEl.scrollTop = logEl.scrollHeight;
    }
    function setProgress(done, total) {
        $('bar').style.display = 'block';
        $('fill').style.width = total ? Math.round((done / total) * 100) + '%' : '0%';
    }

    async function run() {
        const goBtn = $('go');
        let players, skipped;
        try {
            ({ players, skipped } = parsePlayers($('players').value));
        } catch (e) {
            log((e && e.message) || String(e));
            return;
        }
        if (!players.length) { log('No usable players found (each needs tm, name and a YYYY-MM-DD dob).'); return; }
        if (skipped) log(`Skipped ${skipped} entr(ies) missing tm/name/dob.`);

        window[NS].delay = Math.max(0, parseInt($('delay').value, 10) || 0);
        const concurrency = Math.max(1, parseInt($('concurrency').value, 10) || DEFAULT_CONCURRENCY);

        goBtn.disabled = true;
        goBtn.style.opacity = '.6';
        log(`${players.length} player(s). Searching with concurrency ${concurrency}...`);

        let done = 0;
        let found = 0;
        const results = await runPool(players, concurrency, (r) => {
            if (r && r.ok) found++;
            setProgress(++done, players.length);
            // Chatty enough to show life on a long run, quiet enough not to flood.
            if (done % 25 === 0 || done === players.length) log(`  ${done}/${players.length} checked, ${found} confirmed`);
        });

        const csv = buildCsv(results);
        // Kept on the namespace so a finished run can be re-copied from the
        // console (copy(__sofascoreIdFinder.lastCsv)) without searching again.
        window[NS].lastCsv = csv;
        log(`Done: ${found} confirmed, ${results.length - found} unresolved.`);
        triggerDownload(csv, 'sofascore_ids_overrides_additions.csv');
        log('Downloaded sofascore_ids_overrides_additions.csv');

        goBtn.disabled = false;
        goBtn.style.opacity = '1';
    }

    $('go').addEventListener('click', () => { run().catch((e) => log('Error: ' + ((e && e.message) || e))); });
    $('close').addEventListener('click', () => window[NS].destroy());

    window[NS] = { destroy: () => root.remove(), run, delay: DEFAULT_DELAY_MS, lastCsv: null };
    log('Ready. Paste the unmapped-players JSON and click Find IDs.');
})();
