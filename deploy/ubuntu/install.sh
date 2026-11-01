#!/usr/bin/env bash
#
# Diwan ERP - complete install on one Ubuntu server (DigitalOcean or any other).
#
#   sudo bash -c "$(curl -fsSL https://github.com/turkialawlqy/Diwan-ERP/releases/latest/download/install-server.sh)"
#
# Installs Nginx, PHP-FPM, MySQL, Redis and Supervisor, pulls the code from GitHub
# (the public repository directly, or with a deploy key for a private copy), builds the interface, writes .env, creates the database
# and the platform administrator, and issues a wildcard SSL certificate (*.domain) through DigitalOcean DNS.
# Safe to run again: never recreates the database nor replaces an existing .env.
#
# DIWAN_DRY_RUN=1 prints the commands without running them.

set -Eeuo pipefail

APP_DIR=${DIWAN_APP_DIR:-/var/www/diwan}
APP_USER=www-data
APP_HOME=/var/www
CONF=/root/.diwan-install.conf
SECRETS=/root/diwan-secrets.txt
DRY_RUN=${DIWAN_DRY_RUN:-0}
# Fingerprint of this script as started: step 5 may pull a newer copy over it (bash keeps reading the old one).
SELF_SUM=$(sha256sum "${BASH_SOURCE[0]}" | cut -d' ' -f1)

# ------------------------------------------------------------------ helpers
c_info=$'\e[36m'; c_ok=$'\e[32m'; c_warn=$'\e[33m'; c_err=$'\e[31m'; c_off=$'\e[0m'
step() { echo; echo "${c_info}==> $*${c_off}"; }
ok()   { echo "${c_ok}✓ $*${c_off}"; }
warn() { echo "${c_warn}! $*${c_off}"; }
die()  { echo "${c_err}✗ $*${c_off}" >&2; exit 1; }
run()  { if [[ $DRY_RUN == 1 ]]; then echo "  [dry-run] $*"; else "$@"; fi; }

# Ubuntu's Software Updater (aptd) or unattended-upgrades may hold the package
# manager: wait for it instead of stopping (up to 15 minutes), then retry.
APT_LOCKS=(/var/lib/dpkg/lock-frontend /var/lib/dpkg/lock /var/lib/apt/lists/lock /var/cache/apt/archives/lock)
apt_busy() {
    local f
    for f in "${APT_LOCKS[@]}"; do
        [[ -e $f ]] || continue
        flock -n "$f" true 2> /dev/null || return 0
    done
    return 1
}
apt_wait() {
    local waited=0
    apt_busy || return 0
    echo "${c_warn}! Another program is updating the system (Software Updater). Waiting for it to finish...${c_off}"
    while apt_busy; do
        if (( waited >= 900 )); then
            echo
            die "The system is still busy updating after 15 minutes. Close Software Updater (or restart the computer), then run the same command again."
        fi
        sleep 10; waited=$((waited + 10)); printf '.'
    done
    echo
}
apt_get() {
    local try
    for try in 1 2 3; do
        apt_wait
        if apt-get -o DPkg::Lock::Timeout=600 "$@"; then return 0; fi
        (( try < 3 )) && sleep 5
    done
    return 1
}
as_app() { run sudo -u "$APP_USER" -H env HOME="$APP_HOME" COMPOSER_HOME="$APP_HOME/.composer" "$@"; }
artisan() { as_app php "$APP_DIR/artisan" "$@"; }
# PhpSpreadsheet 1.x (Excel import) declares PHP < 8.5; every other package supports 8.5.
composer_flags() {
    if php -r 'exit(PHP_VERSION_ID >= 80500 ? 0 : 1);' 2>/dev/null; then
        echo "  (PHP $(php -r 'echo PHP_VERSION;'): skipping the PHP version limit of the Excel library; every other library supports it)" >&2
        echo "--ignore-platform-req=php"
    fi
}
trap 'echo "${c_err}✗ Setup stopped at line $LINENO. Send the last messages above to ask for help, then run the script again (it is safe to repeat).${c_off}" >&2' ERR

