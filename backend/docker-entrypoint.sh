#!/bin/sh
set -eu

cd /var/www/html
mkdir -p storage/app/product-images storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache

# Bind mounts and restored volumes replace permissions set during image builds.
# Keep host ownership while allowing the PHP-FPM group to write Laravel files.
if [ "$(id -u)" = "0" ]; then
    chgrp -R www-data storage bootstrap/cache
    chmod -R g+rwX storage bootstrap/cache
fi

exec docker-php-entrypoint "$@"
