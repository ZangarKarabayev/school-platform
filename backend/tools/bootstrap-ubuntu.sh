#!/usr/bin/env bash
set -euo pipefail

: "${REPO_URL:?REPO_URL is required}"
: "${APP_URL:?APP_URL is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

APP_DIR="${APP_DIR:-/var/www/school-platform/backend}"
APP_ROOT="${APP_ROOT:-/var/www/school-platform}"
APP_ENV="${APP_ENV:-production}"

DB_CONNECTION="${DB_CONNECTION:-mysql}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

PHP83="php8.3"
PHP82="php8.2"
COMPOSER_BIN="${COMPOSER_BIN:-/usr/bin/composer}"

KALKAN_DIR="${KALKAN_DIR:-/root/kalkan-verifier}"
KALKAN_PUBLIC_DIR="$KALKAN_DIR/public"
KALKAN_CERTS_DIR="$KALKAN_DIR/certs"
KALKAN_SO_SOURCE="$KALKAN_DIR/kalkancrypt.so"
KALKAN_SO_TARGET="/usr/lib/php/20220829/kalkancrypt.so"

NGINX_SITE="${NGINX_SITE:-/etc/nginx/sites-available/school-platform}"
NGINX_LINK="${NGINX_LINK:-/etc/nginx/sites-enabled/school-platform}"
NGINX_SERVER_NAME="${NGINX_SERVER_NAME:-_}"

echo "[1/15] Install base packages"
apt update
apt install -y software-properties-common ca-certificates lsb-release apt-transport-https curl git unzip nginx openssl mysql-client libltdl7 libpcsclite1 supervisor
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y \
  php8.3 php8.3-cli php8.3-fpm php8.3-common php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd php8.3-sqlite3 \
  php8.2 php8.2-cli php8.2-fpm php8.2-common \
  composer

echo "[2/15] Create app directories"
mkdir -p "$APP_ROOT"
mkdir -p "$KALKAN_PUBLIC_DIR"
mkdir -p "$KALKAN_CERTS_DIR"

echo "[3/15] Clone project"
if [ ! -d "$APP_DIR/.git" ]; then
  git clone "$REPO_URL" "$APP_DIR"
fi

cd "$APP_DIR"

echo "[4/15] Install PHP dependencies"
$PHP83 $COMPOSER_BIN install --no-interaction --prefer-dist --optimize-autoloader

echo "[5/15] Create .env"
if [ ! -f .env ]; then
  cp .env.example .env
fi

sed -i "s|^APP_ENV=.*|APP_ENV=$APP_ENV|g" .env
sed -i "s|^APP_URL=.*|APP_URL=$APP_URL|g" .env
sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=$DB_CONNECTION|g" .env
sed -i "s|^DB_HOST=.*|DB_HOST=$DB_HOST|g" .env
sed -i "s|^DB_PORT=.*|DB_PORT=$DB_PORT|g" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$DB_DATABASE|g" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=$DB_USERNAME|g" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|g" .env

grep -q "^EDS_AUTH_VERIFIER_DRIVER=" .env \
  && sed -i "s|^EDS_AUTH_VERIFIER_DRIVER=.*|EDS_AUTH_VERIFIER_DRIVER=http|g" .env \
  || echo "EDS_AUTH_VERIFIER_DRIVER=http" >> .env

grep -q "^EDS_AUTH_VERIFIER_URL=" .env \
  && sed -i "s|^EDS_AUTH_VERIFIER_URL=.*|EDS_AUTH_VERIFIER_URL=http://127.0.0.1:5055|g" .env \
  || echo "EDS_AUTH_VERIFIER_URL=http://127.0.0.1:5055" >> .env

echo "[6/15] Laravel app setup"
$PHP83 artisan key:generate --force
$PHP83 artisan storage:link || true

echo "[7/15] Permissions"
chown -R www-data:www-data "$APP_ROOT"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

echo "[8/15] Configure PHP 8.2 Kalkan module"
if [ -f "$KALKAN_SO_SOURCE" ]; then
  cp "$KALKAN_SO_SOURCE" "$KALKAN_SO_TARGET"
  chmod 644 "$KALKAN_SO_TARGET"
  echo "extension=kalkancrypt.so" > /etc/php/8.2/mods-available/kalkancrypt.ini
  phpenmod -v 8.2 kalkancrypt
fi

phpdismod -v 8.3 kalkancrypt || true

echo "[9/15] Install verifier service"
cat > /etc/systemd/system/kalkan-verifier.service <<'EOF'
[Unit]
Description=Kalkan CMS verifier (PHP 8.2)
After=network.target

[Service]
Type=simple
User=root
Group=root
WorkingDirectory=/root/kalkan-verifier/public
ExecStart=/usr/bin/php8.2 -S 127.0.0.1:5055 -t /root/kalkan-verifier/public
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

echo "[10/15] Configure supervisor for Laravel queue worker"
cat > /etc/supervisor/conf.d/school-platform-worker.conf <<'EOF'
[program:school-platform-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php8.3 /var/www/school-platform/backend/artisan queue:work --sleep=3 --tries=3 --timeout=120
directory=/var/www/school-platform/backend
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/school-platform/backend/storage/logs/worker.log
stopwaitsecs=3600
EOF

echo "[11/15] Configure nginx"
cat > "$NGINX_SITE" <<EOF
server {
    listen 80;
    server_name $NGINX_SERVER_NAME;
    root $APP_DIR/public;
    index index.php index.html;

    client_max_body_size 50M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

ln -sf "$NGINX_SITE" "$NGINX_LINK"
rm -f /etc/nginx/sites-enabled/default

echo "[12/15] Start services"
systemctl daemon-reload
systemctl enable php8.3-fpm
systemctl enable php8.2-fpm
systemctl enable nginx
systemctl enable supervisor
systemctl enable kalkan-verifier || true

systemctl restart php8.3-fpm
systemctl restart php8.2-fpm
systemctl restart nginx
systemctl restart supervisor || true
supervisorctl reread || true
supervisorctl update || true
supervisorctl start school-platform-worker:* || true
systemctl restart kalkan-verifier || true

echo "[13/15] Database migrate"
$PHP83 artisan migrate --force

echo "[14/15] Base seed"
$PHP83 artisan db:seed --class=RolePermissionSeeder --force || true

echo "[15/15] Laravel cache"
$PHP83 artisan optimize:clear
$PHP83 artisan config:cache
$PHP83 artisan route:cache

nginx -t

echo
echo "Done."
echo "Manual follow-up:"
echo "1. Put verifier file here: $KALKAN_PUBLIC_DIR/index.php"
echo "2. Put Kalkan PHP 8.2 module here: $KALKAN_SO_SOURCE"
echo "3. Put NUC certs here: $KALKAN_CERTS_DIR"
echo "4. Add KATO files into: $APP_DIR/storage/app/private"
echo "5. Add NUC certs into Ubuntu trust store and run update-ca-certificates"
echo "6. Set QUEUE_CONNECTION in .env if background jobs are used"
echo "7. Supervisor worker config: /etc/supervisor/conf.d/school-platform-worker.conf"
echo "8. Run demo import if needed: $PHP83 artisan setup:demo"
