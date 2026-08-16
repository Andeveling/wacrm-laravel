#!/usr/bin/env bash
# Corre EN el servidor (usuario deploy) tras cada rsync de GitHub Actions.
# Idempotente: prepara .env la primera vez, migra, cachea y reinicia workers.
set -euo pipefail

cd /var/www/wacrm

APP_URL="${APP_URL:?APP_URL required}"
DB_PASSWORD="${DB_PASSWORD:-}"
APP_URL_HOST="${APP_URL#*://}"

FIRST_DEPLOY=0
if [[ ! -f .env ]]; then
  FIRST_DEPLOY=1
  log(){ echo "[deploy] $*"; }
  log "Creando .env por primera vez"

  append(){ printf '%s\n' "$1" >> .env; }
  : > .env
  append "APP_NAME=WACRM"
  append "APP_ENV=production"
  append "APP_KEY=$(php artisan key:generate --show)"
  append "APP_DEBUG=false"
  append "APP_URL=${APP_URL}"
  append "APP_LOCALE=es"
  append "APP_FALLBACK_LOCALE=en"
  append "APP_FAKER_LOCALE=es_ES"
  append "APP_MAINTENANCE_DRIVER=file"
  append "LOG_CHANNEL=stack"
  append "LOG_STACK=single"
  append "LOG_LEVEL=info"
  append "DB_CONNECTION=pgsql"
  append "DB_HOST=127.0.0.1"
  append "DB_PORT=5432"
  append "DB_DATABASE=wacrm"
  append "DB_USERNAME=wacrm"
  append "DB_PASSWORD=${DB_PASSWORD}"
  append "SESSION_DRIVER=database"
  append "SESSION_LIFETIME=120"
  append "SESSION_ENCRYPT=false"
  append "BROADCAST_CONNECTION=reverb"
  append "QUEUE_CONNECTION=redis"
  append "CACHE_STORE=redis"
  append "REDIS_CLIENT=phpredis"
  append "REDIS_HOST=127.0.0.1"
  append "REDIS_PORT=6379"
  append "REDIS_PASSWORD=null"
  append "FILESYSTEM_DISK=local"
  append "MAIL_MAILER=log"
  append "MAIL_FROM_ADDRESS=hello@${APP_URL_HOST}"
  append "MAIL_FROM_NAME=\"\${APP_NAME}\""
  append "REVERB_SERVER_HOST=0.0.0.0"
  append "REVERB_SERVER_PORT=8080"
  append "REVERB_HOST=${APP_URL_HOST}"
  append "REVERB_PORT=8080"
  append "REVERB_SCHEME=ws"
  append "REVERB_APP_ID=wacrm"
  append "REVERB_APP_KEY=$(openssl rand -hex 16)"
  append "REVERB_APP_SECRET=$(openssl rand -hex 16)"
  append "META_APP_SECRET="
  append "META_WEBHOOK_VERIFY_TOKEN="
  append "META_GRAPH_API_URL=https://graph.facebook.com"
  append "META_GRAPH_API_VERSION=v21.0"
fi

echo "[deploy] APP_ENV=$(grep '^APP_ENV=' .env | cut -d= -f2)"
echo "[deploy] APP_URL=$(grep '^APP_URL=' .env | cut -d= -f2)"

echo "[deploy] migrate --force"
php artisan migrate --force

if [[ "$FIRST_DEPLOY" == "1" ]]; then
  echo "[deploy] primer deploy: seed del escenario demo"
  composer install --no-interaction --prefer-dist --no-progress
  php artisan db:seed --force
  composer install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader
fi

echo "[deploy] config:cache + event:cache + storage:link"
php artisan config:cache
php artisan event:cache
php artisan storage:link

echo "[deploy] recargando php-fpm para activar el código nuevo y vaciar OPcache"
sudo -n systemctl reload php8.4-fpm

echo "[deploy] reiniciando workers"
sudo -n supervisorctl restart wacrm-queue wacrm-reverb

echo "[deploy] listo"
