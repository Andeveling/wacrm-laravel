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

`develop` es la rama de integración (issues y PRs diarios). `main` es el
corte que se despliega. Cada push a `main` dispara `.github/workflows/deploy.yml`.
Los tests corren en push a `develop`/`main` y en todo pull request. El deploy
no se dispara al mergear a `develop`.

El job de deploy:

1. En un runner de GitHub se instala PHP 8.4 + `composer install --no-dev` y `pnpm build`. La VPS **no compila nada** (no tiene node/pnpm).
2. `rsync --delete` sube el repo (excluye `.git`, `.env*`, `node_modules`, `storage`, `bootstrap/cache`, docs) a `/var/www/wacrm/`.
3. En el servidor corre `scripts/deploy.sh`:
   - primer deploy: genera `.env` (APP_KEY, REVERB keys, DB_PASSWORD desde secret), migra y siembra el escenario demo;
   - siempre: `migrate --force`, `config:cache` + `event:cache` (no `route:cache`: hay rutas con closures), `storage:link`, recarga PHP-FPM para activar el código nuevo y vaciar OPcache, y reinicia workers con supervisor.

Secrets de Actions: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DB_PASSWORD`. El `.env` del servidor es la fuente de verdad; `.env.production` local (gitignored) es el respaldo.

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

## Dominio + TLS (pendiente)

Hoy la app responde en `http://167.172.150.205`. Cuando haya dominio (registrar en Cloudflare, Porkbun o Namecheap):

1. Registrar un A record → `167.172.150.205`.
2. Cambiar `APP_URL` en `.env` del servidor (y en el `env:` de `deploy.yml`) a `https://<dominio>`.
3. Configurar TLS: `sudo apt install certbot python3-certbot-nginx && sudo certbot --nginx -d <dominio>`.
4. Reverb: mover `REVERB_SCHEME=https` y `REVERB_PORT=443`, o proxear `/app` desde nginx (mismo origen).

## Cambiar credenciales

Regenerar DB password: cambiar en Postgres (`ALTER ROLE wacrm PASSWORD ...`), en el secret `DB_PASSWORD` de GitHub y en el `.env` del servidor (`php artisan config:cache`). La clave SSH de deploy: `gh secret set DEPLOY_SSH_KEY` con una ed25519 nueva cuyo público esté en `/home/deploy/.ssh/authorized_keys`.
