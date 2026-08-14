#!/usr/bin/env bash
# Provisiona el VPS de WACRM: stack nativo Laravel 13 (PHP 8.4 + nginx + Postgres 16/pgvector + redis).
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

DB_PASSWORD="${DB_PASSWORD:?DB_PASSWORD required}"
CI_PUBKEY="${CI_PUBKEY:?CI_PUBKEY required}"

log(){ echo "[provision] $*"; }

# ----- 0. Swap (la VPS solo tiene 1GB de RAM) -----------------------------------
if [[ ! -f /swapfile ]]; then
  log "Agregando swapfile de 1G"
  fallocate -l 1G /swapfile && chmod 600 /swapfile
  mkswap /swapfile >/dev/null && swapon /swapfile
  grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
  grep -q 'vm.swappiness' /etc/sysctl.conf || echo 'vm.swappiness=10' >> /etc/sysctl.conf
  sysctl -p >/dev/null
fi

# ----- 1. Paquetes base ---------------------------------------------------------
log "apt-get update"
apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg git rsync unzip supervisor \
  software-properties-common ufw >/dev/null

# ----- 2. PHP 8.4 (ondrej) ------------------------------------------------------
if ! dpkg -s php8.4-fpm >/dev/null 2>&1; then
  log "Agregando PPA ondrej/php"
  LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1
  apt-get update -qq
  log "Instalando PHP 8.4 + extensiones (pcntl va incluido en php8.4-cli)"
  apt-get install -y -qq php8.4-cli php8.4-fpm php8.4-pgsql php8.4-redis \
    php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-intl php8.4-bcmath \
    php8.4-gd php8.4-opcache php8.4-sockets >/dev/null
fi

# ----- 3. nginx + postgres + redis + composer -----------------------------------
log "Instalando nginx, postgres (pgvector), redis, composer"
apt-get install -y -qq nginx postgresql-16 postgresql-16-pgvector redis-server >/dev/null
apt-get install -y -qq composer >/dev/null

# ----- 4. Base de datos ----------------------------------------------------------
log "Creando rol + base wacrm"
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='wacrm'" | grep -q 1; then
  sudo -u postgres psql -q -c "CREATE ROLE wacrm LOGIN PASSWORD '$DB_PASSWORD' SUPERUSER"
fi
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='wacrm'" | grep -q 1; then
  sudo -u postgres createdb -O wacrm wacrm
fi

# ----- 5. Usuario deploy + dir de la app ----------------------------------------
log "Creando usuario deploy y /var/www/wacrm"
id deploy >/dev/null 2>&1 || useradd -m -s /bin/bash deploy
install -d -o deploy -g deploy /var/www/wacrm
install -d -o deploy -g deploy -m 775 /var/www/wacrm/storage/app/public
install -d -o deploy -g deploy -m 775 /var/www/wacrm/storage/framework/cache
install -d -o deploy -g deploy -m 775 /var/www/wacrm/storage/framework/sessions
install -d -o deploy -g deploy -m 775 /var/www/wacrm/storage/framework/views
install -d -o deploy -g deploy -m 775 /var/www/wacrm/storage/logs
install -d -o deploy -g deploy -m 775 /var/www/wacrm/bootstrap/cache
# `install -d` solo aplica owner/gid al último nivel; normalizo el árbol completo.
chown -R deploy:deploy /var/www/wacrm

# SSH: clave de CI + acceso del usuario local
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
grep -qF "$CI_PUBKEY" /home/deploy/.ssh/authorized_keys 2>/dev/null || echo "$CI_PUBKEY" >> /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys

# sudo limitado para supervisorctl (deploy.sh hace restart de workers/reverb)
cat > /etc/sudoers.d/wacrm-deploy <<'EOF'
deploy ALL=(root) NOPASSWD: /usr/bin/supervisorctl
EOF
chmod 440 /etc/sudoers.d/wacrm-deploy

