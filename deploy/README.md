# VirtuaFC — Kubernetes deployment (Hetzner)

This directory contains a Helm chart for deploying VirtuaFC to Kubernetes,
targeted at **Hetzner Cloud** but portable to any cluster.

```
deploy/
├── README.md                       # this file
└── helm/
    └── virtua-fc/
        ├── Chart.yaml
        ├── values.yaml             # defaults
        ├── values-hetzner.yaml.example
        ├── secret.example.yaml
        └── templates/              # web, horizon, scheduler, redis, ingress, migrate job
```

## Architecture

| Workload     | Replicas      | Notes                                                              |
|--------------|---------------|--------------------------------------------------------------------|
| `web`        | 2 (HPA → 6)   | FrankenPHP/Octane, serves HTTP. Probes `/up`.                      |
| `horizon`    | 1             | Runs all queue supervisors (`gameplay`, `setup`, `mail`, `cleanup`).|
| `scheduler`  | 1             | `php artisan schedule:work` — runs cron tasks from `routes/console.php`.|
| `redis`      | 1 StatefulSet | In-cluster Redis 7 with AOF persistence on a Hetzner volume.       |
| `migrate`    | Job           | Helm pre-install/pre-upgrade hook. Runs `migrate --force` and warms caches.|
| Postgres     | external      | Use Hetzner Managed Postgres (or Neon/Supabase/self-hosted).       |

## Why these choices

- **Single Horizon deployment** rather than per-queue. The supervisor groups
  defined in `config/horizon.php` already provide per-queue process limits and
  auto-balancing within one Horizon process. Splitting into 4 deployments adds
  complexity without much gain at the current scale. Revisit if `gameplay`
  starves the others.
- **In-cluster Redis** with AOF + a PVC. Hetzner doesn't offer managed Redis;
  a single StatefulSet with persistent volume is fine for a single-node game.
  Move to Upstash or Hetzner-hosted Redis on a dedicated VM if you ever need HA.
- **External managed Postgres.** Backups, point-in-time recovery, and minor
  version upgrades are not worth babysitting. Hetzner launched managed Postgres
  in 2025; Neon/Supabase work too.
- **Scheduler as a Deployment** (not a CronJob). `schedule:work` is a long-lived
  process that knows about Laravel's schedule definitions. Avoids drift between
  cluster cron and `routes/console.php`.

## Prerequisites

On your Hetzner Cloud account:
1. A **Managed Kubernetes** cluster (3 small nodes, e.g. CX22 or CPX21).
2. A **Managed Postgres** instance (or external alternative).
3. A **Hetzner Load Balancer** in front of the ingress controller (provisioned
   automatically by `hcloud-cloud-controller-manager` when an ingress Service
   of type `LoadBalancer` is created).
4. DNS — point your apex/subdomain to the Hetzner LB.

In the cluster, install once:
- **`hcloud-csi-driver`** — provides the `hcloud-volumes` StorageClass for PVCs.
- **An ingress controller** — Traefik or ingress-nginx.
- **`cert-manager`** with a Let's Encrypt `ClusterIssuer` named `letsencrypt-prod`.
- **(Optional) `kube-prometheus-stack`** for metrics + Grafana.

## First-time setup

```bash
# 1. Create namespace
kubectl create namespace virtua-fc

# 2. Create the application Secret (see helm/virtua-fc/secret.example.yaml)
APP_KEY=$(php artisan key:generate --show)
kubectl create secret generic virtua-fc-app -n virtua-fc \
  --from-literal=APP_KEY="$APP_KEY" \
  --from-literal=DB_PASSWORD="$DB_PASSWORD" \
  --from-literal=REDIS_PASSWORD="" \
  --from-literal=RESEND_KEY="$RESEND_KEY"

# 3. Copy and fill in your values file
cp helm/virtua-fc/values-hetzner.yaml.example helm/virtua-fc/values-hetzner.yaml
$EDITOR helm/virtua-fc/values-hetzner.yaml

# 4. Build & push the production image (CI normally does this)
docker build --target production -t ghcr.io/pabloroman/virtua-fc:$(git rev-parse --short HEAD) .
docker push ghcr.io/pabloroman/virtua-fc:$(git rev-parse --short HEAD)

# 5. Install
helm upgrade --install virtua-fc ./helm/virtua-fc \
  --namespace virtua-fc \
  --values helm/virtua-fc/values-hetzner.yaml \
  --set image.tag=$(git rev-parse --short HEAD)
```

