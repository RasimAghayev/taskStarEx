# syntax=docker/dockerfile:1.29
#
# nginx reverse-proxy/static-file front for the Laravel backend (src/be).
# public/ is a plain source COPY from the build context (repo root) — it is
# Laravel's standard public web root, not composer/build-generated, so no
# cross-stage copy from php.dockerfile is needed.

FROM nginx:1.30.6-alpine@sha256:901e593c95dc85f5a729f5765516321366ef4ac401821431d408897e95287d0a

COPY server/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY src/be/public /var/www/html/be/public

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -q --spider http://127.0.0.1/up || exit 1
