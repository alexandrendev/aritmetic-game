#!/bin/sh
set -e

if [ ! -d vendor ]; then
    echo "Installing dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

exec "$@"
