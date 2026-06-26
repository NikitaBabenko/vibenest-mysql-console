# Minimal Adminer image for VibeNest's on-demand DB console (MySQL path).
#
# At runtime the platform injects DB_* (the managed MySQL connection, parsed from the
# database's InternalUrl) + AUTH_USER/AUTH_PASS, and spins this up as a co-located Coolify
# app on the same server as the DB (mirrors the pgweb / sqlite consoles).
#
# Two layers:
#   * Adminer (PHP built-in server on 127.0.0.1:9000) with an autologin plugin that reads the
#     DB_* env and connects straight to the one managed database — no login screen.
#   * Caddy (:8080) fronts it with HTTP basic_auth (AUTH_USER + a bcrypt hash of AUTH_PASS,
#     computed at boot). This is the real security boundary — pgweb has basic auth natively;
#     Adminer does not, and the Coolify edge is reachable directly by Host header.
#
# Built by Coolify from the private repo VibeNest/mysql-console via the platform's GitHub-App
# private-repo mechanism (NOT by the .NET solution build). Mirror of these files.
FROM caddy:2-alpine AS caddybin

FROM php:8.3-cli-alpine

# MySQL driver for Adminer (mysqli is what the 'server' driver uses).
RUN docker-php-ext-install mysqli pdo_mysql > /dev/null

# Pin Adminer so the console UI is reproducible (bump deliberately — supply-chain).
ADD https://github.com/vrana/adminer/releases/download/v4.8.1/adminer-4.8.1-mysql.php /app/adminer.php

COPY index.php /app/index.php
COPY --from=caddybin /usr/bin/caddy /usr/bin/caddy
COPY Caddyfile /etc/caddy/Caddyfile
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
