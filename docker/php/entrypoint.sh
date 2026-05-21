#!/bin/sh
set -e

LOCK_HASH=$(md5sum composer.lock 2>/dev/null | cut -d' ' -f1)
INSTALLED_HASH=$(cat vendor/.composer-lock-hash 2>/dev/null || echo "")

if [ "$LOCK_HASH" != "$INSTALLED_HASH" ]; then
    echo "Installing dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
    echo "$LOCK_HASH" > vendor/.composer-lock-hash
fi

exec "$@"
