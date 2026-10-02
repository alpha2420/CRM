#!/usr/bin/env bash
#
# One-command install of Convera on a fresh Ubuntu server (24.04 or newer):
# Oracle Cloud (including the Always Free Ampere servers), Azure, AWS,
# DigitalOcean or any VPS. Safe to run again: it keeps an existing .env,
# database password and data.
#
#   sudo bash install.sh --domain useconvera.com --www --email you@useconvera.com
#
# Options:
#   --domain NAME      the web address, whose DNS A record points to this server
#   --www              also answer on www.NAME (it redirects to NAME)
#   --email ADDRESS    your email: owner panel, HTTPS certificate notices, backup alerts
#   --repo URL         git repository (default: https://github.com/alpha2420/CRM.git)
#   --branch NAME      branch to deploy (default: main)
#   --dir PATH         where the app lives (default: /var/www/crm)
#   --gemini-key KEY   optional: turns on the AI assistant and voice notes
#   --no-https         skip the free HTTPS certificate (e.g. while DNS is not ready)
#
set -euo pipefail

DOMAIN=""
EMAIL=""
REPO="https://github.com/alpha2420/CRM.git"
BRANCH="main"
APP_DIR="/var/www/crm"
GEMINI_KEY=""
WWW=0
HTTPS=1
PHP_VERSION="8.4"
APP_USER="www-data"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain) DOMAIN="$2"; shift 2 ;;
        --email) EMAIL="$2"; shift 2 ;;
        --repo) REPO="$2"; shift 2 ;;
        --branch) BRANCH="$2"; shift 2 ;;
        --dir) APP_DIR="$2"; shift 2 ;;
        --gemini-key) GEMINI_KEY="$2"; shift 2 ;;
        --www) WWW=1; shift ;;
        --no-https) HTTPS=0; shift ;;
        -h|--help) sed -n '2,23p' "$0"; exit 0 ;;
        *) echo "Unknown option: $1 (see --help)"; exit 1 ;;
    esac
done

