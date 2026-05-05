#!/usr/bin/env bash
# One-time server bring-up for a fresh Hetzner Ubuntu 24.04 root server.
# Run as root immediately after Hetzner installimage:
#
#   curl -fsSL https://raw.githubusercontent.com/<repo>/<branch>/deploy/hetzner/scripts/bootstrap.sh | sudo DEPLOY_PUBKEY="ssh-ed25519 AAAA…" bash
#
# Or, after cloning the repo onto the box:
#
#   sudo DEPLOY_PUBKEY="ssh-ed25519 AAAA…" bash deploy/hetzner/scripts/bootstrap.sh
#
# Idempotent — safe to re-run.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "bootstrap.sh must run as root" >&2
    exit 1
fi

if [ -z "${DEPLOY_PUBKEY:-}" ]; then
    echo "DEPLOY_PUBKEY env var required (the SSH public key for the deploy user)" >&2
    exit 1
fi

DEPLOY_USER="${DEPLOY_USER:-deploy}"
SSH_PORT="${SSH_PORT:-22}"

echo "==> Updating apt and installing base packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y
apt-get install -y \
    ca-certificates curl gnupg lsb-release \
    ufw fail2ban unattended-upgrades \
    htop iotop jq rsync git tmux \
    postgresql-client-common postgresql-client

echo "==> Creating deploy user '$DEPLOY_USER'"
if ! id -u "$DEPLOY_USER" >/dev/null 2>&1; then
    useradd -m -s /bin/bash -G sudo "$DEPLOY_USER"
fi
install -d -m 0700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" /home/"$DEPLOY_USER"/.ssh
echo "$DEPLOY_PUBKEY" > /home/"$DEPLOY_USER"/.ssh/authorized_keys
chmod 0600 /home/"$DEPLOY_USER"/.ssh/authorized_keys
chown "$DEPLOY_USER":"$DEPLOY_USER" /home/"$DEPLOY_USER"/.ssh/authorized_keys

# Passwordless sudo for the deploy user (so CI deploys don't need a TTY).
echo "$DEPLOY_USER ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/90-"$DEPLOY_USER"
chmod 0440 /etc/sudoers.d/90-"$DEPLOY_USER"

echo "==> Hardening sshd"
sed -ri 's/^#?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
sed -ri 's/^#?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
sed -ri 's/^#?ChallengeResponseAuthentication.*/ChallengeResponseAuthentication no/' /etc/ssh/sshd_config
sed -ri 's/^#?KbdInteractiveAuthentication.*/KbdInteractiveAuthentication no/' /etc/ssh/sshd_config
systemctl reload ssh || systemctl reload sshd

echo "==> Configuring ufw"
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow "$SSH_PORT"/tcp comment 'ssh'
ufw allow 80/tcp comment 'http'
ufw allow 443/tcp comment 'https'
ufw --force enable

echo "==> Configuring fail2ban (sshd jail)"
cat >/etc/fail2ban/jail.d/sshd.local <<'EOF'
[sshd]
enabled = true
maxretry = 5
findtime = 10m
bantime = 1h
EOF
systemctl enable --now fail2ban

echo "==> Enabling unattended-upgrades"
dpkg-reconfigure -f noninteractive unattended-upgrades

echo "==> Installing Docker Engine"
install -m 0755 -d /etc/apt/keyrings
if [ ! -f /etc/apt/keyrings/docker.asc ]; then
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
fi
. /etc/os-release
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $VERSION_CODENAME stable" \
    > /etc/apt/sources.list.d/docker.list
apt-get update -y
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
usermod -aG docker "$DEPLOY_USER"

echo "==> Time sync"
timedatectl set-timezone UTC
systemctl enable --now systemd-timesyncd

echo "==> Creating directory layout"
install -d -m 0755 -o "$DEPLOY_USER" -g "$DEPLOY_USER" \
    /srv/virtua-fc \
    /srv/virtua-fc/compose \
    /srv/virtua-fc/env \
    /srv/virtua-fc/backups \
    /srv/virtua-fc/prometheus \
    /srv/virtua-fc/promtail \
    /srv/virtua-fc/grafana \
    /srv/virtua-fc/grafana/provisioning \
    /srv/virtua-fc/grafana/dashboards \
    /srv/virtua-fc/scripts

# Data volumes — owned by root, mounted into containers with their own UIDs.
install -d -m 0755 \
    /var/lib/virtua-fc \
    /var/lib/virtua-fc/postgres \
    /var/lib/virtua-fc/redis \
    /var/lib/virtua-fc/traefik \
    /var/lib/virtua-fc/prometheus \
    /var/lib/virtua-fc/grafana \
    /var/lib/virtua-fc/loki

# Grafana container runs as UID 472.
chown -R 472:472 /var/lib/virtua-fc/grafana

# Traefik acme.json must be 0600.
touch /var/lib/virtua-fc/traefik/acme.json
chmod 0600 /var/lib/virtua-fc/traefik/acme.json

cat <<EOF

====================================================
Bootstrap complete.

Next steps (as the deploy user, NOT root):

  1. Copy the deploy/hetzner/ tree into /srv/virtua-fc/
       rsync -a deploy/hetzner/ deploy@<host>:/srv/virtua-fc/

  2. Copy compose/.env.example to /srv/virtua-fc/env/.env (mode 0600)
     and fill in secrets (APP_KEY, DB_PASSWORD, NIGHTWATCH_TOKEN, …).

  3. Test SSH lockdown: 'ssh root@<host>' must fail.
     'ssh deploy@<host>' must succeed (key-only).

  4. First-boot the stack:
       cd /srv/virtua-fc/compose
       docker compose --env-file /srv/virtua-fc/env/.env \\
         -f docker-compose.yml -f docker-compose.monitoring.yml up -d

See deploy/hetzner/README.md for the full playbook.
====================================================
EOF
