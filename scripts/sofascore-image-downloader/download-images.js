/*
 * Sofascore player-image downloader
 * ---------------------------------
 * Takes a list of Sofascore player IDs and downloads their avatars from
 * https://img.sofascore.com/api/v1/player/{ID}/image, bundled into a .zip.
 *
 * WHY THIS RUNS IN THE BROWSER (and only on sofascore.com):
 * The image CDN sends `access-control-allow-origin: *`, so JS is allowed to read
 * the bytes -- BUT it 403s any request whose origin/referer isn't sofascore.com
 * (bot protection). A standalone HTML file, a file:// page, or a fetch from any
 * other site all get 403. The same fetch from a tab already on sofascore.com
 * returns 200. So: open https://www.sofascore.com, then paste this script into the
 * DevTools console (see README.md for the exact steps / a bookmarklet variant).
 *
 * The output files are named `{sofascore_id}.webp` because the game reads player
 * avatars from the assets disk at `players/{sofascore_id}.webp`
 * (GamePlayer::getImageUrlAttribute). The CDN actually serves a mix of webp and
 * jpeg, but the extension is cosmetic -- the browser renders by content sniffing,
 * so a jpeg saved as .webp still displays. Extract the zip straight into `players/`.
 *
 * The zip is assembled in-page with a tiny dependency-free STORE-only writer
 * (no compression -- the images are already compressed). We deliberately avoid
 * loading JSZip from a CDN because sofascore.com's CSP would block it.
 */
