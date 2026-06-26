#!/bin/sh
# Boot the MySQL console: Adminer (PHP built-in server on loopback) behind Caddy basic auth.
#
# AUTH_USER/AUTH_PASS are the per-session basic-auth creds injected by the platform. Caddy's
# basic_auth needs a bcrypt hash, so we compute it at boot from the plaintext AUTH_PASS and
# hand both to the Caddyfile via env. DB_* (the managed MySQL connection) is read by Adminer
# (index.php) directly from the environment.
set -eu

AUTH_USER="${AUTH_USER:-vbn}"
if [ -z "${AUTH_PASS:-}" ]; then
    echo "mysql-console: AUTH_PASS is required" >&2
    exit 1
fi

AUTH_HASH="$(caddy hash-password --plaintext "$AUTH_PASS")"
export AUTH_USER AUTH_HASH

# Adminer on loopback only — Caddy is the sole public listener. display_errors=0: Adminer 4.8.1
# predates PHP 8.1's stricter "array offset on null" warning and would otherwise print a harmless
# notice atop the page; real errors still surface through Adminer's own UI + the log.
php -d display_errors=0 -S 127.0.0.1:9000 -t /app >/tmp/php.log 2>&1 &

exec caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
