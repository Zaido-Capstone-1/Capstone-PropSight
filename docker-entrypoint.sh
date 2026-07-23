#!/bin/bash
set -e

# Railway injects the port to listen on as $PORT (it varies per deploy) —
# Apache's default config hardcodes port 80, so rewrite it at container start.
PORT="${PORT:-80}"

sed -ri "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/g" /etc/apache2/sites-enabled/000-default.conf

exec "$@"