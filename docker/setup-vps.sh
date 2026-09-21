#!/usr/bin/env bash
#
# Prepares a fresh Ubuntu VPS for the Docker deployment: Docker Engine +
# Compose, firewall, brute-force protection, automatic security updates and
# a swap file. Run once, from the repo folder:
#
#   sudo bash docker/setup-vps.sh
#
# It keeps SSH reachable (on whatever port sshd is actually using) before it
# turns the firewall on, so it should not lock you out. Safe to re-run.

set -euo pipefail

log() { printf '\n==> %s\n' "$*"; }

[ "$(id -u)" -eq 0 ] || { echo "Run this with sudo." >&2; exit 1; }

. /etc/os-release
if [ "${ID:-}" != "ubuntu" ]; then
    echo "This script targets Ubuntu (found ${PRETTY_NAME:-unknown})." >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------------- swap
# A safety net for memory spikes (the image build, Chrome during a PDF).
if ! swapon --show | grep -q .; then
    log "Adding a 2 GB swap file"
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

# -------------------------------------------------------------- Docker
if ! command -v docker >/dev/null 2>&1; then
    log "Installing Docker Engine (official Docker apt repository)"
    apt-get update
    apt-get install -y ca-certificates curl
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${UBUNTU_CODENAME:-$VERSION_CODENAME} stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
systemctl enable --now docker

# Let the person who ran sudo use docker without sudo (takes effect on next login).
if [ -n "${SUDO_USER:-}" ] && [ "$SUDO_USER" != "root" ]; then
    usermod -aG docker "$SUDO_USER"
fi

# ------------------------------------------------------------ security
log "Firewall, fail2ban and automatic security updates"
apt-get install -y ufw fail2ban unattended-upgrades

# Allow SSH on the port(s) sshd really listens on, so enabling the firewall
# can't cut you off.
SSH_PORTS="$(sshd -T 2>/dev/null | awk '/^port /{print $2}' | sort -u)"
for port in ${SSH_PORTS:-22}; do
    ufw allow "${port}/tcp"
done
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp
ufw --force enable

systemctl enable --now fail2ban

cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF

log "Done."
cat <<'EOF'

Note: Docker publishes container ports itself, bypassing ufw — that is fine
here because docker-compose.yml only publishes 80 and 443 (MySQL is never
exposed).

Next: log out and back in (so the docker group applies), then
  cp .env.docker.example .env      # edit it
  docker compose up -d --build
EOF
