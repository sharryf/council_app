#!/usr/bin/env bash
#
# One-time server setup for Oceancy on a fresh Ubuntu 24.04 Droplet.
#
# Installs nginx, PHP 8.3, MySQL 8, Node 22 and the libraries headless
# Chrome needs (every PDF export uses Spatie Browsershot + Puppeteer),
# then locks the server down: firewall, fail2ban, key-only SSH and
# automatic security updates. There is no queue worker or cron job to set
# up — the app has no queued jobs and no scheduled tasks.
#
# Order of operations for a new server:
#   1. Create the Droplet (Ubuntu 24.04, 2 GiB, Singapore or Bangalore,
#      SSH key login) and point the domain's A record at its IP.
#   2. Copy this file to the server and run it as root:
#        scp deploy/provision.sh root@SERVER_IP:
#        ssh root@SERVER_IP
#        DOMAIN=council.example.com bash provision.sh
#   3. Get the HTTPS certificate (DNS must already point here):
#        certbot --nginx -d council.example.com -m you@example.com --agree-tos --redirect
#   4. Log in as the `deploy` user and run deploy.sh (see that file).
#   5. Run deploy/check-pdf.sh as root to confirm PDF generation works.
#
# Safe to re-run: each step checks before changing anything.

set -euo pipefail

DOMAIN="${DOMAIN:?Set DOMAIN, e.g. DOMAIN=council.example.com bash provision.sh}"
APP_USER="${APP_USER:-deploy}"
APP_DIR="${APP_DIR:-/var/www/council-app}"
DB_NAME="${DB_NAME:-council_app}"
DB_USER="${DB_USER:-council_app}"
PHP_VERSION="8.3"
PUPPETEER_CACHE_DIR="/opt/puppeteer"

log() { printf '\n==> %s\n' "$*"; }

[ "$(id -u)" -eq 0 ] || { echo "Run this as root." >&2; exit 1; }

. /etc/os-release
if [ "${ID:-}" != "ubuntu" ] || [ "${VERSION_ID:-}" != "24.04" ]; then
    echo "This script targets Ubuntu 24.04 (found ${PRETTY_NAME:-unknown})." >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------------- swap
# A safety net for the occasional memory spike (Chrome + composer).
if ! swapon --show | grep -q .; then
    log "Adding a 1 GB swap file"
    fallocate -l 1G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

# ------------------------------------------------------------ packages
log "Installing system packages"
apt-get update
apt-get -y upgrade
apt-get install -y \
    ca-certificates curl gnupg git unzip openssl \
    ufw fail2ban unattended-upgrades \
    nginx mysql-server certbot python3-certbot-nginx composer \
    "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-zip" "php${PHP_VERSION}-gd" "php${PHP_VERSION}-intl" \
    "php${PHP_VERSION}-bcmath"

# Libraries headless Chrome needs to start, plus fonts so PDFs render text.
log "Installing Chrome libraries and fonts"
apt-get install -y \
    libasound2t64 libatk-bridge2.0-0t64 libatk1.0-0t64 libcairo2 libcups2t64 \
    libdbus-1-3 libdrm2 libexpat1 libgbm1 libglib2.0-0t64 libnspr4 libnss3 \
    libpango-1.0-0 libx11-6 libxcb1 libxcomposite1 libxdamage1 libxext6 \
    libxfixes3 libxkbcommon0 libxrandr2 xdg-utils \
    fonts-liberation fonts-dejavu-core fonts-noto-core fonts-noto-color-emoji

# Node 22 (Vite 8 needs a recent Node; Ubuntu's own package is too old).
if ! command -v node >/dev/null 2>&1 || ! node -v | grep -q '^v22'; then
    log "Installing Node 22"
    install -d -m 0755 /etc/apt/keyrings
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor --yes -o /etc/apt/keyrings/nodesource.gpg
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list
    apt-get update
    apt-get install -y nodejs
fi

# --------------------------------------------------------- deploy user
log "Setting up the '${APP_USER}' user"
if ! id "$APP_USER" >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" "$APP_USER"
fi
usermod -aG www-data "$APP_USER"

if [ -s /root/.ssh/authorized_keys ]; then
    install -d -m 700 -o "$APP_USER" -g "$APP_USER" "/home/${APP_USER}/.ssh"
    install -m 600 -o "$APP_USER" -g "$APP_USER" /root/.ssh/authorized_keys "/home/${APP_USER}/.ssh/authorized_keys"
fi

