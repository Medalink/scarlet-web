#!/bin/sh
# Builds the config, route and event caches from the container's own environment, then runs the command.
set -eu
cd /app
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan event:cache --no-interaction
exec "$@"
