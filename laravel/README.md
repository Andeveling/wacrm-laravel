# wacrm — Laravel

Laravel 13 + Inertia 2 + React + Tailwind 4 (starter kit oficial React), corriendo
sobre [Laravel Sail](https://laravel.com/docs/sail): Postgres 17 + pgvector, Redis,
Reverb (WebSockets), worker de cola y scheduler.

## Arranque

```bash
cp .env.example .env          # solo la primera vez
composer install              # vendor/ vive en el host, no requiere PHP local completo
./vendor/bin/sail up -d       # laravel.test, pgsql, redis, reverb, queue, scheduler
./vendor/bin/sail artisan key:generate   # solo la primera vez
./vendor/bin/sail artisan migrate
pnpm install
pnpm dev                      # Vite en el host (HMR)
```

Alias recomendado (evita escribir `./vendor/bin/sail` cada vez):

```bash
alias sail='sh vendor/bin/sail'
```

- App: <http://localhost:8000>
- Reverb (WebSockets): `localhost:8080`
- Postgres: `localhost:5433` (user/db/pass: `wacrm`/`wacrm`/`secret`)

## Comandos artisan

Siempre dentro del contenedor (el `.env` apunta a los hosts de docker: `pgsql`, `redis`):

```bash
./vendor/bin/sail artisan <comando>
```

## Qué NO está todavía

- MinIO (storage S3): se añade cuando se migre `upload-media.ts` (Fase de storage).
- Build de producción (imagen con código dentro, TLS en nginx): se define en el cutover.
