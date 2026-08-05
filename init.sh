#!/usr/bin/env bash
set -euo pipefail

# Inicializa el proyecto Laravel en local con Sail: deps + contenedores + DB + healthcheck.
# Idempotente: correlo todas las veces que haga falta.

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

GREEN=$'\033[0;32m'; RED=$'\033[0;31m'; YELLOW=$'\033[1;33m'; NC=$'\033[0m'
log()  { printf '%s[init]%s %s\n' "$GREEN" "$NC" "$*"; }
warn() { printf '%s[init]%s %s\n' "$YELLOW" "$NC" "$*" >&2; }
fail() { printf '%s[init]%s %s\n' "$RED"   "$NC" "$*" >&2; exit 1; }

command -v docker >/dev/null || fail "docker no está instalado"
docker info >/dev/null 2>&1 || fail "docker daemon no responde"

# ----- 1. Dependencias del host ---------------------------------------------------
if [[ ! -d vendor ]]; then
  log "Instalando dependencias Composer..."
  docker run --rm -v "$ROOT":/app -w /app composer:latest composer install
fi
SAIL="vendor/bin/sail"
[[ -x "$SAIL" ]] || fail "vendor/bin/sail no encontrado — corré 'composer install'"

if [[ ! -d node_modules ]]; then
  log "Instalando dependencias npm..."
  if command -v pnpm >/dev/null; then pnpm install
  elif command -v npm >/dev/null; then npm install
  else fail "ni pnpm ni npm disponibles en el host"
  fi
else
  log "node_modules/ OK"
fi

[[ -f .env ]] || { cp .env.example .env; log ".env creado desde .env.example"; }

# ----- 2. Levantar contenedores ---------------------------------------------------
log "Levantando contenedores (sail up -d)..."
"$SAIL" up -d

grep -q "^APP_KEY=base64" .env || { "$SAIL" artisan key:generate; log "APP_KEY generada"; }

# ----- 3. Migraciones + seed ------------------------------------------------------
log "Esperando a que Postgres esté healthy..."
for i in {1..30}; do
  status=$(docker inspect --format='{{.State.Health.Status}}' "$(docker compose ps -q pgsql)" 2>/dev/null || echo "missing")
  [[ "$status" == "healthy" ]] && break
  sleep 2
done
[[ "$status" == "healthy" ]] || fail "Postgres no quedó healthy (status: $status)"

log "Corriendo migrate:fresh --seed..."
"$SAIL" artisan migrate:fresh --seed --force

# ----- 4. Build de assets si hace falta -------------------------------------------
if [[ ! -f public/build/manifest.json ]]; then
  warn "public/build/manifest.json no existe — corré 'pnpm run build' o 'pnpm run dev' en el host"
fi

# ----- 5. Healthcheck HTTP --------------------------------------------------------
log "Esperando HTTP 200 en http://localhost:8000..."
for i in {1..30}; do
  code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 3 http://localhost:8000 || true)
  [[ "$code" == "200" ]] && break
  sleep 2
done

if [[ "$code" == "200" ]]; then
  log "Listo. Abrí http://localhost:8000"
else
  fail "Sail responde $code — revisá: $SAIL logs laravel.test"
fi
