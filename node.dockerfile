# syntax=docker/dockerfile:1.29
#
# taskStarEx frontend (src/fe, Nuxt) — multi-stage build.
# Stages: base -> deps -> build -> test -> runtime. See docs/BASELINE.md for
# the pre-existing manifest/lockfile drift (nuxt ^4.0.0 / vue-router ^5.0.0
# declared but not locked) that originally blocked `deps` onward — resolved
# by Phase 6.
#
# `test` (Phase 11): runs the real Vitest unit suite (`npm run test`, see
# vitest.config.ts) added this phase — before this, zero frontend tests
# existed anywhere in this repo. The pre-existing build-output
# characterization check (confirming the production server bundle was
# actually produced) is kept as a second, independent assertion — it
# caught a real class of defect (a broken build) the unit suite alone
# would not, since the unit suite never calls `nuxt build`.
#
# Build context is the repository root (see docker-compose.yml: context: .).

FROM node:26.13.1-alpine@sha256:6bd9ab43c0ec0190ca7b2d193906cb8b7edf6e7f4afa43ddeccd6452710bffb5 AS base
RUN apk add --no-cache libc6-compat
WORKDIR /var/www/html/fe

# ---- deps: reproducible install from the committed lockfile ---------------
FROM base AS deps
COPY src/fe/package.json src/fe/package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    apk add --no-cache --virtual .build-deps python3 make g++ \
    && npm ci \
    && apk del .build-deps

# ---- build: full source, production bundle ---------------------------------
FROM deps AS build
COPY src/fe/ ./
RUN npm run build

# ---- test: real unit suite (Phase 11) + build-output characterization ----
FROM build AS test
RUN npm run test
RUN test -f .output/server/index.mjs

# ---- runtime: only the built output, no node_modules/dev tooling ----------
FROM base AS runtime
ENV NODE_ENV=production \
    PORT=3000 \
    NITRO_PORT=3000 \
    HOST=0.0.0.0 \
    NITRO_HOST=0.0.0.0
COPY --from=build /var/www/html/fe/.output ./.output
EXPOSE 3000
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -q --spider http://127.0.0.1:3000/ || exit 1
USER node
ENTRYPOINT ["node", ".output/server/index.mjs"]