## Subsequent deploys

```bash
helm upgrade virtua-fc ./helm/virtua-fc \
  --namespace virtua-fc \
  --values helm/virtua-fc/values-hetzner.yaml \
  --set image.tag=$NEW_TAG
```

This will:
1. Run the `migrate` Job as a `pre-upgrade` hook (migrations + cache warm).
2. Roll the `web` Deployment (zero downtime — `maxUnavailable: 0`).
3. Recreate the `horizon` and `scheduler` Deployments (clean restart).

## Migration cutover from Laravel Cloud

1. **Lower DNS TTL** to 60s, ~24h before cutover.
2. **Provision** the cluster, Postgres, secrets, ingress, TLS — verify with a
   staging hostname.
3. **Test deploy** end-to-end against the staging hostname. Run smoke tests:
   - `kubectl exec deploy/virtua-fc-web -- php artisan app:simulate-match`
   - Hit `/up`, log in, simulate a match through the UI.
4. **Cutover window:**
   - On Laravel Cloud: pause queue workers, put app in maintenance mode.
   - Take a final `pg_dump` from Laravel Cloud Postgres → restore into Hetzner
     Managed Postgres.
   - Update `helm` values with the production hostname, `helm upgrade`.
   - Flip DNS to the Hetzner LB IP.
   - Bring out of maintenance, verify Horizon dashboard at `/horizon`.
5. **Keep Laravel Cloud running 24–48h** as a rollback option, then tear down.

## Cost estimate (Hetzner, EU)

| Item                                  | Monthly        |
|---------------------------------------|----------------|
| Managed K8s control plane             | free           |
| 3× CPX21 worker nodes (3 vCPU, 4 GB)  | ~€24           |
| Managed Postgres (small, 50 GB)       | ~€20           |
| Load Balancer (LB11)                  | ~€5            |
| 10 GB Redis volume                    | ~€0.50         |
| Egress (first 20 TB free per node)    | €0             |
| **Total**                             | **~€50/month** |

Compare to Laravel Cloud's per-resource pricing.

## Troubleshooting

- **Pods stuck in `Init`/`CrashLoopBackOff`** — entrypoint blocks on Postgres/Redis
  reachability. Check `kubectl logs <pod>` for "Waiting for PostgreSQL...".
- **Migrate Job fails** — `kubectl logs job/virtua-fc-migrate-<rev>`. Fix the
  underlying issue, then `helm rollback` or re-run with corrected values.
- **Horizon dashboard 403** — check the gate in `app/Providers/HorizonServiceProvider.php`;
  by default Horizon is locked down outside `local`.
- **N+1 queries appearing in Pulse** — see `CLAUDE.md` "Backend Performance"
  section. Add eager loading.

## What's not in scope here

- **Object storage.** The app uses local `storage/` for read-only assets that
  ship in the image. If you start storing user uploads, switch `FILESYSTEM_DISK`
  to S3-compatible (Hetzner Object Storage, Cloudflare R2, etc.) and set the
  AWS_* env vars.
- **CDN.** Static assets in `public/build/` are served by Octane. Putting
  Cloudflare in front (free tier) cuts egress and improves TTFB globally.
- **Multi-region / HA.** Single-region single-AZ to start. Postgres backups
  are managed, Redis is single-replica with AOF — that's enough for a game
  where occasional minutes of downtime during a node failure are acceptable.
