# Subdomain migration: `app.virtuafc.com` → `beta.virtuafc.com`

Move the live beta off `app.virtuafc.com` and onto `beta.virtuafc.com`, freeing `app.` for the future production deploy on a different provider. Zero downtime, no forced re-login, reversible.

## Strategy & rationale

- **Final state during beta:** `beta.virtuafc.com` serves the app on Laravel Cloud. `app.virtuafc.com` 302-redirects to `beta.` at the Cloudflare edge.
- **Why beta moves, not production:** `app.` is the canonical product URL. Migrating users twice (now to `play.`, later back to `app.`) is worse than migrating once now while the user base is small.
- **Why a 302, not a 301:** when production ships you'll re-point `app.` to the new infra. 301s are cached by browsers indefinitely and are very hard to claw back. 302s expire on the next request.
- **Why redirect at Cloudflare, not in Laravel:** keeps the redirect independent of Laravel Cloud, costs no origin requests, and lets you pull `app.` off Laravel Cloud entirely (so the new provider can claim it without conflict in Laravel Cloud's custom-domain registry).
- **Session preservation across the redirect:** set `SESSION_DOMAIN=.virtuafc.com` (leading dot) **before** cutover. Active users get a parent-domain cookie on their next response, so when the redirect lands them on `beta.`, they're still logged in.

## Pre-flight audit (already done — recorded for the record)

Verified clean in this codebase:

- No hardcoded `app.virtuafc.com` in `config/`, `app/`, `resources/`, `routes/`.
- No OAuth/Socialite providers (`config/services.php` is mailer + Slack only).
- No Sanctum SPA stateful auth (no `config/sanctum.php`).
- No PWA manifest or service worker in `public/`.
- `config/cors.php` already lists only `virtuafc.com` and `www.virtuafc.com` (apex), not `app.`.
- `SESSION_DOMAIN` defaults to `null` (request-host scoped) — fine to widen.

If any of the above changes before cutover (e.g. you add Google login), update the relevant provider's allowed origins/callbacks **before** starting Phase 3.

---

## Phase 0 — Decide and prepare (no production changes)

1. Pick the cutover window. Pick a low-traffic hour; the actual switch takes seconds but you want headroom for verification.
2. Confirm Cloudflare account access with permission to add DNS records, custom hostnames on Laravel Cloud, and a **Bulk Redirect** or **Single Redirect** rule (Rules → Redirect Rules in the Cloudflare dashboard).
3. Confirm Laravel Cloud account access with permission to add a custom domain to the environment currently serving `app.virtuafc.com`.
4. Snapshot current state for rollback:
   - Current Cloudflare DNS record for `app` (type, target, proxied flag).
   - Current `APP_URL`, `SESSION_DOMAIN`, `BETA_FEEDBACK_URL` in Laravel Cloud env.
   - Current Laravel Cloud custom-domain configuration for `app.virtuafc.com`.

## Phase 1 — Widen the session cookie (deploy, then wait)

Goal: make existing logins survive the eventual host switch.

1. In Laravel Cloud (current environment serving `app.`), set:
   ```
   SESSION_DOMAIN=.virtuafc.com
   ```
   Note the **leading dot** — this is what makes the cookie travel to all `*.virtuafc.com` subdomains.
2. Deploy. No code changes; this is env-only.
3. Verify in a real browser session:
   - Log in on `app.virtuafc.com`.
   - Open DevTools → Application → Cookies. The session cookie's `Domain` column should now read `.virtuafc.com` (not `app.virtuafc.com`).
4. **Wait at least one full session lifetime** (`SESSION_LIFETIME=120` minutes by default — give it 24 hours of organic traffic to be safe). Active users get the parent-domain cookie on their next response. Anyone whose old `app.`-scoped cookie is still in their browser will simply re-login on `beta.` after cutover (acceptable tail risk).

> **Why not skip this and just live with re-logins?** You can. But this single env change makes the cutover invisible to logged-in users, which is the entire point of "no downtime."

## Phase 2 — Stand up `beta.virtuafc.com` alongside `app.`

Both hosts will serve the same Laravel Cloud environment for as long as you want. This is the "blue/green" safety net.

1. **Cloudflare DNS:** add `beta` as a CNAME pointing to the same Laravel Cloud target as `app.`. **Set the proxy status to DNS-only (grey cloud) initially** — Laravel Cloud needs to see the unproxied record to issue its certificate. Re-enable proxy (orange cloud) after the cert is live.
2. **Laravel Cloud:** add `beta.virtuafc.com` as a custom domain on the same environment. Wait for the SSL cert to provision (usually a few minutes).
3. Once Laravel Cloud reports the cert as active, flip the Cloudflare record back to **Proxied (orange cloud)**.
4. **Verify `beta.` end-to-end** while `app.` is still primary:
   - `curl -I https://beta.virtuafc.com/` returns `200`.
   - Browser: visit `beta.virtuafc.com`, log in fresh, exercise: squad selection, simulate a match, transfer market, season summary download (the canvas-image `virtuafc.com` footer text is not host-bound, fine).
   - DevTools → cookies on `beta.` should show `Domain=.virtuafc.com` — confirming the Phase 1 cookie scope works for the new host.
   - Trigger a transactional email (if any exist on the live beta) and confirm absolute links resolve. Note: queued mail uses `APP_URL`, which still points at `app.` — that's fine for now, the app. host still serves the app, and Phase 4 fixes this.
5. **Do not advertise `beta.` yet.** It exists for verification only.

## Phase 3 — Cutover: redirect `app.` → `beta.`

This is the only step with user-visible effect. Execution time: <1 minute.

1. **Cloudflare → Rules → Redirect Rules → Create rule.** Use a Single Redirect (or a Bulk Redirect list, if you have several):
   - **When incoming requests match:** `(http.host eq "app.virtuafc.com")`
   - **Then:** Dynamic redirect
     - **Expression:** `concat("https://beta.virtuafc.com", http.request.uri.path, if(len(http.request.uri.query) > 0, concat("?", http.request.uri.query), ""))`
     - **Status code:** `302` (Found — temporary)
     - **Preserve query string:** handled by the expression above.
2. Deploy the rule.
3. Verify from a clean shell:
   ```bash
   curl -sI https://app.virtuafc.com/squad | head -5
   # Expect: HTTP/2 302 ... location: https://beta.virtuafc.com/squad
   curl -sI 'https://app.virtuafc.com/foo?bar=baz' | grep -i location
   # Expect: location: https://beta.virtuafc.com/foo?bar=baz
   ```
4. Verify in a logged-in browser: navigate to `app.virtuafc.com/squad`. You should land on `beta.virtuafc.com/squad` **still logged in**. If you see a login screen, the Phase 1 cookie widening hasn't taken effect for that session — clear cookies and re-test with a fresh login.
5. **Smoke check:** simulate a match, advance the date, check finances. Watch error reporting (Slack channel via `LOG_SLACK_WEBHOOK_URL`) for 5–10 minutes for any host-specific 4xx/5xx spikes.

> **Why leave `app.` as a custom domain on Laravel Cloud during the redirect?** You don't strictly have to — once the Cloudflare proxy is intercepting and 302-ing every request, the origin never sees `app.` traffic. But keeping it attached costs nothing, and removing it prematurely makes rollback (Phase 6) slower. Remove it in Phase 5.

## Phase 4 — Update host-aware app config

Now that `beta.` is the canonical app host, point the app at it.

1. In Laravel Cloud env, set:
   ```
   APP_URL=https://beta.virtuafc.com
   ```
   This affects: queued job URL generation, mail absolute links, `route(..., absolute: true)` from CLI / queue contexts, password reset emails.
2. Deploy. Verify a queued email (e.g. password reset) renders links to `beta.`.
3. (Optional but recommended) Update `config/cors.php` if you anticipate any browser fetch from `beta.` to apex. Today the file allows only `virtuafc.com` / `www.virtuafc.com` and there are no API calls between hosts, so leave it alone unless something breaks.

## Phase 5 — Cleanup (after 1–2 weeks of stable operation)

1. Remove `app.virtuafc.com` as a custom domain from the Laravel Cloud environment. (Cloudflare's edge redirect means the origin sees no `app.` traffic anyway, and removing it from Laravel Cloud frees the hostname so the future production provider can claim it cleanly.)
2. Update any external references that still point to `app.`:
   - `BETA_FEEDBACK_URL` if applicable.
   - Cosmetic copy in `resources/js/season-summary.js` and `resources/js/modules/canvas-image.js` mentions `virtuafc.com` (apex), not `app.` — no change needed.
   - Social profiles, marketing site, README badges (none in this repo today).
3. Keep the Cloudflare 302 rule **in place indefinitely** — it's the safety net for old bookmarks/links until production launches and the rule is repurposed.

## Phase 6 — Rollback (if anything goes wrong)

In order, fastest first:

1. **Roll back the redirect:** disable the Cloudflare Redirect Rule from Phase 3. `app.` immediately resumes serving the app from Laravel Cloud (the custom domain is still attached).
2. **Roll back `APP_URL`:** set `APP_URL=https://app.virtuafc.com` in Laravel Cloud env, redeploy. Mail / queued job links go back to `app.`.
3. **Leave `SESSION_DOMAIN=.virtuafc.com`:** harmless on a single-subdomain setup; rolling it back forces all logged-in users to re-login. Only revert if it caused an actual incident.
4. **Tear down `beta.`:** remove the Cloudflare CNAME and the Laravel Cloud custom domain. (Only if you want a fully clean reset.)

## Phase 7 — When production ships on the new provider

This is the eventual reason you did all this:

1. On the new production provider, configure `app.virtuafc.com` as the custom domain. Provision SSL.
2. **Remove the Cloudflare 302 redirect rule** from Phase 3.
3. Update Cloudflare DNS for `app` to point at the new provider. (If using proxy, keep orange cloud.)
4. Decide what `beta.` does going forward: either point it at a separate beta/staging environment, or add a new Cloudflare 302 from `beta.` → `app.` for a transition window so people who bookmarked `beta.` end up on production.
5. In the **production** environment's env config, set `SESSION_DOMAIN=.virtuafc.com` so the same parent-domain cookie pattern works. (Sessions don't carry across providers — different `APP_KEY`, different session store — so users will re-login regardless. That's expected for the prod cutover.)

---

## Quick reference: what changes where

| Change | Where | Phase |
|---|---|---|
| `SESSION_DOMAIN=.virtuafc.com` | Laravel Cloud env | 1 |
| `beta` CNAME → Laravel Cloud | Cloudflare DNS | 2 |
| `beta.virtuafc.com` custom domain | Laravel Cloud | 2 |
| 302 `app.*` → `beta.*` | Cloudflare Redirect Rule | 3 |
| `APP_URL=https://beta.virtuafc.com` | Laravel Cloud env | 4 |
| Remove `app.` custom domain | Laravel Cloud | 5 |
| Remove 302 rule, repoint `app.` | Cloudflare + new provider | 7 |

## Verification checklist (run during Phase 3)

- [ ] `curl -I https://app.virtuafc.com/` returns `302` with `Location: https://beta.virtuafc.com/`
- [ ] Path and query string preserved across redirect
- [ ] Logged-in browser session on `app.` lands on `beta.` still authenticated
- [ ] Match simulation, date advancement, transfer market all functional on `beta.`
- [ ] Slack error channel quiet for 10+ minutes after cutover
- [ ] Password-reset email (after Phase 4) contains `https://beta.virtuafc.com/...` links