# Sets KEY=VALUE in a dotenv file (quoted when needed; values may hold any character).
set_env() {
    local file=$1 key=$2 value=$3
    if [[ ! $value =~ ^[A-Za-z0-9_./:@,*+-]*$ ]]; then
        if [[ $value != *"'"* ]]; then
            value="'$value'" # single quotes: taken literally (no $ expansion)
        else
            value=${value//\\/\\\\}; value=${value//\"/\\\"}; value=${value//\$/\\\$}
            value="\"$value\""
        fi
    fi
    [[ $DRY_RUN == 1 ]] && { echo "  [dry-run] $key=$value"; return; }
    KEY="$key" VAL="$value" awk 'BEGIN { k = ENVIRON["KEY"]; v = ENVIRON["VAL"]; done = 0 }
        $0 ~ "^" k "=" { if (!done) print k "=" v; done = 1; next } { print }
        END { if (!done) print k "=" v }' "$file" > "$file.tmp"
    cat "$file.tmp" > "$file" # in place: keeps the owner and mode (the app must still read .env)
    rm -f "$file.tmp"
}

# Fills __PLACEHOLDERS__ of a template from the environment variables of the same name.
render() {
    local text; text=$(cat "$1")
    local var
    for var in DOMAIN APP_DIR PHP_SOCK MODE LISTEN SSL_LINES HTTP_BLOCK; do
        text=${text//__${var}__/${!var:-}}
    done
    printf '%s\n' "$text"
}

ask() { # ask VAR "question" "default"
    local var=$1 prompt=$2 default=${3:-} answer
    [[ -n ${!var:-} ]] && return
    read -r -p "$prompt${default:+ [$default]}: " answer
    printf -v "$var" '%s' "${answer:-$default}"
}

save_conf() {
    [[ $DRY_RUN == 1 ]] && return
    ( umask 077
    { for v in DOMAIN ADMIN_EMAIL REPO BRANCH DO_TOKEN MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_FROM DB_PASS ADMIN_CREATED; do
        printf '%s=%q\n' "$v" "${!v:-}"; done; } > "$CONF" )
}

[[ $EUID -eq 0 ]] || die "Run the script as root: sudo bash install.sh"
grep -qi ubuntu /etc/os-release || warn "This script is written for Ubuntu; your system may need different commands."

# ------------------------------------------------------------------ questions
# shellcheck disable=SC1090
[[ -f $CONF ]] && source "$CONF" && ok "Using your previous answers from $CONF"
# DIWAN_DOMAIN switches the domain (e.g. a test domain now, the real one once its DNS is live).
[[ -n ${DIWAN_DOMAIN:-} ]] && DOMAIN=$DIWAN_DOMAIN

SERVER_IP=$(curl -fsS4 --max-time 5 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')
step "Setup questions"
[[ -n ${DOMAIN:-} ]] || echo "To try it before your domain is connected, use: ${SERVER_IP}.sslip.io (works at once with subdomains, without SSL)"
ask DOMAIN "Main domain (e.g. diwan.example.com)"
DOMAIN=${DOMAIN#http*://}; DOMAIN=${DOMAIN%%/*}; DOMAIN=${DOMAIN,,}
[[ $DOMAIN =~ ^[a-z0-9.-]+\.[a-z]{2,}$ ]] || die "Invalid domain: $DOMAIN"
ask ADMIN_EMAIL "Platform administrator email"
# The public repository needs no key; a private fork (git@github.com:...) gets a deploy key below.
ask REPO "Code repository" "https://github.com/turkialawlqy/Diwan-ERP.git"
DEFAULT_BRANCH=$(git ls-remote --symref "$REPO" HEAD 2> /dev/null | awk '/^ref:/ {sub("refs/heads/", "", $2); print $2; exit}' || true)
ask BRANCH "Branch" "${DEFAULT_BRANCH:-main}"
if [[ -z ${DO_TOKEN+x} ]]; then
    echo "DigitalOcean API token (Write scope) to issue the SSL certificate for the domain and all its subdomains."
    echo "Leave it empty to install on HTTP now and add the certificate later."
    read -r -s -p "DO API token: " DO_TOKEN; echo
fi
if [[ -z ${MAIL_HOST+x} ]]; then
    echo "SMTP mail to send the system's emails (leave empty now; you can add it later in .env)."
    read -r -p "SMTP host: " MAIL_HOST
    if [[ -n $MAIL_HOST ]]; then
        read -r -p "SMTP port [587]: " MAIL_PORT; MAIL_PORT=${MAIL_PORT:-587}
        read -r -p "SMTP username: " MAIL_USERNAME
        read -r -s -p "SMTP password: " MAIL_PASSWORD; echo
        read -r -p "Sender address [no-reply@$DOMAIN]: " MAIL_FROM; MAIL_FROM=${MAIL_FROM:-no-reply@$DOMAIN}
    fi
fi
DB_PASS=${DB_PASS:-$(openssl rand -hex 16)}
save_conf

TEST_DOMAIN=0
[[ $DOMAIN =~ \.(sslip|nip)\.io$ ]] && TEST_DOMAIN=1
ok "Domain: $DOMAIN - server address: $SERVER_IP"

# ------------------------------------------------------------------ 1. system
step "1/11 System update, swap and firewall"
export DEBIAN_FRONTEND=noninteractive
run apt_get update -y
run apt_get upgrade -y
if ! swapon --show | grep -q /swapfile; then
    run fallocate -l 2G /swapfile
    run chmod 600 /swapfile
    run mkswap /swapfile
    run swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || run sh -c 'echo "/swapfile none swap sw 0 0" >> /etc/fstab'
    run sysctl -w vm.swappiness=20
    ok "Added 2GB of swap (a 512MB server is not enough to build on its own)."
fi
run timedatectl set-timezone Asia/Riyadh || true

# ------------------------------------------------------------------ 2. packages
step "2/11 Packages: Nginx, PHP, MySQL, Redis and Supervisor"
run apt_get install -y nginx mysql-server redis-server supervisor git unzip curl ufw certbot \
    php-fpm php-cli php-mysql php-mbstring php-intl php-xml php-zip php-bcmath php-gd php-curl php-redis \
    composer nodejs npm
[[ -n $DO_TOKEN ]] && run apt_get install -y python3-certbot-dns-digitalocean
run ufw allow OpenSSH
run ufw allow 'Nginx Full'
run ufw --force enable

PHP_V=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo 8.3)
PHP_SOCK=/run/php/php${PHP_V}-fpm.sock
ok "PHP $PHP_V"

# Small server tuning: PHP-FPM on demand, upload limits.
run tee "/etc/php/$PHP_V/fpm/conf.d/99-diwan.ini" > /dev/null <<'INI'
memory_limit = 256M
upload_max_filesize = 20M
post_max_size = 25M
max_execution_time = 120
opcache.enable = 1
opcache.memory_consumption = 64
INI
POOL=/etc/php/$PHP_V/fpm/pool.d/www.conf
if [[ -f $POOL ]]; then
    run sed -i -E 's/^pm = .*/pm = ondemand/; s/^pm.max_children = .*/pm.max_children = 4/; s/^;?pm.process_idle_timeout = .*/pm.process_idle_timeout = 20s/' "$POOL"
fi
run systemctl restart "php$PHP_V-fpm"

# ------------------------------------------------------------------ 3. MySQL & Redis
step "3/11 Database and Redis"
run tee /etc/mysql/mysql.conf.d/zz-diwan.cnf > /dev/null <<'CNF'
[mysqld]
performance_schema = OFF
innodb_buffer_pool_size = 128M
innodb_log_buffer_size = 8M
max_connections = 40
key_buffer_size = 8M
tmp_table_size = 16M
max_heap_table_size = 16M
CNF
run systemctl restart mysql
run mysql -e "CREATE DATABASE IF NOT EXISTS diwan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'diwan'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER 'diwan'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON diwan.* TO 'diwan'@'localhost'; FLUSH PRIVILEGES;"
if [[ -f /etc/redis/redis.conf ]]; then
    run sed -i -E 's/^#? ?maxmemory .*/maxmemory 96mb/; s/^#? ?maxmemory-policy .*/maxmemory-policy noeviction/; s/^bind .*/bind 127.0.0.1 ::1/' /etc/redis/redis.conf
    run systemctl restart redis-server
fi
ok "Database diwan is ready; Redis listens on this server only."

# ------------------------------------------------------------------ 4. deploy key
step "4/11 Repository access"
run mkdir -p "$APP_HOME/.ssh"
run chown -R "$APP_USER:$APP_USER" "$APP_HOME"
if [[ $REPO == git@* ]] && [[ ! -f $APP_HOME/.ssh/id_ed25519 ]]; then
    as_app ssh-keygen -t ed25519 -N '' -C "diwan@$DOMAIN" -f "$APP_HOME/.ssh/id_ed25519" -q
fi
[[ $REPO == git@* ]] && as_app sh -c "ssh-keyscan -t ed25519 github.com >> $APP_HOME/.ssh/known_hosts 2>/dev/null; sort -u -o $APP_HOME/.ssh/known_hosts $APP_HOME/.ssh/known_hosts"
until [[ $DRY_RUN == 1 ]] || sudo -u "$APP_USER" -H git ls-remote "$REPO" HEAD > /dev/null 2>&1; do
    [[ $REPO == git@* ]] || die "Cannot reach $REPO. Check the address and the server's internet connection."
    echo
    echo "${c_warn}Add this key in GitHub > the repository > Settings > Deploy keys > Add deploy key (leave Allow write access unchecked):${c_off}"
    echo
    cat "$APP_HOME/.ssh/id_ed25519.pub"
    echo
    read -r -p "After adding it, press Enter to continue... "
done
ok "Repository access works."

# ------------------------------------------------------------------ 5. code
step "5/11 Code, libraries and interface build (several minutes)"
# The front-end build needs ~1GB: Node gets a larger heap, backed by the swap file.
[[ $DRY_RUN == 1 ]] || swapon --show | grep -q /swapfile || swapon /swapfile || warn "Swap is off; the interface build may run out of memory."
if [[ ! -d $APP_DIR/.git ]]; then
    run mkdir -p "$APP_DIR"
    run chown "$APP_USER:$APP_USER" "$APP_DIR"
    as_app git clone --branch "$BRANCH" "$REPO" "$APP_DIR"
else
    as_app git -C "$APP_DIR" fetch origin "$BRANCH"
    as_app git -C "$APP_DIR" checkout "$BRANCH"
    as_app git -C "$APP_DIR" pull --ff-only origin "$BRANCH"
fi
# An older copy of this script (run from /root, or this very file before the pull) hands over to the one just pulled.
if [[ $DRY_RUN != 1 && -z ${DIWAN_REEXEC:-} && $(sha256sum "$APP_DIR/deploy/ubuntu/install.sh" | cut -d' ' -f1) != "$SELF_SUM" ]]; then
    ok "A newer copy of this installer came with the code; continuing with it."
    exec env DIWAN_REEXEC=1 bash "$APP_DIR/deploy/ubuntu/install.sh"
fi
mapfile -t COMPOSER_FLAGS < <(composer_flags)
as_app composer install --working-dir="$APP_DIR" --no-dev --optimize-autoloader --no-interaction "${COMPOSER_FLAGS[@]}"
# The front-end build takes minutes on a small server: only when its sources changed since the last build.
BUILD_STAMP=$APP_DIR/public/build/.source-hash
# As the repository's owner: git refuses a repository owned by another user ("dubious ownership").
BUILD_HASH=$(sudo -u "$APP_USER" -H git -C "$APP_DIR" ls-tree -r HEAD -- resources/js resources/css package.json package-lock.json vite.config.js tailwind.config.js postcss.config.js | sha256sum | cut -d' ' -f1)
if [[ -f $APP_DIR/public/build/manifest.json && -f $BUILD_STAMP && $(cat "$BUILD_STAMP") == "$BUILD_HASH" ]]; then
    ok "The interface is already built and unchanged; skipping the build."
else
    as_app env NODE_OPTIONS=--max-old-space-size=1536 sh -c "cd $APP_DIR && npm ci --no-audit --no-fund && npm run build && rm -rf node_modules"
    [[ $DRY_RUN == 1 ]] || as_app sh -c "echo $BUILD_HASH > $BUILD_STAMP"
fi
ok "Code and interface are ready."

# ------------------------------------------------------------------ 6. .env
step "6/11 Settings file .env"
ENV_FILE=$APP_DIR/.env
# HTTPS only once a certificate exists for the domain (set again after step 9).
HTTPS=false
[[ -d /etc/letsencrypt/live/$DOMAIN ]] && HTTPS=true
SCHEME=$([[ $HTTPS == true ]] && echo https || echo http)
if [[ ! -f $ENV_FILE ]]; then
    run cp "$APP_DIR/.env.production.example" "$ENV_FILE"
    NEW_ENV=1
fi
set_env "$ENV_FILE" APP_URL "$SCHEME://$DOMAIN"
set_env "$ENV_FILE" APP_DOMAIN "$DOMAIN"
set_env "$ENV_FILE" TENANCY_ALLOWED_HOSTS "$DOMAIN"
set_env "$ENV_FILE" FORCE_HTTPS "$HTTPS"
set_env "$ENV_FILE" SESSION_SECURE_COOKIE "$HTTPS"
set_env "$ENV_FILE" DB_DATABASE diwan
set_env "$ENV_FILE" DB_USERNAME diwan
set_env "$ENV_FILE" DB_PASSWORD "$DB_PASS"
set_env "$ENV_FILE" BACKUP_OFFSITE_DISK ""
set_env "$ENV_FILE" MAIL_FROM_ADDRESS "${MAIL_FROM:-no-reply@$DOMAIN}"
if [[ -n ${MAIL_HOST:-} ]]; then
    set_env "$ENV_FILE" MAIL_MAILER smtp
    set_env "$ENV_FILE" MAIL_HOST "$MAIL_HOST"
    set_env "$ENV_FILE" MAIL_PORT "${MAIL_PORT:-587}"
    set_env "$ENV_FILE" MAIL_USERNAME "$MAIL_USERNAME"
    set_env "$ENV_FILE" MAIL_PASSWORD "$MAIL_PASSWORD"
else
    set_env "$ENV_FILE" MAIL_MAILER log
fi
if [[ ${NEW_ENV:-0} == 1 ]] || ! grep -q '^BACKUP_PASSWORD=..' "$ENV_FILE" 2>/dev/null; then
    set_env "$ENV_FILE" BACKUP_PASSWORD "$(openssl rand -hex 24)"
fi
run chown "$APP_USER:$APP_USER" "$ENV_FILE"
run chmod 640 "$ENV_FILE"
grep -q '^APP_KEY=base64' "$ENV_FILE" 2>/dev/null || artisan key:generate --force
if [[ $DRY_RUN != 1 ]]; then
    ( umask 077
    {
        echo "# Diwan secrets - copy them somewhere safe outside the server, then delete this file."
        echo "# APP_KEY decrypts the e-invoicing device keys; losing it means linking the devices again."
        grep -E '^(APP_KEY|DB_PASSWORD|BACKUP_PASSWORD)=' "$ENV_FILE"
    } > "$SECRETS" )
fi
ok ".env is ready; secrets saved in $SECRETS"

# ------------------------------------------------------------------ 7. database
step "7/11 Database tables"
artisan migrate --force --seed
[[ -L $APP_DIR/public/storage ]] || artisan storage:link
# Trailing slashes: storage is a link to the shared folder once releases are in use.
run chown -R "$APP_USER:$APP_USER" "$APP_DIR/storage/" "$APP_DIR/bootstrap/cache/"
run chmod -R ug+rwX "$APP_DIR/storage/" "$APP_DIR/bootstrap/cache/"
ok "Database is ready."

# ------------------------------------------------------------------ 8-9. nginx & SSL
step "8/11 Web server (Nginx)"
write_nginx() {
    # IPv6 listeners only where the kernel has IPv6 (some droplets are IPv4 only).
    local v6_80='' v6_443='' v6_443h=''
    if [[ -f /proc/net/if_inet6 ]]; then
        v6_80=$'\n    listen [::]:80;'; v6_443=$'\n    listen [::]:443 ssl;'; v6_443h=$'\n    listen [::]:443 ssl http2;'
    fi
    if [[ $1 == https ]]; then
        MODE="HTTPS"
        HTTP_BLOCK="server {
    listen 80;$v6_80
    server_name $DOMAIN *.$DOMAIN;
    return 301 https://\$host\$request_uri;
}"
        # "http2 on;" needs nginx 1.25.1+; older versions take it on the listen line.
        local v; v=$(nginx -v 2>&1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' || echo 0.0.0)
        if [[ $(printf '%s\n1.25.1\n' "$v" | sort -V | head -1) == 1.25.1 ]]; then
            LISTEN="listen 443 ssl;$v6_443
    http2 on;"
        else
            LISTEN="listen 443 ssl http2;$v6_443h"
        fi
        SSL_LINES="
    ssl_certificate     /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    add_header Strict-Transport-Security \"max-age=31536000\" always;"
    else
        MODE="HTTP"; HTTP_BLOCK=""; SSL_LINES=""
        LISTEN="listen 80;$v6_80"
    fi
    export DOMAIN APP_DIR PHP_SOCK MODE LISTEN SSL_LINES HTTP_BLOCK
    if [[ $DRY_RUN == 1 ]]; then
        echo "  [dry-run] nginx ($MODE):"; render "$APP_DIR/deploy/ubuntu/nginx.conf.tpl" | sed 's/^/    /'
        return
    fi
    render "$APP_DIR/deploy/ubuntu/nginx.conf.tpl" > /etc/nginx/sites-available/diwan
    ln -sf /etc/nginx/sites-available/diwan /etc/nginx/sites-enabled/diwan
    rm -f /etc/nginx/sites-enabled/default
    nginx -t
    systemctl reload nginx
}
write_nginx http

step "9/11 SSL certificate for the domain and all its subdomains"
if [[ $TEST_DOMAIN == 1 ]]; then
    warn "Test domain ($DOMAIN): runs on http without a certificate. Once your real domain is connected: DIWAN_DOMAIN=your-domain sudo -E bash $APP_DIR/deploy/ubuntu/install.sh"
elif [[ -n $DO_TOKEN ]]; then
    run mkdir -p /root/.secrets
    [[ $DRY_RUN == 1 ]] || ( umask 077; echo "dns_digitalocean_token = $DO_TOKEN" > /root/.secrets/digitalocean.ini )
    # Fails while the registrar's nameservers still point elsewhere: the install goes on over http.
    if run certbot certonly --dns-digitalocean --dns-digitalocean-credentials /root/.secrets/digitalocean.ini \
        --dns-digitalocean-propagation-seconds 60 -d "$DOMAIN" -d "*.$DOMAIN" \
        --agree-tos -m "$ADMIN_EMAIL" --non-interactive --keep-until-expiring --deploy-hook "systemctl reload nginx"; then
        write_nginx https
        ok "HTTPS works on $DOMAIN and *.$DOMAIN, and renews automatically."
    else
        warn "Could not issue the certificate now, most likely because the DNS has not propagated yet. Setup continues on http; run the script again later to add the certificate."
    fi
else
    warn "No DigitalOcean token: the system runs on http only for now. To add the certificate later, put DO_TOKEN in $CONF and run the script again."
fi
# The site's addresses follow whether a certificate exists now.
HTTPS=false
[[ $DRY_RUN != 1 && -f /etc/letsencrypt/live/$DOMAIN/fullchain.pem ]] && HTTPS=true
SCHEME=$([[ $HTTPS == true ]] && echo https || echo http)
set_env "$ENV_FILE" APP_URL "$SCHEME://$DOMAIN"
set_env "$ENV_FILE" FORCE_HTTPS "$HTTPS"
set_env "$ENV_FILE" SESSION_SECURE_COOKIE "$HTTPS"

# ------------------------------------------------------------------ 10. supervisor
step "10/11 Background processes (queues and scheduler)"
run mkdir -p /var/log/diwan
run chown "$APP_USER:$APP_USER" /var/log/diwan
if [[ $DRY_RUN == 1 ]]; then
    echo "  [dry-run] /etc/supervisor/conf.d/diwan.conf"
else
    sed "s#__APP_DIR__#$APP_DIR#g" "$APP_DIR/deploy/ubuntu/supervisor.conf" > /etc/supervisor/conf.d/diwan.conf
fi
run supervisorctl reread
run supervisorctl update
run supervisorctl restart 'diwan:*'
ok "Tax-authority submissions, backups and alerts run in the background."

# ------------------------------------------------------------------ 11. finish
step "11/11 Final setup and platform administrator"
run chown "$APP_USER:$APP_USER" "$ENV_FILE"
run chmod 640 "$ENV_FILE"
artisan optimize:clear > /dev/null
artisan optimize
if [[ ${ADMIN_CREATED:-} != 1 ]]; then
    echo "Create the platform administrator password ($ADMIN_EMAIL) - at least 10 characters:"
    artisan erp:create-super-admin "$ADMIN_EMAIL"
    ADMIN_CREATED=1
    save_conf
fi
# Releases layout (shared .env and storage, $APP_DIR as a link) so updates never stop the system.
[[ $DRY_RUN == 1 ]] || bash "$APP_DIR/deploy/ubuntu/update.sh" --adopt
artisan erp:preflight || warn "Review the check notes above (mail and off-site backup are acceptable for a trial)."

echo
echo "${c_ok}══════════════════════════════════════════════════════${c_off}"
echo "${c_ok}  Diwan is installed${c_off}"
echo "  Register a company:      $SCHEME://$DOMAIN/register"
echo "  Platform admin login:    $SCHEME://$DOMAIN/login  ($ADMIN_EMAIL, two-factor required)"
echo "  Each company at:         $SCHEME://<company>.$DOMAIN"
echo "  Secrets:                 $SECRETS  (copy them somewhere safe, then delete)"
echo "  Update later:            sudo bash $APP_DIR/deploy/ubuntu/update.sh  (no downtime)"
echo "${c_ok}══════════════════════════════════════════════════════${c_off}"