# ----- 6. nginx -----------------------------------------------------------------
log "Configurando nginx"
cat > /etc/nginx/sites-available/wacrm <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name _;

    root /var/www/wacrm/public;
    index index.php;

    charset utf-8;
    client_max_body_size 25M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Meta documents a 3 MB webhook payload limit. Keep the proxy boundary
    # aligned with the byte-exact application check for this public endpoint.
    location = /api/whatsapp/webhook {
        client_max_body_size 3M;
        try_files /__wacrm_webhook_never_exists__ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        # El header Link de preload de Inertia/Vite supera los 8k por defecto.
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/wacrm.access.log;
    error_log /var/log/nginx/wacrm.error.log;
}
EOF
ln -sf /etc/nginx/sites-available/wacrm /etc/nginx/sites-enabled/wacrm
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ----- 7. php-fpm (ajustado a 1GB, corre como deploy para escribir en storage/) --
log "Ajustando php-fpm"
FPM=/etc/php/8.4/fpm/pool.d/www.conf
sed -i 's/^user =.*/user = deploy/' "$FPM"
sed -i 's/^group =.*/group = deploy/' "$FPM"
sed -i 's/^listen.owner =.*/listen.owner = deploy/' "$FPM"
sed -i 's/^listen.group =.*/listen.group = www-data/' "$FPM"
sed -i 's/^pm.max_children *=.*/pm.max_children = 4/' "$FPM"
sed -i 's/^pm.start_servers *=.*/pm.start_servers = 2/' "$FPM"
sed -i 's/^pm.min_spare_servers *=.*/pm.min_spare_servers = 1/' "$FPM"
sed -i 's/^pm.max_spare_servers *=.*/pm.max_spare_servers = 3/' "$FPM"
grep -q 'pm.max_requests' "$FPM" || echo 'pm.max_requests = 500' >> "$FPM"
grep -q 'request_terminate_timeout' "$FPM" || echo 'request_terminate_timeout = 120s' >> "$FPM"
sed -i 's/^;request_terminate_timeout.*/request_terminate_timeout = 120s/' "$FPM"

cat > /etc/php/8.4/fpm/conf.d/99-wacrm.ini <<'EOF'
memory_limit = 256M
opcache.enable = 1
opcache.memory_consumption = 64
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
expose_php = 0
EOF
cat > /etc/php/8.4/cli/conf.d/99-wacrm-cli.ini <<'EOF'
memory_limit = 512M
opcache.enable_cli = 0
EOF
systemctl restart php8.4-fpm

# ----- 8. redis (límite de memoria) ---------------------------------------------
grep -q '^maxmemory ' /etc/redis/redis.conf || echo 'maxmemory 128mb' >> /etc/redis/redis.conf
grep -q '^maxmemory-policy' /etc/redis/redis.conf || echo 'maxmemory-policy allkeys-lru' >> /etc/redis/redis.conf
systemctl restart redis-server

# ----- 9. supervisor: queue + reverb --------------------------------------------
log "Configurando supervisor (queue + reverb)"
cat > /etc/supervisor/conf.d/wacrm.conf <<'EOF'
[program:wacrm-queue]
command=/usr/bin/php /var/www/wacrm/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/wacrm
user=deploy
autostart=true
autorestart=true
startsecs=3
stopwaitsecs=30
redirect_stderr=true
stdout_logfile=/var/log/wacrm-queue.log
stdout_logfile_maxbytes=10MB

[program:wacrm-reverb]
command=/usr/bin/php /var/www/wacrm/artisan reverb:start --host=0.0.0.0 --port=8080
directory=/var/www/wacrm
user=deploy
autostart=true
autorestart=true
startsecs=3
stopwaitsecs=30
redirect_stderr=true
stdout_logfile=/var/log/wacrm-reverb.log
stdout_logfile_maxbytes=10MB
EOF
supervisorctl reread >/dev/null
supervisorctl update >/dev/null

# ----- 10. cron del scheduler ----------------------------------------------------
log "Configurando cron del scheduler"
cat > /etc/cron.d/wacrm <<'EOF'
* * * * * deploy /usr/bin/php /var/www/wacrm/artisan schedule:run >> /dev/null 2>&1
EOF
chmod 644 /etc/cron.d/wacrm

# ----- 11. firewall ---------------------------------------------------------------
log "Configurando ufw (22, 80, 8080, 443)"
ufw --force reset >/dev/null
ufw allow OpenSSH >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 8080/tcp >/dev/null
ufw allow 443/tcp >/dev/null
ufw --force enable >/dev/null

log "Provisioning completo"
