# syntax=docker/dockerfile:1.7
#
# nginx reverse-proxy/static-file front for the Laravel backend (src/be).
# public/ is a plain source COPY from the build context (repo root) — it is
# Laravel's standard public web root, not composer/build-generated, so no
# cross-stage copy from php.dockerfile is needed.

FROM nginx:1.31.8-alpine@sha256:fed7e14be83cbd2661a75cdc5b872d936cf155d46c327084d30b8df19ae88d72

COPY server/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY src/be/public /var/www/html/be/public

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -q --spider http://127.0.0.1/up || exit 1
