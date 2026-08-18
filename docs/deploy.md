# Deploy

WACRM corre en una VPS de 1 CPU / 1 GB RAM (Ubuntu 24.04, alias SSH `andeveling`, IP `167.172.150.205`) con stack nativo:

| Pieza | Detalle |
| --- | --- |
| PHP | 8.4 (PPA ondrej) vía php-fpm, pool ajustado a 1 GB |
| nginx | `sites-available/wacrm` → `public/` |
| Postgres | 16 + `postgresql-16-pgvector`, rol `wacrm` (SUPERUSER), db `wacrm` |
| Redis | local, `maxmemory 128mb` |
| Workers | supervisor: `wacrm-queue` (queue:work) y `wacrm-reverb` (websockets) |
| Scheduler | cron.d → `artisan schedule:run` cada minuto |
| Swap | 1 GB (la VPS tiene 1 GB de RAM) |

El aprovisionamiento del VPS está versionado en `scripts/provision-server.sh` (corre como root; idempotente salvo la base y el swap).

## Cómo se despliega

El VPS de pruebas se actualiza **solo** desde `develop`. Cada push a
`develop` dispara `.github/workflows/deploy.yml`. `main` no despliega.
Los tests corren en push a `develop`/`main` y en todo pull request.


El job de deploy:

1. En un runner de GitHub se instala PHP 8.4 + `composer install --no-dev` y `pnpm build` con `VITE_REVERB_*` (host/puerto/scheme públicos + `secrets.REVERB_APP_KEY`). La VPS **no compila nada** (no tiene node/pnpm) y **no** lleva `VITE_*` en su `.env`.
2. `rsync --delete` sube el repo (excluye `.git`, `.env*`, `node_modules`, `storage`, `bootstrap/cache`, docs) a `/var/www/wacrm/`.
3. En el servidor corre `scripts/deploy.sh`:
   - primer deploy: genera `.env` (APP_KEY, REVERB keys, DB_PASSWORD desde secret), migra y siembra el escenario demo;
   - siempre: `migrate --force`, `config:cache` + `event:cache` (no `route:cache`: hay rutas con closures), `storage:link`, recarga PHP-FPM para activar el código nuevo y vaciar OPcache, y reinicia workers con supervisor.

Secrets de Actions: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DB_PASSWORD`, `REVERB_APP_KEY` (misma key que el `.env` del VPS; no regenerar sin rebuild del JS). El `.env` del servidor es la fuente de verdad; el `APP_URL` del workflow solo siembra el primer `.env`. `.env.production` local (gitignored) es el respaldo.

nginx en el VPS termina TLS y hace proxy de `/app` (WebSocket de Reverb) a `127.0.0.1:8080`. No abrir el 8080 al mundo.

Acceso demo (siembra del primer deploy): `test@example.com` / `password`.

## Ops

### Meta webhook ingress

Meta webhook requests are accepted up to 3 MiB (3,145,728 bytes). The
application checks the exact received byte count and the versioned VPS
provisioning keeps nginx's `/api/whatsapp/webhook` boundary aligned with it;
do not raise that location's `client_max_body_size` without changing the
application contract and tests together.

```bash
# logs
journalctl -u nginx -f
tail -f /var/log/wacrm-queue.log /var/log/wacrm-reverb.log
tail -f /var/www/wacrm/storage/logs/laravel.log

# workers
sudo supervisorctl status            # wacrm-queue / wacrm-reverb
sudo supervisorctl restart wacrm-queue wacrm-reverb
```

## Dominio + TLS

La app responde en `https://wacrm.andeveling.com`. El `.env` del VPS tiene ese `APP_URL`; no lo pises desde el workflow.

Para cambiar de dominio: actualizar `APP_URL` (y Reverb) en el `.env` del servidor, `php artisan config:cache`, y el A record. El `APP_URL` de `deploy.yml` solo importa si se regenera el `.env`.

## Cambiar credenciales

Regenerar DB password: cambiar en Postgres (`ALTER ROLE wacrm PASSWORD ...`), en el secret `DB_PASSWORD` de GitHub y en el `.env` del servidor (`php artisan config:cache`). La clave SSH de deploy: `gh secret set DEPLOY_SSH_KEY` con una ed25519 nueva cuyo público esté en `/home/deploy/.ssh/authorized_keys`.
