#!/usr/bin/env bash
set -euo pipefail

# Inicializa el proyecto Laravel en local: deps + contenedores + DB + healthcheck.
# Idempotente: correlo todas las veces que haga falta.

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

GREEN=$'\033[0;32m'; RED=$'\033[0;31m'; YELLOW=$'\033[1;33m'; NC=$'\033[0m'
log()  { printf '%s[init]%s %s\n' "$GREEN" "$NC" "$*"; }
warn() { printf '%s[init]%s %s\n' "$YELLOW" "$NC" "$*" >&2; }
fail() { printf '%s[init]%s %s\n' "$RED"   "$NC" "$*" >&2; exit 1; }

command -v docker >/dev/null || fail "docker no está instalado"
docker info >/dev/null 2>&1 || fail "docker daemon no responde"

# Wrapper de sail que funciona tanto con el alias de fish como desde bash.
# En bash no hay `command sail` que llame a la función — necesitamos shim distinto.
if command -v sail >/dev/null && ! declare -F sail >/dev/null; then
  # El alias externo está disponible, lo usamos vía env.
  SAIL_BIN="sail"
elif [[ -x vendor/bin/sail ]]; then
  SAIL_BIN="vendor/bin/sail"
else
  fail "sail no encontrado (ni en PATH ni en vendor/bin/)"
fi
sail() { "$SAIL_BIN" "$@"; }

# ----- 1. Dependencias del host ---------------------------------------------------
if [[ ! -d vendor ]]; then
  log "Instalando dependencias Composer..."
  docker run --rm -v "$ROOT":/app -w /app composer:latest composer install
else
  log "vendor/ OK"
fi

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
grep -q "^APP_KEY=base64" .env || { docker compose run --rm app php artisan key:generate; log "APP_KEY generada"; }

# ----- 2. Levantar contenedores ---------------------------------------------------
log "Levantando contenedores (sail up -d)..."
sail up -d

# ----- 3. Migraciones + seed ------------------------------------------------------
log "Esperando a que Postgres esté healthy..."
for i in {1..30}; do
  status=$(docker inspect --format='{{.State.Health.Status}}' laravel-postgres-1 2>/dev/null || echo "missing")
  [[ "$status" == "healthy" ]] && break
  sleep 2
done
[[ "$status" == "healthy" ]] || fail "Postgres no quedó healthy (status: $status)"

log "Corriendo migrate:fresh --seed..."
sail artisan migrate:fresh --seed --force

# ----- 4. Build de assets si hace falta -------------------------------------------
if [[ ! -f public/build/manifest.json ]]; then
  warn "public/build/manifest.json no existe — corré 'pnpm run build' o 'pnpm run dev' en el host"
fi

# ----- 5. Healthcheck HTTP --------------------------------------------------------
log "Reiniciando nginx para refrescar resolución DNS de 'app'..."
docker compose restart nginx >/dev/null

log "Esperando HTTP 200 en http://localhost:8000..."
for i in {1..30}; do
  code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 3 http://localhost:8000 || true)
  [[ "$code" == "200" ]] && break
  sleep 2
done

if [[ "$code" == "200" ]]; then
  log "Listo. Abrí http://localhost:8000"
else
  fail "Nginx responde $code — revisá: sail logs nginx && sail logs app"
fi