(function () {
    'use strict';

    // ---- Config (also editable via the panel) ----
    const IMAGE_URL = (id) => `https://img.sofascore.com/api/v1/player/${id}/image`;
    const DEFAULT_CONCURRENCY = 6;   // simultaneous fetches
    const DEFAULT_MAX_PER_ZIP = 1000; // entries per zip; larger lists split into parts
    const FETCH_ATTEMPTS = 2;         // total tries per id on transient/network errors

    // Namespaced so re-running replaces the panel instead of stacking copies and
    // so we never collide with sofascore.com's own globals.
    const NS = '__sofascoreImageDownloader';
    if (window[NS] && window[NS].destroy) {
        window[NS].destroy();
    }

    // ---------------------------------------------------------------------------
    // CRC32 + STORE-only ZIP writer (validated: produces archives macOS/unzip read)
    // ---------------------------------------------------------------------------
    const CRC_TABLE = (() => {
        const t = new Uint32Array(256);
        for (let n = 0; n < 256; n++) {
            let c = n;
            for (let k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
            t[n] = c >>> 0;
        }
        return t;
    })();

    function crc32(buf) {
        let c = 0xFFFFFFFF;
        for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xFF] ^ (c >>> 8);
        return (c ^ 0xFFFFFFFF) >>> 0;
    }

    // files: [{ name: string, data: Uint8Array }] -> Uint8Array (a valid .zip)
    function buildZip(files) {
        const enc = new TextEncoder();
        const parts = [];
        const central = [];
        let offset = 0;
        const u16 = (v) => new Uint8Array([v & 255, (v >> 8) & 255]);
        const u32 = (v) => new Uint8Array([v & 255, (v >> 8) & 255, (v >> 16) & 255, (v >>> 24) & 255]);

        for (const f of files) {
            const name = enc.encode(f.name);
            const crc = crc32(f.data);
            const sz = f.data.length;
            // Local file header + data. Version 20, no flags, method 0 (STORE),
            // no mod time/date (0), sizes known up front.
            const local = [
                u32(0x04034b50), u16(20), u16(0), u16(0), u16(0), u16(0),
                u32(crc), u32(sz), u32(sz), u16(name.length), u16(0), name, f.data,
            ];
            for (const p of local) parts.push(p);
            // Central directory record for this entry.
            central.push([
                u32(0x02014b50), u16(20), u16(20), u16(0), u16(0), u16(0), u16(0),
                u32(crc), u32(sz), u32(sz), u16(name.length), u16(0), u16(0), u16(0), u16(0),
                u32(0), u32(offset), name,
            ]);
            let localLen = 0;
            for (const p of local) localLen += p.length;
            offset += localLen;
        }

        const cdStart = offset;
        let cdLen = 0;
        for (const cd of central) {
            for (const p of cd) { parts.push(p); cdLen += p.length; }
        }
        // End of central directory.
        parts.push(u32(0x06054b50), u16(0), u16(0), u16(files.length), u16(files.length),
            u32(cdLen), u32(cdStart), u16(0));

        let total = 0;
        for (const p of parts) total += p.length;
        const out = new Uint8Array(total);
        let o = 0;
        for (const p of parts) { out.set(p, o); o += p.length; }
        return out;
    }

    // ---------------------------------------------------------------------------
    // Input parsing
    // ---------------------------------------------------------------------------
    // Accepts: a plain list of ids (any separator), OR a pasted sofascore_ids.json
    // ({transfermarkt_id: sofascore_id} map / array) -- we take the numeric values.
    function parseIds(text) {
        const raw = (text || '').trim();
        if (!raw) return [];
        let values = [];
        if (raw[0] === '{' || raw[0] === '[') {
            try {
                const parsed = JSON.parse(raw);
                values = Array.isArray(parsed) ? parsed : Object.values(parsed);
            } catch (e) {
                values = raw.split(/[^0-9]+/); // malformed json -> fall back to digit runs
            }
        } else {
            values = raw.split(/[^0-9]+/);
        }
        const seen = new Set();
        const ids = [];
        for (const v of values) {
            const id = String(v).trim();
            if (/^\d+$/.test(id) && !seen.has(id)) { seen.add(id); ids.push(id); }
        }
        return ids;
    }

    // ---------------------------------------------------------------------------
    // Fetching
    // ---------------------------------------------------------------------------
    // credentials:'omit' is required: the CDN answers with ACAO '*', and a
    // wildcard ACAO + credentials is a CORS failure. We keep the default referrer
    // (sofascore.com) because that's what gets us past the 403 bot check.
    async function fetchImage(id) {
        for (let attempt = 1; attempt <= FETCH_ATTEMPTS; attempt++) {
            try {
                const r = await fetch(IMAGE_URL(id), { credentials: 'omit' });
                if (r.status === 404) return { id, ok: false, reason: '404 (no photo)' };
                if (!r.ok) {
                    if (attempt === FETCH_ATTEMPTS) return { id, ok: false, reason: `HTTP ${r.status}` };
                    continue;
                }
                const data = new Uint8Array(await r.arrayBuffer());
                if (data.length === 0) {
                    if (attempt === FETCH_ATTEMPTS) return { id, ok: false, reason: 'empty body' };
                    continue;
                }
                return { id, ok: true, data };
            } catch (e) {
                if (attempt === FETCH_ATTEMPTS) return { id, ok: false, reason: 'network: ' + ((e && e.message) || e) };
            }
        }
    }

    // Simple fixed-size worker pool.
    async function runPool(ids, concurrency, onResult) {
        let next = 0;
        const results = new Array(ids.length);
        async function worker() {
            while (true) {
                const idx = next++;
                if (idx >= ids.length) return;
                const res = await fetchImage(ids[idx]);
                results[idx] = res;
                onResult(res);
            }
        }
        const workers = [];
        const n = Math.max(1, Math.min(concurrency, ids.length));
        for (let w = 0; w < n; w++) workers.push(worker());
        await Promise.all(workers);
        return results;
    }

    // ---------------------------------------------------------------------------
    // Download helpers
    // ---------------------------------------------------------------------------
    function triggerDownload(bytes, filename) {
        const blob = new Blob([bytes], { type: 'application/zip' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 30000);
    }

    function chunk(arr, size) {
        const out = [];
        for (let i = 0; i < arr.length; i += size) out.push(arr.slice(i, i + size));
        return out;
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
            <strong style="font-size:13px">Sofascore image downloader</strong>
            <button data-role="close" style="background:none;border:none;color:#94a3b8;font-size:18px;line-height:1;cursor:pointer;padding:0 2px">×</button>
        </div>
        <textarea data-role="ids" rows="5" placeholder="Paste Sofascore IDs (any separator), or a sofascore_ids.json map"
            style="width:100%;box-sizing:border-box;resize:vertical;background:#020617;color:#e2e8f0;border:1px solid #334155;border-radius:6px;padding:8px;font:12px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace"></textarea>
        <div style="display:flex;gap:8px;margin:8px 0">
            <label style="flex:1;color:#94a3b8">Concurrency
                <input data-role="concurrency" type="number" min="1" max="20" value="${DEFAULT_CONCURRENCY}"
                    style="width:100%;box-sizing:border-box;background:#020617;color:#e2e8f0;border:1px solid #334155;border-radius:6px;padding:4px 6px">
            </label>
            <label style="flex:1;color:#94a3b8">Max per zip
                <input data-role="maxperzip" type="number" min="1" value="${DEFAULT_MAX_PER_ZIP}"
                    style="width:100%;box-sizing:border-box;background:#020617;color:#e2e8f0;border:1px solid #334155;border-radius:6px;padding:4px 6px">
            </label>
        </div>
        <button data-role="go" style="width:100%;background:#2563eb;color:#fff;border:none;border-radius:6px;padding:9px;font-weight:600;cursor:pointer">Download ZIP</button>
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
        const ids = parseIds($('ids').value);
        if (!ids.length) { log('No valid numeric IDs found.'); return; }

        const concurrency = Math.max(1, parseInt($('concurrency').value, 10) || DEFAULT_CONCURRENCY);
        const maxPerZip = Math.max(1, parseInt($('maxperzip').value, 10) || DEFAULT_MAX_PER_ZIP);

        goBtn.disabled = true;
        goBtn.style.opacity = '.6';
        logEl.textContent = '';
        log(`${ids.length} unique ID(s). Fetching with concurrency ${concurrency}...`);

        let done = 0;
        const results = await runPool(ids, concurrency, () => setProgress(++done, ids.length));

        const ok = results.filter((r) => r && r.ok);
        const failures = results.filter((r) => r && !r.ok);
        log(`Fetched ${ok.length} image(s); ${failures.length} failure(s).`);

        if (!ok.length) {
            log('Nothing to zip.');
            goBtn.disabled = false;
            goBtn.style.opacity = '1';
            return;
        }

        // Every downloaded image is saved as {id}.webp to drop straight into players/.
        const entries = ok.map((r) => ({ name: `${r.id}.webp`, data: r.data }));
        const groups = chunk(entries, maxPerZip);
        const multi = groups.length > 1;
        if (multi) log(`Splitting into ${groups.length} part(s) of up to ${maxPerZip}.`);

        groups.forEach((group, i) => {
            const files = group.slice();
            // Attach the failures manifest to the first part only.
            if (i === 0 && failures.length) {
                const body = failures.map((f) => `${f.id}\t${f.reason}`).join('\n') + '\n';
                files.push({ name: '_failures.txt', data: new TextEncoder().encode(body) });
            }
            const zip = buildZip(files);
            const name = multi
                ? `sofascore-images-part-${String(i + 1).padStart(2, '0')}.zip`
                : 'sofascore-images.zip';
            // Small stagger so Chrome doesn't drop rapid multi-file downloads.
            setTimeout(() => {
                triggerDownload(zip, name);
                log(`Downloaded ${name} (${group.length} image(s), ${(zip.length / 1024).toFixed(0)} KB).`);
            }, i * 400);
        });

        if (failures.length) log(`See _failures.txt for the ${failures.length} skipped ID(s).`);
        goBtn.disabled = false;
        goBtn.style.opacity = '1';
    }

    $('go').addEventListener('click', () => { run().catch((e) => log('Error: ' + ((e && e.message) || e))); });
    $('close').addEventListener('click', () => window[NS].destroy());

    window[NS] = { destroy: () => root.remove(), run };
    log('Ready. Paste IDs above and click Download ZIP.');
})();
