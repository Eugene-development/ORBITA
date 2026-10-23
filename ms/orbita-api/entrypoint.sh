#!/bin/sh
set -eu
cd /var/www
php docker/runtime-check.php
mkdir -p storage/logs storage/framework/sessions storage/framework/views storage/framework/cache/data bootstrap/cache
# Configuration belongs to this container and is supplied by Compose env_file.
rm -f bootstrap/cache/config.php
php artisan package:discover --no-ansi >&2
if [ "${APP_ENV:-production}" = production ]; then php artisan lighthouse:cache --no-ansi >&2; fi
if [ "$#" -eq 0 ]; then set -- php-fpm -F; fi
exec "$@"