# The only thing the deploy user may do as root: reload PHP after a deploy.
SUDOERS="/etc/sudoers.d/${APP_USER}-deploy"
echo "${APP_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl reload php${PHP_VERSION}-fpm" > "$SUDOERS"
chmod 440 "$SUDOERS"
visudo -cf "$SUDOERS" >/dev/null

# Group-owned app folder: the deploy user writes it, PHP-FPM (www-data) reads
# it and writes storage/. setgid keeps new files in the www-data group.
install -d -m 2775 -o "$APP_USER" -g www-data "$APP_DIR"

# Chrome is installed once, system-wide, so both the deploy user (npm ci
# downloads it) and www-data (PHP-FPM launches it) use the same copy.
install -d -m 0755 -o "$APP_USER" -g www-data "$PUPPETEER_CACHE_DIR"
# Chrome writes profile/crash data under $HOME, which is /var/www for www-data.
install -d -m 0755 -o www-data -g www-data /var/www/.cache /var/www/.config

# ---------------------------------------------------------------- PHP
log "Configuring PHP"
for sapi in fpm cli; do
    cat > "/etc/php/${PHP_VERSION}/${sapi}/conf.d/99-council.ini" <<'EOF'
upload_max_filesize = 50M
post_max_size = 55M
memory_limit = 512M
max_execution_time = 120
EOF
done

POOL="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
if ! grep -q 'PUPPETEER_CACHE_DIR' "$POOL"; then
    cat >> "$POOL" <<EOF

; Headless Chrome for the PDF exports lives in one shared folder.
env[PUPPETEER_CACHE_DIR] = ${PUPPETEER_CACHE_DIR}
env[HOME] = /var/www
env[PATH] = /usr/local/bin:/usr/bin:/bin
EOF
fi

# Files PHP-FPM creates (logs, cache) must stay writable by the deploy user.
install -d /etc/systemd/system/php${PHP_VERSION}-fpm.service.d
printf '[Service]\nUMask=0002\n' > "/etc/systemd/system/php${PHP_VERSION}-fpm.service.d/umask.conf"
systemctl daemon-reload
systemctl enable "php${PHP_VERSION}-fpm"
systemctl restart "php${PHP_VERSION}-fpm"

# -------------------------------------------------------------- MySQL
log "Configuring MySQL"
# Trim MySQL's memory so Chrome has room on a 2 GiB server.
cat > /etc/mysql/mysql.conf.d/99-council.cnf <<'EOF'
[mysqld]
performance_schema = OFF
innodb_buffer_pool_size = 256M
max_connections = 50
EOF
systemctl enable mysql
systemctl restart mysql

CREDS="/home/${APP_USER}/.council-credentials"
if [ ! -f "$CREDS" ]; then
    DB_PASSWORD="$(openssl rand -base64 24 | tr -d '=+/')"
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
    install -m 600 -o "$APP_USER" -g "$APP_USER" /dev/null "$CREDS"
    printf 'DB_DATABASE=%s\nDB_USERNAME=%s\nDB_PASSWORD=%s\n' "$DB_NAME" "$DB_USER" "$DB_PASSWORD" > "$CREDS"
fi

# -------------------------------------------------------------- nginx
log "Configuring nginx"
cat > /etc/nginx/sites-available/council-app <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    root ${APP_DIR}/public;
    index index.php;
    client_max_body_size 50M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
ln -sf /etc/nginx/sites-available/council-app /etc/nginx/sites-enabled/council-app
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable nginx
systemctl reload nginx

# ------------------------------------------------------------ security
log "Locking the server down"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

systemctl enable --now fail2ban

cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF

# Key-only SSH — but only if a key is actually installed, so this can never
# lock you out. The 00- prefix makes sshd read it before other drop-ins
# (the first value it sees wins).
if [ -s /root/.ssh/authorized_keys ]; then
    cat > /etc/ssh/sshd_config.d/00-council.conf <<'EOF'
PasswordAuthentication no
PermitRootLogin prohibit-password
EOF
    sshd -t
    systemctl reload ssh
fi

log "Done."
cat <<EOF

Next:
  1. HTTPS (DNS for ${DOMAIN} must point at this server first):
       certbot --nginx -d ${DOMAIN} -m you@example.com --agree-tos --redirect
  2. Log in as ${APP_USER} and run deploy.sh. The database password was saved
     to /home/${APP_USER}/.council-credentials (only ${APP_USER} can read it).
  3. Run deploy/check-pdf.sh as root once the app is deployed.
EOF
