FROM php:8.4-fpm-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql redis pcntl intl zip opcache

# Sail corre los comandos con `exec -u sail`; el Dockerfile base de Sail crea ese user.
# Este proyecto usa `user: 1000:1000` en compose, así que el uid debe coincidir con el del host.
# ponytail: Alpine usa addgroup/adduser, no useradd. Cambiar a debian si se migra.
RUN addgroup -g 1000 -S sail && adduser -u 1000 -G sail -D sail

WORKDIR /var/www
