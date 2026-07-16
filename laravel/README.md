# wacrm — Laravel

Fase 1 del plan de migración ([docs/migration-laravel13-docker.md](../docs/migration-laravel13-docker.md)).
Laravel 13 + Inertia 2 + React + Tailwind 4 (starter kit oficial React) con todo el
stack de servicios en docker-compose: Postgres 17 + pgvector, Redis, Reverb
(WebSockets), workers de cola y scheduler.

## Arranque

```bash
cp .env.example .env          # solo la primera vez
composer install              # vendor/ vive en el host (PHP 8.4 local)
pnpm install
docker compose up -d          # postgres, redis, nginx, php-fpm, reverb, queue, scheduler
docker compose exec app php artisan key:generate   # solo la primera vez
docker compose exec app php artisan migrate
pnpm dev                      # Vite en el host (HMR)
```

- App: <http://localhost:8000>
- Reverb (WebSockets): `localhost:8080`
- Postgres: `localhost:5432` (user/db/pass: `wacrm`/`wacrm`/`secret`)

## Comandos artisan

Siempre dentro del contenedor (el `.env` apunta a los hosts de docker: `postgres`, `redis`):

```bash
docker compose exec app php artisan <comando>
```

## Qué NO está todavía

- MinIO (storage S3): se añade cuando se migre `upload-media.ts` (Fase de storage).
- Build de producción (imagen con código dentro, TLS en nginx): se define en el cutover.
