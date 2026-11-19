# syntax=docker/dockerfile:1.29
#
# nginx reverse-proxy/static-file front for the Laravel backend (src/be).
# public/ is a plain source COPY from the build context (repo root) — it is
# Laravel's standard public web root, not composer/build-generated, so no
# cross-stage copy from php.dockerfile is needed.

FROM nginx:1.31.9-alpine@sha256:90b6bd2aec278b16b98221d64deb1d6d35d164567f507ab0716e6053878315aa

COPY server/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY src/be/public /var/www/html/be/public

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -q --spider http://127.0.0.1/up || exit 1
