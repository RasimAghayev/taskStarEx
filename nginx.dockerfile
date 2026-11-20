# syntax=docker/dockerfile:1.29
#
# nginx reverse-proxy/static-file front for the Laravel backend (src/be).
# public/ is a plain source COPY from the build context (repo root) — it is
# Laravel's standard public web root, not composer/build-generated, so no
# cross-stage copy from php.dockerfile is needed.

FROM nginx:1.31.9-alpine@sha256:fb4658584bd9a916ffb6f6e365f97c25cfdb2923801f551ea08356e695f14ff7

COPY server/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY src/be/public /var/www/html/be/public

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -q --spider http://127.0.0.1/up || exit 1
