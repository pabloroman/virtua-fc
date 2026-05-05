# VirtuaFC on Hetzner — Operational Playbook

A copy-pasteable runbook for deploying and operating VirtuaFC on a single
dedicated Hetzner root server with a bolted-on monitoring stack. Written for
an operator who has SSH access and nothing else: every command in this
document is intended to be run literally, in order, top to bottom.

If something here disagrees with the codebase, the codebase wins — open an
issue and update this README.

---

## Contents

1. [Topology](#1-topology)
2. [Prerequisites](#2-prerequisites)
3. [One-time server bring-up](#3-one-time-server-bring-up)
4. [Day-2 operations](#4-day-2-operations)
5. [Monitoring access & on-call](#5-monitoring-access--on-call)
6. [Health checks & smoke tests](#6-health-checks--smoke-tests)
7. [Common incidents → fixes](#7-common-incidents--fixes)
8. [Scaling out](#8-scaling-out)
9. [Disaster-recovery drill](#9-disaster-recovery-drill)
10. [Reference appendix](#10-reference-appendix)
11. [Container image (GHCR)](#11-container-image-ghcr)

---

## 1. Topology

```
                        Internet
                           │
                  ┌────────▼─────────┐
                  │ Traefik          │  :80/:443 (Let's Encrypt HTTP-01)
                  │ (TLS, routing)   │
                  └─┬──────┬────────┬┘
                    │      │        │
            virtuafc.…           grafana.…
                    │              │
                  app           Grafana
                  (Octane/FrankenPHP)
                    │
              scheduler   horizon
              (schedule:work) (queues)
                    │
        ┌───────────┼────────────────┐
        │           │                │
     postgres     redis      Prometheus + Loki + Promtail
                              + node/cAdvisor/pg/redis exporters
```

All services run as containers on a single Docker host. Networks:

- `edge` — Traefik plus everything that takes external traffic (`app`, `grafana`).
- `internal` — everything else (Postgres, Redis, the monitoring scrape targets).

Host paths:

| Path | Purpose |
|---|---|
| `/srv/virtua-fc/compose/` | `docker-compose.yml`, `docker-compose.monitoring.yml` |
| `/srv/virtua-fc/env/.env` | secrets (mode 0600, owned by `deploy`) |
| `/srv/virtua-fc/scripts/` | `compose.sh`, `deploy.sh`, `rollback.sh`, `backup.sh`, `restore.sh`, `smoketest.sh` |
| `/srv/virtua-fc/{prometheus,promtail,grafana}/` | provisioned monitoring config |
| `/srv/virtua-fc/backups/` | local Postgres dumps (rotated) |
| `/var/lib/virtua-fc/{postgres,redis,traefik,prometheus,grafana,loki}` | persistent data volumes |

---

## 2. Prerequisites

Before you start the bring-up, make sure you have:

- **Hetzner root server** (recommended: AX42, Ryzen 7700, 64 GB ECC, 2×1 TB
  NVMe in RAID-1) running Ubuntu 24.04 LTS via `installimage`.
- **SSH key** for the future `deploy` user (the public key, not the private).
- **Domain** with DNS records pointing at the server's IPv4/IPv6:
  - `virtuafc.example.com` → app
  - `grafana.virtuafc.example.com` → Grafana
  - (Add other subdomains later if you choose to expose `horizon.` / `pulse.`
    — they are reachable today via the app at `/horizon` and `/pulse`.)
- **GHCR access** — the GitHub Actions workflow pushes the production image
  to `ghcr.io/<owner>/<repo>` (e.g. `ghcr.io/pabloroman/virtua-fc`). See
  [§11 Container image (GHCR)](#11-container-image-ghcr) for how the URL
  is constructed and how to grant the box pull access.
- **Resend API key** for transactional email.
- **Laravel Nightwatch token** for APM/error tracking.
- **Slack webhook** for alert delivery (optional but recommended).
- **Hetzner Storage Box** (~€4/mo) for offsite backups, plus its SSH user/host.

GitHub repository settings (one-time):

| Where | Name | Value |
|---|---|---|
| Secrets | `HETZNER_HOST` | server IP or hostname |
| Secrets | `HETZNER_USER` | `deploy` |
| Secrets | `HETZNER_SSH_KEY` | the *private* key, ed25519 preferred |
| Variables | `APP_DOMAIN` | e.g. `virtuafc.example.com` |

---

## 3. One-time server bring-up

Two paths, depending on which Hetzner product you're on. Both end at the
same state — `/srv/virtua-fc/...` directories created, `deploy` user with
your SSH key, root + password SSH disabled, ufw enforcing 22/80/443,
Docker installed.

### Path A — Hetzner Cloud (CX23+ / CCX) via cloud-init

Pick this if you're provisioning a Cloud server. Cloud-init runs the YAML
on first boot, so the box is already configured by the time you can SSH in.

> **Sizing:** match/season simulation is CPU-bound. Use **CCX** (dedicated
> vCPU) rather than CX (shared vCPU) for production, or expect noisy-neighbor
> stalls. CX is fine for staging.

1. **Edit the user-data file.** Open `deploy/hetzner/cloud-init.yaml` and
   replace the `ssh-ed25519 AAAA_REPLACE_WITH_YOUR_PUBLIC_KEY …` line with
   your real public key.
2. **Create the server.** In the Hetzner Cloud console, choose Ubuntu 24.04,
   pick CCX23 or larger, and paste the YAML into "User data". Or via CLI:
   ```bash
   hcloud server create \
     --name virtua-fc-1 \
     --type ccx23 \
     --image ubuntu-24.04 \
     --location nbg1 \
     --ssh-key <your-key-name> \
     --user-data-from-file deploy/hetzner/cloud-init.yaml
   ```
3. **Wait ~2 minutes** for cloud-init to finish (apt-update, Docker install,
   etc.). The server is ready when `/etc/virtua-fc-bootstrapped` exists.
4. **SSH in as deploy** (root login is already disabled by cloud-init):
   ```bash
   ssh deploy@<server-ip>
   ```
5. **Skip to §3.3** to push the deploy tree onto the box.

If cloud-init fails for some reason (rare but possible — check
`/var/log/cloud-init-output.log` on the box), fall back to Path B by running
`scripts/bootstrap.sh` manually.

### Path B — Hetzner dedicated root server (AX-line) via installimage

Pick this if you're on a dedicated box. There is no cloud-init on dedicated
hardware — you boot into rescue mode and run installimage, then run our
bootstrap script.

#### B.1 Install Ubuntu 24.04 with RAID-1

In Hetzner Robot, boot the server into rescue mode and run:

```bash
installimage
```

Choose **Ubuntu 24.04**. In the editor, set:

```
SWRAID 1
SWRAIDLEVEL 1
HOSTNAME virtua-fc-1
PART /boot ext3 1G
PART lvm vg0 all
LV vg0 root / ext4 64G
LV vg0 var /var ext4 100G
LV vg0 srv /srv ext4 32G
LV vg0 docker /var/lib/docker ext4 200G
LV vg0 data /var/lib/virtua-fc ext4 all
```

Reboot. From your laptop:

```bash
ssh root@<server-ip>
```

#### B.2 Run the bootstrap script

Copy the bootstrap script to the box and run it. It is idempotent.

```bash
# from your laptop, in the repo root
scp deploy/hetzner/scripts/bootstrap.sh root@<server-ip>:/tmp/bootstrap.sh
ssh root@<server-ip> "DEPLOY_PUBKEY='ssh-ed25519 AAAA…' bash /tmp/bootstrap.sh"
```

What it does (see `scripts/bootstrap.sh` for the source — same end state as
the cloud-init YAML):

- Creates the `deploy` user with passwordless sudo and your SSH key.
- Disables root SSH and password auth in `/etc/ssh/sshd_config`.
- Configures `ufw` (deny incoming except 22/80/443).
- Enables `fail2ban` (sshd jail) and `unattended-upgrades`.
- Installs Docker Engine + compose plugin from Docker's apt repo.
- Sets the timezone to UTC (matches `APP_TIMEZONE`).
- Creates `/srv/virtua-fc/...` and `/var/lib/virtua-fc/...` directory layout.

**Verify SSH lockdown** in a *different* terminal — both must hold:

```bash
ssh root@<server-ip>      # MUST FAIL
ssh deploy@<server-ip>    # MUST SUCCEED (key only)
```

### 3.3 Push deployment files to the server

From the repo root on your laptop:

```bash
rsync -avz --delete \
  deploy/hetzner/compose/        deploy@<server-ip>:/srv/virtua-fc/compose/
rsync -avz --delete \
  deploy/hetzner/scripts/        deploy@<server-ip>:/srv/virtua-fc/scripts/
rsync -avz --delete \
  deploy/hetzner/prometheus/     deploy@<server-ip>:/srv/virtua-fc/prometheus/
rsync -avz --delete \
  deploy/hetzner/promtail/       deploy@<server-ip>:/srv/virtua-fc/promtail/
rsync -avz --delete \
  deploy/hetzner/grafana/        deploy@<server-ip>:/srv/virtua-fc/grafana/
ssh deploy@<server-ip> "chmod +x /srv/virtua-fc/scripts/*.sh"
```

### 3.4 Fill in the environment file

```bash
ssh deploy@<server-ip>
cp /srv/virtua-fc/compose/.env.example /srv/virtua-fc/env/.env
chmod 0600 /srv/virtua-fc/env/.env
nano /srv/virtua-fc/env/.env
```

Generate `APP_KEY` once (it must look like `base64:…`) — you can do it before
the stack is up by running the image directly. Use whatever tag exists in
GHCR right now (see §11): the branch tag for first-time bootstrap, or
`latest` once a `main` build has landed.

```bash
docker run --rm ghcr.io/<owner>/virtua-fc:<tag> \
  php artisan key:generate --show
```

Paste the output into `APP_KEY=` in `.env`. Fill in `DB_PASSWORD`,
`RESEND_API_KEY`, `NIGHTWATCH_TOKEN`, `GRAFANA_ADMIN_PASSWORD`, the
StorageBox values, etc.

### 3.5 First boot

```bash
cd /srv/virtua-fc/compose
/srv/virtua-fc/scripts/compose.sh pull
/srv/virtua-fc/scripts/compose.sh up -d
/srv/virtua-fc/scripts/compose.sh ps
```

Watch the logs until `app` reports `Octane has started`:

```bash
/srv/virtua-fc/scripts/compose.sh logs -f --tail=200 app
```

The entrypoint will run `migrate --force` automatically on the `app` container
(see `docker/entrypoint.sh` — gated by `RUN_MIGRATIONS=true`, set on `app`
only in `compose/docker-compose.yml`). The `horizon` and `scheduler`
containers skip migrations to avoid races.

### 3.6 Seed reference data

```bash
/srv/virtua-fc/scripts/compose.sh exec app php artisan app:seed-reference-data --fresh
```

### 3.7 Open the front door

Visit `https://virtuafc.example.com` — you should see the login page.
Visit `https://grafana.virtuafc.example.com` — log in with
`GRAFANA_ADMIN_USER` / `GRAFANA_ADMIN_PASSWORD`, then change the password.

### 3.8 Schedule nightly backups

```bash
sudo tee /etc/systemd/system/virtua-fc-backup.service >/dev/null <<'EOF'
[Unit]
Description=VirtuaFC nightly Postgres backup
After=docker.service

[Service]
Type=oneshot
User=deploy
ExecStart=/srv/virtua-fc/scripts/backup.sh
EOF

sudo tee /etc/systemd/system/virtua-fc-backup.timer >/dev/null <<'EOF'
[Unit]
Description=Run VirtuaFC backup nightly
[Timer]
OnCalendar=*-*-* 03:00:00
RandomizedDelaySec=15min
Persistent=true
[Install]
WantedBy=timers.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now virtua-fc-backup.timer
sudo systemctl list-timers virtua-fc-backup.timer
```

### 3.9 Smoke test

```bash
/srv/virtua-fc/scripts/smoketest.sh
```

All checks should print `OK:`. If anything fails, jump to
[Common incidents → fixes](#7-common-incidents--fixes).

---

## 4. Day-2 operations

All commands assume you've SSHed in as `deploy@<server-ip>`. The
`compose.sh` wrapper loads the env file and both compose files for you.

### 4.1 Deploy a new version

The normal path is automatic via `.github/workflows/deploy.yml` — push to
`main`, the workflow builds the image, renders the production `.env` from
GitHub environment Secrets/Variables, scps it to `/srv/virtua-fc/env/.env`,
rsyncs the `deploy/hetzner/` tree, then SSHes in and runs `scripts/deploy.sh`
followed by `scripts/smoketest.sh`.

To deploy manually (after the new image has been pushed to GHCR):

```bash
# As deploy@<host>: edit /srv/virtua-fc/env/.env and bump IMAGE_TAG=<sha-or-tag>
sudo -u deploy sed -i "s|^IMAGE_TAG=.*|IMAGE_TAG=<sha-or-tag>|" /srv/virtua-fc/env/.env
/srv/virtua-fc/scripts/deploy.sh
```

`deploy.sh` is the script the CI runs too; it reads `IMAGE_TAG` from the
rendered `.env`. CI records the previous tag in
`/srv/virtua-fc/env/.previous` (read from the running container's image)
before overwriting `.env`, so `rollback.sh` can revert.

### 4.2 Roll back

```bash
/srv/virtua-fc/scripts/rollback.sh                  # back to previous
/srv/virtua-fc/scripts/rollback.sh <explicit-tag>   # to a specific tag
```

### 4.3 Tail logs

`logs.sh` defaults to following app + horizon + scheduler + traefik with
`--tail=200`; pass any compose-logs args to override:

```bash
/srv/virtua-fc/scripts/logs.sh                    # default hot-path services
/srv/virtua-fc/scripts/logs.sh app                # just app
/srv/virtua-fc/scripts/logs.sh --tail=50 horizon  # last 50 lines, no follow
```

For structured queries, use Grafana → Explore → Loki and filter by
`{compose_service="app"}`.

### 4.4 Run an artisan command

`artisan.sh` and `tinker.sh` wrap the compose-exec dance:

```bash
/srv/virtua-fc/scripts/artisan.sh <cmd>
/srv/virtua-fc/scripts/tinker.sh
```

Or one-shot from your laptop:

```bash
ssh deploy@<host> /srv/virtua-fc/scripts/artisan.sh schedule:run
ssh -t deploy@<host> /srv/virtua-fc/scripts/tinker.sh   # -t for REPL TTY
```

Examples:

```bash
# Trigger the daily cleanup on demand
/srv/virtua-fc/scripts/artisan.sh app:cleanup-games

# Fire any due scheduled tasks now
/srv/virtua-fc/scripts/artisan.sh schedule:run

# Retry all failed Horizon jobs
/srv/virtua-fc/scripts/artisan.sh horizon:retry all

# Clear the cache after a config change in /srv/virtua-fc/env/.env
/srv/virtua-fc/scripts/artisan.sh config:cache
```

### 4.5 Inspect Horizon

The dashboard lives at `https://virtuafc.example.com/horizon` and is gated
to `$user->is_admin` (see `app/Providers/HorizonServiceProvider.php`).

CLI fallbacks:

```bash
/srv/virtua-fc/scripts/compose.sh exec app php artisan horizon:status
/srv/virtua-fc/scripts/compose.sh exec horizon php artisan horizon:terminate
/srv/virtua-fc/scripts/compose.sh exec app php artisan horizon:supervisor:list
```

The four supervisors (`gameplay`, `setup`, `mail`, `cleanup`) are configured
in `config/horizon.php`.

### 4.6 Inspect Pulse

`https://virtuafc.example.com/pulse` — same admin gate
(`app/Providers/AppServiceProvider.php` defines the `viewPulse` gate).
Pulse is enabled via `PULSE_ENABLED=true`; its tables live in the same
Postgres as the app.

### 4.7 Database access

CLI on the box — `psql.sh` reads credentials and DB name from the
rendered `.env`:

```bash
/srv/virtua-fc/scripts/psql.sh
/srv/virtua-fc/scripts/psql.sh -c "SELECT count(*) FROM users;"
/srv/virtua-fc/scripts/psql.sh -f /tmp/some-script.sql

# Top 10 slow queries (requires pg_stat_statements; enable once with:
#   CREATE EXTENSION pg_stat_statements;)
/srv/virtua-fc/scripts/psql.sh -c \
  "SELECT round(total_exec_time::numeric, 2) AS total_ms,
          calls,
          round(mean_exec_time::numeric, 2) AS mean_ms,
          left(query, 120) AS q
     FROM pg_stat_statements
    ORDER BY total_exec_time DESC
    LIMIT 10;"

# Database size
/srv/virtua-fc/scripts/psql.sh -c \
  "SELECT pg_size_pretty(pg_database_size(current_database()));"
```

GUI client (TablePlus, pgAdmin, DBeaver, …) — postgres is bound to
`127.0.0.1:5432` on the host (never exposed publicly), so use an SSH
local-forward from your laptop:

```bash
ssh -L 5432:127.0.0.1:5432 deploy@<host>
```

Then connect to `localhost:5432` with the username, password, and database
in `/srv/virtua-fc/env/.env` (or your local copy of the GitHub Variables/
Secrets the workflow renders from).

### 4.7a Grafana access

Grafana is exposed at `https://grafana.${APP_DOMAIN}` via Traefik with
its own Let's Encrypt cert. You'll need an A/AAAA record (or wildcard)
for that subdomain pointing at the Hetzner IP.

Login: `GRAFANA_ADMIN_USER` / `GRAFANA_ADMIN_PASSWORD` from the GitHub
environment (defaults: `admin` and the secret you set). Datasources
(Prometheus, Loki) and dashboards are provisioned from
`/srv/virtua-fc/grafana/{provisioning,dashboards}/`, which is rsync'd
from `deploy/hetzner/grafana/` on every deploy — drop new dashboards
into the repo, push, and they appear after the next workflow run.

### 4.8 Backup now / restore from backup

```bash
# Manual on-demand backup
/srv/virtua-fc/scripts/backup.sh

# List the contents of a dump (no destruction)
/srv/virtua-fc/scripts/restore.sh --dry-run \
  /srv/virtua-fc/backups/virtua_fc-20260105T030000Z.dump

# Destructive restore (asks for confirmation)
/srv/virtua-fc/scripts/restore.sh \
  /srv/virtua-fc/backups/virtua_fc-20260105T030000Z.dump
```

`backup.sh` ships dumps to the Storage Box configured by `STORAGEBOX_*` in
`/srv/virtua-fc/env/.env`, then prunes anything older than
`BACKUP_RETENTION_DAYS` locally.

### 4.9 Rotate secrets

#### `APP_KEY`

`APP_KEY` rotation invalidates **all** existing encrypted sessions. Plan a
brief logout-everyone window:

```bash
# 1. Generate the new key without writing it.
NEW_KEY=$(docker run --rm ghcr.io/<owner>/virtua-fc:latest php artisan key:generate --show)

# 2. Edit /srv/virtua-fc/env/.env, replace APP_KEY.
# 3. Bounce app/horizon/scheduler so they pick up the new key.
/srv/virtua-fc/scripts/compose.sh up -d --force-recreate app horizon scheduler

# 4. Flush sessions (Redis DB 0).
/srv/virtua-fc/scripts/compose.sh exec redis redis-cli -n 0 FLUSHDB
```

#### `DB_PASSWORD`

```bash
# 1. Change it inside Postgres.
/srv/virtua-fc/scripts/compose.sh exec postgres \
  psql -U virtua_fc -d postgres -c "ALTER USER virtua_fc WITH PASSWORD '<new>';"

# 2. Update DB_PASSWORD in /srv/virtua-fc/env/.env.
# 3. Roll the consumers (postgres_exporter and the app services).
/srv/virtua-fc/scripts/compose.sh up -d --force-recreate \
  app horizon scheduler postgres_exporter
```

#### API tokens (Resend, Nightwatch, Slack webhook)

Update `/srv/virtua-fc/env/.env` and `compose.sh up -d --force-recreate app
horizon scheduler grafana`. Tokens are read at boot.

### 4.10 Renew TLS

Traefik renews Let's Encrypt certificates automatically (~30 days before
expiry). Inspect:

```bash
/srv/virtua-fc/scripts/compose.sh logs traefik | grep -i acme
```

If renewal stalls, check that ports 80 and 443 are reachable from the
internet (the HTTP-01 challenge needs port 80) and that DNS still points at
this box.

### 4.11 Update the deployment files themselves

When this `deploy/hetzner/` tree changes (new compose service, new script):

```bash
# from your laptop, repo root, after pulling main
rsync -avz --delete deploy/hetzner/{compose,scripts,prometheus,promtail,grafana}/ \
  deploy@<server-ip>:/srv/virtua-fc/{compose,scripts,prometheus,promtail,grafana}/
ssh deploy@<server-ip> "/srv/virtua-fc/scripts/compose.sh up -d"
```

---

## 5. Monitoring access & on-call

| URL | What | Auth |
|---|---|---|
| `https://virtuafc.example.com/up` | Health probe (Laravel default) | none — used by uptime checks |
| `https://virtuafc.example.com/horizon` | Queue dashboard | logged-in admin (`is_admin`) |
| `https://virtuafc.example.com/pulse` | App perf dashboard | logged-in admin (`is_admin`) |
| `https://grafana.virtuafc.example.com` | Metrics + logs | Grafana basic auth |
| Nightwatch SaaS dashboard | Errors / APM | Nightwatch login |
| Better Stack / external uptime | External probe | external |

### Where to look first when something is slow

1. **Pulse** (`/pulse`) — slow requests, slow queries, slow jobs, exceptions.
   Almost everything is here in one screen.
2. **Horizon** (`/horizon`) — supervisor health, throughput, failed jobs.
3. **Grafana** → *Node Exporter Full* dashboard — host CPU/RAM/disk pressure.
4. **Grafana** → *Postgres / Redis* dashboards — connection counts, lock waits, hit rates.
5. **Grafana → Explore → Loki** — full-text search the app's stdout
   (`{compose_service="app"} |= "<term>"`).
6. **Nightwatch** dashboard — exception clusters and traces.

### Silencing an alert before maintenance

- Grafana → Alerting → Silences → New silence → match the alert by label,
  pick a duration. **Always** set a duration; never indefinite.

### Importing standard dashboards

Grafana → "+" → Import → enter ID → pick the Prometheus data source:

| ID | Dashboard |
|---|---|
| 1860 | Node Exporter Full |
| 14282 | cAdvisor exporter |
| 9628 | PostgreSQL Database |
| 11835 | Redis Dashboard for Prometheus Redis Exporter |
| 17346 | Traefik Official Standalone Dashboard |

Once imported, export each one as JSON and commit it under
`deploy/hetzner/grafana/dashboards/` — the provisioning loader auto-imports
that directory on next start (see `grafana/provisioning/dashboards/dashboards.yml`).

---

## 6. Health checks & smoke tests

```bash
/srv/virtua-fc/scripts/smoketest.sh
```

Wraps:

- All compose services healthy (`docker compose ps`).
- `https://<domain>/up` returns 200.
- Postgres `pg_isready`.
- Redis `PING` → `PONG`.
- `php artisan horizon:status` reports running.
- Grafana `/api/health` returns 200.

Fails loudly on the first red light and exits non-zero — used by both the
GitHub Actions deploy workflow (auto-rollback on failure) and on-call.

---

## 7. Common incidents → fixes

### 502 Bad Gateway from Traefik

1. `compose.sh ps` — is `app` healthy? If not, check entrypoint logs:
   `compose.sh logs --tail=200 app`. Common causes:
   - `APP_KEY` missing or wrong → `Cannot decrypt session` errors. Re-set in `.env`.
   - DB unreachable → `compose.sh exec app php artisan migrate:status` to confirm.
   - Migration failed → see "Migration failed mid-deploy" below.
2. Container healthy but route still 502 → Traefik label drift: `compose.sh
   logs --tail=200 traefik | grep -i error`.

### Horizon dashboard shows a red supervisor

1. `compose.sh exec horizon php artisan horizon:status` — confirms.
2. Inspect failures: `https://<domain>/horizon/failed` or
   `compose.sh exec app php artisan queue:failed`.
3. Fix the underlying error (look at the exception in Pulse or Nightwatch),
   then retry: `compose.sh exec app php artisan horizon:retry all`.
4. If a single supervisor is wedged, restart it: `compose.sh exec horizon
   php artisan horizon:terminate` (compose restarts the container).

### Postgres disk filling

```bash
# Find the heaviest tables
/srv/virtua-fc/scripts/compose.sh exec postgres \
  psql -U virtua_fc -d virtua_fc -c \
  "SELECT relname, pg_size_pretty(pg_total_relation_size(relid)) AS total
     FROM pg_catalog.pg_statio_user_tables
    ORDER BY pg_total_relation_size(relid) DESC
    LIMIT 20;"

# Reclaim space
/srv/virtua-fc/scripts/compose.sh exec postgres \
  psql -U virtua_fc -d virtua_fc -c "VACUUM (VERBOSE, ANALYZE);"
```

If Pulse is the culprit (high cardinality writes), trim its tables:

```bash
/srv/virtua-fc/scripts/compose.sh exec app php artisan pulse:purge
```

### Redis OOM

Sessions and queues live in DB 0 by default; the cache lives in DB 1 (set by
Laravel's `REDIS_CACHE_DB`). Flushing the cache only is safe:

```bash
/srv/virtua-fc/scripts/compose.sh exec redis redis-cli -n 1 FLUSHDB
```

Never `FLUSHALL` on prod — that drops queued jobs and active sessions.

### Migration failed mid-deploy

The new image is already running on `horizon`/`scheduler` (no migrations
there) but `app` exited because `migrate --force` failed. `deploy.sh` will
have detected the smoketest failure and called `rollback.sh`. If it didn't:

```bash
# 1. Roll back to the previous tag.
/srv/virtua-fc/scripts/rollback.sh

# 2. Inspect the migration that failed.
/srv/virtua-fc/scripts/compose.sh exec app php artisan migrate:status

# 3. Hotfix the migration in code, push a new commit, redeploy.
```

If the migration partially applied (DDL committed but the entrypoint died):
you may need `php artisan migrate:rollback --step=1` from a one-shot
container before deploying again.

### Grafana shows "no data" everywhere

1. Prometheus reachable? `compose.sh exec grafana wget -qO- http://prometheus:9090/-/healthy`.
2. Targets up? Open Prometheus directly inside the network:
   `compose.sh exec prometheus wget -qO- 'http://localhost:9090/api/v1/targets' | jq '.data.activeTargets[] | {job, health, lastError}'`.
3. Common cause: a renamed service. Update `prometheus/prometheus.yml`,
   `compose.sh up -d prometheus`.

### TLS renewal stuck

Traefik can't pass the HTTP-01 challenge if port 80 is blocked or DNS
points elsewhere.

```bash
# Confirm port 80 is open and routes to traefik
sudo ufw status
sudo ss -tlnp | grep ':80 '
# Force a reload
/srv/virtua-fc/scripts/compose.sh restart traefik
/srv/virtua-fc/scripts/compose.sh logs traefik | grep -i acme | tail -50
```

If `acme.json` is corrupted, stop traefik, delete the file, restart — Traefik
will re-issue cleanly (subject to Let's Encrypt rate limits, so don't do
this casually).

---

## 8. Scaling out

This single-host setup is intentionally simple. Triggers to leave it:

| Symptom | Action |
|---|---|
| Postgres CPU > 70 % sustained | Move Postgres to Hetzner Cloud or a dedicated DB host. Update `DB_HOST` in `.env`, redeploy. No code changes. |
| App p95 latency > 500 ms after Pulse cleanup | Add a second app host behind a load balancer, share Redis + Postgres. Sticky sessions via Redis make this transparent. |
| Match-simulation throughput becomes the bottleneck | Add a second host running only `horizon` (queue workers); same image, no app-side changes. |
| Pulse writes drown the OLTP DB | Switch `PULSE_DB_CONNECTION` to a separate Postgres connection (the `pulse_pgsql` connection in `config/database.php` is wired for this). |
| Cross-tenant queries (`PLANES-SEAM` markers) finally get refactored | Provision a second Postgres for `pgsql_control`, set `CONTROL_DB_*` in `.env`, enable `DATABASE_PLANES_GUARD_ENABLED=true` to verify the rewrite. |

---

## 9. Disaster-recovery drill

Run quarterly. Goal: prove the latest backup is restorable in under 30 minutes.

```bash
# 1. Pull the latest dump from Storage Box to a scratch host (NOT prod).
DUMP=$(ls -t /srv/virtua-fc/backups/virtua_fc-*.dump | head -1)

# 2. Spin up an isolated postgres container.
docker run -d --name vfc-dr -e POSTGRES_DB=virtua_fc \
  -e POSTGRES_USER=virtua_fc -e POSTGRES_PASSWORD=drill \
  -p 55432:5432 postgres:18-alpine

# 3. Wait, then restore.
sleep 5
docker exec -i vfc-dr pg_restore -U virtua_fc -d virtua_fc < "$DUMP"

# 4. Sanity-check row counts.
docker exec vfc-dr psql -U virtua_fc -d virtua_fc -c \
  "SELECT 'users', count(*) FROM users
   UNION ALL SELECT 'games', count(*) FROM games
   UNION ALL SELECT 'game_matches', count(*) FROM game_matches;"

# 5. Tear down.
docker rm -f vfc-dr
```

Record the date, dump size, and restore wall-clock time in your ops log. If
the restore took materially longer than the previous drill, investigate
(usually growth in the largest table or accumulated bloat).

---

## 10. Reference appendix

### 10.1 Environment variables (production)

Everything in `/srv/virtua-fc/env/.env`. The full template is at
`deploy/hetzner/compose/.env.example`. The most important entries:

| Var | Where it's read | Notes |
|---|---|---|
| `APP_KEY` | Laravel | Generate once; rotation logs everyone out. |
| `APP_DOMAIN` / `APP_URL` | compose + Laravel | Must match DNS + TLS subject. |
| `IMAGE_TAG` / `GHCR_REPO` | compose | Bumped by `deploy.sh`. |
| `DB_PASSWORD` | compose + Laravel | Strong random; rotate via §4.9. |
| `RUN_MIGRATIONS` | `docker/entrypoint.sh` | `true` only on `app`, `false` on `horizon`/`scheduler`. |
| `TRUSTED_PROXIES` | Octane | `*` inside the compose network. |
| `NIGHTWATCH_TOKEN`, `NIGHTWATCH_ENABLED` | `laravel/nightwatch` | APM/error tracking. |
| `PULSE_ENABLED` | `laravel/pulse` | `true` in prod. |
| `LOG_SLACK_WEBHOOK_URL` | `config/logging.php` | Reused by Grafana alerts. |
| `RESEND_API_KEY` | `resend/resend-laravel` | Required if `MAIL_MAILER=resend`. |
| `GRAFANA_ADMIN_PASSWORD` | Grafana | Rotate after first login. |
| `STORAGEBOX_*`, `BACKUP_RETENTION_DAYS` | `scripts/backup.sh` | Offsite copy + local pruning. |

### 10.2 Ports

| Port | Service | Exposed externally? |
|---|---|---|
| 22 | sshd | yes (firewall to your IPs if you can) |
| 80 | Traefik (HTTP, redirects to 443) | yes |
| 443 | Traefik (HTTPS) | yes |
| 8000 | `app` (FrankenPHP) | no (Traefik only) |
| 3000 | Grafana | no (Traefik only, behind `grafana.<domain>`) |
| 9090 | Prometheus | no |
| 3100 | Loki | no |
| 8082 | Traefik metrics endpoint | no |

UFW enforces this — see `scripts/bootstrap.sh`.

### 10.3 Volumes

| Host path | Container | Purpose |
|---|---|---|
| `/var/lib/virtua-fc/postgres` | `postgres:/var/lib/postgresql/data` | Game + control DB |
| `/var/lib/virtua-fc/redis` | `redis:/data` | AOF persistence |
| `/var/lib/virtua-fc/traefik` | `traefik:/letsencrypt` | TLS certs |
| `/var/lib/virtua-fc/prometheus` | `prometheus:/prometheus` | Metrics TSDB (30 d) |
| `/var/lib/virtua-fc/grafana` | `grafana:/var/lib/grafana` | Dashboards, users |
| `/var/lib/virtua-fc/loki` | `loki:/loki` | Log chunks |
| `storage` (named) | `app/horizon/scheduler:/app/storage` | Laravel storage tree (uploads, logs) |

### 10.4 Subdomains

| Subdomain | Service | Set up where |
|---|---|---|
| `virtuafc.example.com` | App (Octane) | `traefik.http.routers.app.rule` in compose |
| `grafana.virtuafc.example.com` | Grafana | `traefik.http.routers.grafana.rule` in monitoring compose |
| (future) `horizon.virtuafc.example.com` | Horizon — currently served at `/horizon` on the app | not configured today |
| (future) `pulse.virtuafc.example.com` | Pulse — currently served at `/pulse` on the app | not configured today |

### 10.5 Where the codebase wires up each piece

| Concern | File |
|---|---|
| Health endpoint `/up` | `bootstrap/app.php` |
| Scheduled tasks | `routes/console.php` |
| Horizon supervisors | `config/horizon.php` |
| Horizon access gate | `app/Providers/HorizonServiceProvider.php` |
| Pulse access gate | `app/Providers/AppServiceProvider.php` (`viewPulse`) |
| DB connections (incl. control plane) | `config/database.php`, `config/database_planes.php` |
| Migration gate | `docker/entrypoint.sh` (reads `RUN_MIGRATIONS`) |
| Octane boot command | `Dockerfile` (`CMD`) |
| Logging channels | `config/logging.php` |

### 10.6 Data migration playbook

We deferred the choice of migration strategy until we know whether prod
data exists today. The options live in the project plan (see the plan file
at the root of the repo or the design doc that triggered this work):

1. **Greenfield re-seed** — `app:seed-reference-data --fresh`.
2. **`pg_dump -Fc` → `pg_restore`** — offline cutover, the default.
3. **Logical replication** — near-zero downtime, requires `wal_level=logical`.
4. **Physical streaming replication** — alternative when logical isn't
   available; same major Postgres version on both sides.
5. **WAL shipping / pgBackRest** — long-term DR posture, layer on regardless.

When the time comes, drop a `migrate.sh` next to the other scripts and add a
"Migration day" section above §3.

---

## 11. Container image (GHCR)

### 11.1 Where the URL comes from

The image is published to the GitHub Container Registry. The URL is built
from three pieces:

```
ghcr.io / <owner> / <repo> : <tag>
```

| Piece | Source | Value for this project |
|---|---|---|
| `ghcr.io` | hard-coded — GHCR's hostname | `ghcr.io` |
| `<owner>` | GitHub user or org that owns the repo | `pabloroman` |
| `<repo>` | the repository name | `virtua-fc` |
| `<tag>` | image tag (commit SHA, branch, or `latest`) | the 12-char SHA, plus `latest` |

So the image you'll be pulling on the server is:

```
ghcr.io/pabloroman/virtua-fc:latest
ghcr.io/pabloroman/virtua-fc:3507182f4b9c        # specific build
```

The `<owner>/<repo>` part comes straight from `${{ github.repository }}` in
`.github/workflows/deploy.yml` — no manual configuration. Whatever the
GitHub repo's slug is, that's the image path.

### 11.2 How the image gets there

The image is published by `.github/workflows/deploy.yml`. There are two
jobs:

- **`build`** — runs on **push to `main`** and on `workflow_dispatch`.
  Feature-branch pushes don't trigger it (CI cost). To build any branch
  on demand — for staging or first-time bootstrap — use Actions → "Run
  workflow" and pick the branch. It runs
  `docker buildx build --target production` against the repo root
  `Dockerfile` and pushes to GHCR with these tags:
  - `:<12-char-sha>` — always, immutable, what `deploy.sh` actually pins to.
  - `:<branch-name>` — always, e.g. `:claude-hetzner-deployment-monitoring-5lyhl`.
  - `:latest` — **only when the build is on `main`** (so feature branches
    can't accidentally move the prod-facing pointer).
- **`deploy`** — runs **on push to `main`** (deploys to production) or on
  `workflow_dispatch` with `deploy=true` (deploys to whichever environment
  you pick — `staging` or `production`). SSHes to the box and runs
  `IMAGE_TAG=<sha> /srv/virtua-fc/scripts/deploy.sh`.

The push step uses the workflow's built-in `GITHUB_TOKEN` with
`packages: write` permission (granted at the top of `deploy.yml`). No PAT
needed for *publishing*.

### 11.3 The very first push (chicken-and-egg)

GHCR has no image until the `build` job runs and succeeds at least once.
A fresh repo's `ghcr.io/<owner>/<repo>:latest` URL returns 404 until then —
which is the order-of-operations problem you'll hit if you try to bootstrap
the server before any CI build has happened.

Because `build` only runs on push-to-`main` (deliberately, to avoid
wasting CI on every feature-branch push), bootstrapping from a feature
branch needs a manual nudge.

Three paths to get the first image, in order of preference:

1. **Manually run the workflow** via Actions → "Build and deploy" → Run
   workflow → pick your branch → leave `deploy` unchecked. The `build`
   job runs and pushes the image with `:<branch-name>` and `:<sha>` tags;
   `deploy` is skipped. This is the easiest path.
2. **Merge to `main`.** Once your branch is reviewed and merged, the
   push-to-main triggers a full build + deploy. Use this only when the
   branch is actually ready to ship — not as a bootstrap shortcut.
3. **Build and push from your laptop** as a last resort:
   ```bash
   echo "$GITHUB_TOKEN" | docker login ghcr.io -u <github-username> --password-stdin
   docker buildx build \
     --target production \
     --platform linux/amd64 \
     --tag ghcr.io/<owner>/virtua-fc:bootstrap \
     --push .
   ```
   Useful when CI is unavailable, but slower and requires a PAT with
   `write:packages`.

After any of these:

1. **The package is created automatically** at
   `https://github.com/users/<owner>/packages/container/package/virtua-fc`
   (or `/orgs/<org>/...` for org-owned repos).
2. **By default the package is private.** You must grant the Hetzner box
   pull access — pick one option in §11.4.

### 11.3.1 Bootstrap order with the new server

For a brand-new server + brand-new GHCR package, the order is:

1. Trigger a build for your bootstrap branch via Actions → "Run
   workflow" (leave `deploy` unchecked). Image lands in GHCR tagged
   `:<branch>` and `:<sha>` — but **not** `:latest` yet.
2. Make the package public *or* set up a PAT for the server (§11.4).
3. Provision the Hetzner server with `cloud-init.yaml` (§3 Path A).
4. SSH in, set `IMAGE_TAG=<branch-name>` in `/srv/virtua-fc/env/.env`,
   first-boot the stack (§3.5).
5. Once the server is up and you're confident, merge to `main`. The next
   `main` push tags `:latest`, runs the deploy job, and from then on
   `deploy.sh` pins to a specific SHA on every CI deploy.

### 11.4 Granting the server pull access

#### Option A — make the package public (recommended for OSS)

GitHub UI → your packages → `virtua-fc` → Package settings → "Change
visibility" → Public. Now `docker pull ghcr.io/<owner>/virtua-fc:<tag>`
works from anywhere with no credentials. Easiest, no rotation burden.

If the repo is private and you'd rather keep the image private too, use B
or C.

#### Option B — Personal Access Token on the server

1. GitHub → Settings → Developer settings → Personal access tokens →
   **Tokens (classic)** → Generate new token. The fine-grained tokens do
   not yet support the GHCR `read:packages` scope at the time of writing,
   so use a classic token.
2. Scope: `read:packages` only. Set an expiry (90 days is reasonable).
3. SSH to the box and log Docker into GHCR once:
   ```bash
   ssh deploy@<server-ip>
   echo "<the-token>" | docker login ghcr.io -u <github-username> --password-stdin
   ```
   The credential is saved to `/home/deploy/.docker/config.json` and survives
   reboots. `docker compose pull` from the deploy scripts will use it
   automatically.
4. Add a calendar reminder for token rotation; re-run `docker login` with the
   new value when the time comes.

#### Option C — repo-scoped deploy token

Same as B but generated under Repo → Settings → Actions → Repository
secrets won't work here (those are CI-only). Use a fine-grained PAT scoped
to a single package once GHCR support lands; until then, B is the path.

### 11.5 Configuring the server-side env

Open `/srv/virtua-fc/env/.env` and confirm:

```bash
GHCR_REPO=pabloroman/virtua-fc      # <owner>/<repo>, no leading "ghcr.io/"
IMAGE_TAG=<branch-name-or-sha>      # bumped automatically by deploy.sh
```

For the very first boot from a feature branch, set `IMAGE_TAG` to the
branch tag (e.g. `claude-hetzner-deployment-monitoring-5lyhl`) or the
12-char SHA. After the branch lands on `main`, you can flip it to
`latest` if you like — though `deploy.sh` overwrites this on every CI run
to pin to an exact SHA, which is what you want for reproducibility.

The compose file builds the full `ghcr.io/${GHCR_REPO}:${IMAGE_TAG}` URL
itself — see the `x-app-image` anchor at the top of
`compose/docker-compose.yml`.

### 11.6 Pulling a specific build manually

To pin to a known-good tag (e.g. while debugging):

```bash
# List the tags GHCR has for the package
curl -fsSL \
  -H "Authorization: Bearer $(echo -n '<github-username>:<token>' | base64)" \
  https://ghcr.io/v2/<owner>/virtua-fc/tags/list | jq

# Pull and run a specific tag
ssh deploy@<server-ip>
IMAGE_TAG=3507182f4b9c /srv/virtua-fc/scripts/deploy.sh
```

`deploy.sh` records the previous tag to `/srv/virtua-fc/env/.previous`, so
`rollback.sh` can revert with no arguments.

### 11.7 Pruning old images on the server

Disk fills slowly with stale tags. The deploy scripts don't prune
automatically — add this to a weekly cron if disk pressure becomes a thing:

```bash
docker image prune -af --filter "until=336h"   # 14 days
```

