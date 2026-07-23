#!/bin/bash
set -e

# Railway can re-enable mpm_event at container start even when the image
# already disabled it at build time — force prefork-only every boot, and
# remove any leftover symlinks so a stale mpm_event.load doesn't linger.
a2dismod mpm_event mpm_worker 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# Railway injects the port to listen on as $PORT (it varies per deploy) —
# Apache's default config hardcodes port 80, so rewrite it at container start.
PORT="${PORT:-80}"

sed -ri "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/g" /etc/apache2/sites-enabled/000-default.conf

apache2ctl -t

exec "$@"