step() { echo; echo "▶ $*"; }
note() { echo "  $*"; }
fail() { echo "✗ $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || fail "Run with sudo: sudo bash install.sh --domain … --email …"
[[ -n "$DOMAIN" && -n "$EMAIL" ]] || fail "Both --domain and --email are needed (see --help)."
grep -qi ubuntu /etc/os-release || fail "This installer is for Ubuntu."

export DEBIAN_FRONTEND=noninteractive

# Start/restart a service with systemd, or without it (e.g. inside a test container).
svc() {
    if [[ -d /run/systemd/system ]]; then
        systemctl enable "$2" >/dev/null 2>&1 || true
        systemctl "$1" "$2"
    else
        service "$2" "$1" >/dev/null || service "$2" start >/dev/null
    fi
}

# Write KEY=VALUE into .env, replacing the line if it is there.
set_env() {
    local key="$1" value="$2" file="$APP_DIR/.env"
    if grep -q "^${key}=" "$file"; then
        KEY="$key" VALUE="$value" perl -pi -e 's/^\Q$ENV{KEY}\E=.*/$ENV{KEY}=$ENV{VALUE}/' "$file"
    else
        echo "${key}=${value}" >> "$file"
    fi
    # perl -i writes a new file as root: give it back to the app, readable by nobody else.
    chown "$APP_USER:$APP_USER" "$file"
    chmod 640 "$file"
}
get_env() { grep "^$1=" "$APP_DIR/.env" | head -1 | cut -d= -f2- | sed 's/[[:space:]]*#.*$//' | tr -d '"' ; }
random() { head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c "${1:-32}"; }
as_app() { sudo -u "$APP_USER" -H "$@"; }

# ---------------------------------------------------------------------------
step "Installing server software (nginx, PHP $PHP_VERSION, MySQL, supervisor, certbot)"
apt-get update -q
apt-get install -y -q software-properties-common ca-certificates curl git unzip perl sudo cron \
    nginx mysql-server supervisor certbot python3-certbot-nginx fail2ban unattended-upgrades >/dev/null
if ! apt-cache show "php${PHP_VERSION}-fpm" >/dev/null 2>&1; then
    note "PHP $PHP_VERSION is not in this Ubuntu release: adding the ondrej/php repository"
    add-apt-repository -y ppa:ondrej/php >/dev/null
    apt-get update -q
fi
apt-get install -y -q "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-zip" "php${PHP_VERSION}-gd" "php${PHP_VERSION}-intl" "php${PHP_VERSION}-opcache" >/dev/null
if ! command -v composer >/dev/null; then
    EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    ACTUAL="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [[ "$EXPECTED" == "$ACTUAL" ]] || fail "Composer installer checksum mismatch."
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

# Small servers (1 GB, e.g. Azure B1s) need swap for composer and MySQL.
if [[ "$(awk '/MemTotal/ {print $2}' /proc/meminfo)" -lt 2000000 ]] && ! swapon --show | grep -q .; then
    step "Adding 2 GB of swap (this server has little memory)"
    if { fallocate -l 2G /swapfile 2>/dev/null || dd if=/dev/zero of=/swapfile bs=1M count=2048 status=none; } \
        && chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile; then
        grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    else
        rm -f /swapfile
        note "(swap could not be added here; continuing)"
    fi
fi

# ---------------------------------------------------------------------------
step "PHP settings (OPcache, upload size, no version banner)"
cat > "/etc/php/${PHP_VERSION}/fpm/conf.d/99-crm.ini" <<'INI'
expose_php = Off
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 12M
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
INI
svc restart "php${PHP_VERSION}-fpm"

# ---------------------------------------------------------------------------
step "Database"
svc start mysql
DB_PASSWORD=""
if [[ -f "$APP_DIR/.env" ]]; then DB_PASSWORD="$(get_env DB_PASSWORD)"; fi
if [[ -z "$DB_PASSWORD" ]]; then DB_PASSWORD="$(random 32)"; fi
mysql <<SQL
CREATE DATABASE IF NOT EXISTS crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'crm'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER 'crm'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON crm.* TO 'crm'@'localhost';
FLUSH PRIVILEGES;
SQL
note "database 'crm', user 'crm' (password kept in $APP_DIR/.env)"

# ---------------------------------------------------------------------------
step "Code"
mkdir -p "$(dirname "$APP_DIR")" /var/www/.composer
chown "$APP_USER:$APP_USER" /var/www/.composer
if [[ ! -d "$APP_DIR/.git" ]]; then
    mkdir -p "$APP_DIR" && chown "$APP_USER:$APP_USER" "$APP_DIR"
    as_app git clone --quiet --branch "$BRANCH" "$REPO" "$APP_DIR"
else
    note "already cloned: pulling the latest $BRANCH"
    as_app git -C "$APP_DIR" pull --quiet --ff-only
fi
cd "$APP_DIR"
as_app env COMPOSER_HOME=/var/www/.composer composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --quiet

# ---------------------------------------------------------------------------
step "Web server"
PHP_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
NAMES="$DOMAIN"; WWW_REDIRECT=""
if [[ $WWW -eq 1 ]]; then
    NAMES="$DOMAIN www.$DOMAIN"
    # One address for visitors, search engines and cookies.
    WWW_REDIRECT="if (\$host = www.${DOMAIN}) { return 301 \$scheme://${DOMAIN}\$request_uri; }"
fi
cat > /etc/nginx/sites-available/crm <<NGINX
# Written by deploy/install.sh. HTTPS lines are added by certbot.
server {
    listen 80;
    server_name ${NAMES};
    ${WWW_REDIRECT}
    root ${APP_DIR}/public;
    index index.php;
    charset utf-8;
    client_max_body_size 12M;

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~* \.(css|js|png|webp|svg|ico|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public";
        try_files \$uri =404;
    }

    location = /sw.js {
        add_header Cache-Control "no-cache";
        try_files \$uri =404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 120;
    }

    # .env, .git and other dotfiles are never served.
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
ln -sf /etc/nginx/sites-available/crm /etc/nginx/sites-enabled/crm
rm -f /etc/nginx/sites-enabled/default
nginx -t -q
svc reload nginx 2>/dev/null || svc restart nginx

# ---------------------------------------------------------------------------
step "Firewall: allowing web traffic (80, 443)"
if iptables -S INPUT 2>/dev/null | grep -q -- '-j REJECT'; then
    # Oracle Cloud's Ubuntu images reject everything except SSH, and Oracle
    # advises against ufw there, so open the ports in iptables itself.
    for port in 80 443; do
        if ! iptables -C INPUT -p tcp -m state --state NEW --dport "$port" -j ACCEPT 2>/dev/null; then
            position="$(iptables -L INPUT --line-numbers | awk '/REJECT/ {print $1; exit}')"
            iptables -I INPUT "${position:-1}" -p tcp -m state --state NEW --dport "$port" -j ACCEPT
        fi
    done
    command -v netfilter-persistent >/dev/null && netfilter-persistent save >/dev/null 2>&1 || true
    note "opened in iptables (Oracle Cloud style)"
elif command -v ufw >/dev/null && ufw status 2>/dev/null | grep -q "Status: active"; then
    ufw allow 'Nginx Full' >/dev/null
    note "opened in ufw"
else
    note "no local firewall in the way"
fi
note "Also allow ports 80 and 443 in your cloud's network rules (Oracle: Security List; Azure: Network security group)."

# ---------------------------------------------------------------------------
if [[ $HTTPS -eq 1 ]]; then
    step "Free HTTPS certificate for $NAMES"
    CERT_NAMES=(-d "$DOMAIN"); [[ $WWW -eq 1 ]] && CERT_NAMES+=(-d "www.$DOMAIN")
    if certbot --nginx "${CERT_NAMES[@]}" --non-interactive --agree-tos -m "$EMAIL" --redirect; then
        note "HTTPS is on and renews by itself"
    else
        HTTPS=0
        note "Could not get a certificate yet: check that $NAMES point to this server and ports 80/443 are open,"
        note "then run this installer again."
    fi
fi

# ---------------------------------------------------------------------------
step "Settings (.env)"
SCHEME="https"; [[ $HTTPS -eq 1 ]] || SCHEME="http"
if [[ ! -f .env ]]; then
    as_app cp .env.production.example .env
    chmod 640 .env
    set_env APP_KEY ""
    set_env BACKUP_ARCHIVE_PASSWORD "$(random 40)"
    # Until email is set up (see the end), sign-up must not wait for a verification email.
    set_env MAIL_MAILER log
    set_env CRM_REQUIRE_EMAIL_VERIFICATION false
    # Backups stay on this server until an off-site bucket is added.
    set_env BACKUP_DISKS backups
    set_env MAIL_FROM_ADDRESS "no-reply@${DOMAIN}"
    note "created .env from .env.production.example"
else
    note "keeping the existing .env"
fi
set_env APP_URL "${SCHEME}://${DOMAIN}"
set_env DB_PASSWORD "$DB_PASSWORD"
set_env PLATFORM_ADMIN_EMAILS "$EMAIL"
set_env BACKUP_NOTIFY_EMAIL "$EMAIL"
[[ -z "$(get_env CRM_SUPPORT_EMAIL)" || "$(get_env CRM_SUPPORT_EMAIL)" == "support@example.com" ]] && set_env CRM_SUPPORT_EMAIL "$EMAIL"
set_env CRM_SCHEDULER_RUNS_QUEUE false
set_env DB_QUEUE_RETRY_AFTER 330
set_env CRM_FORCE_HTTPS "$([[ $HTTPS -eq 1 ]] && echo true || echo false)"
set_env SESSION_SECURE_COOKIE "$([[ $HTTPS -eq 1 ]] && echo true || echo false)"
[[ -n "$GEMINI_KEY" ]] && set_env GEMINI_API_KEY "$GEMINI_KEY"
[[ -n "$(get_env APP_KEY)" ]] || as_app php artisan key:generate --force --quiet
if [[ -z "$(get_env VAPID_PUBLIC_KEY)" ]]; then
    # Keys for phone notifications.
    as_app php artisan crm:vapid-keys | grep -E '^VAPID_(PUBLIC|PRIVATE)_KEY=' | while IFS='=' read -r key value; do set_env "$key" "$value"; done
fi
chown "$APP_USER:$APP_USER" .env

step "Database tables and caches"
as_app php artisan migrate --force --no-interaction
as_app php artisan optimize --quiet
chown -R "$APP_USER:$APP_USER" storage bootstrap/cache

# ---------------------------------------------------------------------------
step "Background workers and the scheduler"
cat > /etc/supervisor/conf.d/crm-queue.conf <<CONF
; Written by deploy/install.sh: two queue workers (WhatsApp, files, AI, broadcasts).
[program:crm-queue]
process_name=%(program_name)s_%(process_num)02d
command=php ${APP_DIR}/artisan queue:work database --sleep=3 --tries=3 --timeout=300 --max-time=3600
directory=${APP_DIR}
user=${APP_USER}
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=330
redirect_stderr=true
stdout_logfile=${APP_DIR}/storage/logs/queue.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
CONF
svc start supervisor
supervisorctl reread >/dev/null && supervisorctl update >/dev/null || true
cat > /etc/cron.d/crm <<CRON
# Written by deploy/install.sh: the only cron entry the app needs.
* * * * * ${APP_USER} cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/crm
svc start cron
# Blocks repeated failed SSH logins.
svc restart fail2ban 2>/dev/null || note "(fail2ban will start with the next reboot)"

# ---------------------------------------------------------------------------
step "First backup and a health check"
as_app php artisan backup:run --only-db --disable-notifications --quiet || note "(backup failed: see storage/logs)"
as_app php artisan schedule:run --quiet || true
as_app php artisan crm:health || true

URL="$([[ $HTTPS -eq 1 ]] && echo https || echo http)://${DOMAIN}"
cat <<DONE

✓ Installed. Open ${URL}/register and create your own workspace with ${EMAIL}
  (that address opens the owner panel at ${URL}/platform).

Next steps (each is explained in docs/DEPLOYMENT.md):
  1. Email: put your SMTP details in ${APP_DIR}/.env (MAIL_*), set MAIL_MAILER=smtp
     and CRM_REQUIRE_EMAIL_VERIFICATION=true, then run: sudo bash ${APP_DIR}/deploy/deploy.sh
  2. AI: set GEMINI_API_KEY in .env if you did not pass --gemini-key.
  3. Off-site backups: add a bucket (AWS_*) and set BACKUP_DISKS=backups,s3.
  4. Alerts: add a free uptime monitor for ${URL}/up and, optionally, SENTRY_LARAVEL_DSN.
  5. Check it from your computer:  bash deploy/smoke-test.sh ${URL}
DONE
