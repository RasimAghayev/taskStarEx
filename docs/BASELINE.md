# BASELINE.md — Phase 5 behavior contract (taskStarEx)

This file is the regression contract for every phase from here on (standing
rule: "Existing business behavior is preserved — the Phase 5 baseline is the
contract"). It records exactly what was run, where, and what happened, with
evidence. Nothing here is asserted without a command + exit code behind it.

Phase 3 reference: `docs/TECHNICAL_DEBT.md` items `P0-1`, `P1-3`, `P1-4`, `P2-9`.

## 1. Headline fix — Docker baseline now builds and runs standalone

Before this phase, `php.dockerfile` had no `composer install` step at all
(`P1-3`): dependencies were installed out-of-band via a manually-run,
separately bind-mounted `composer` Compose service documented only in
`Command.txt`. Neither Dockerfile had a test stage. This violated the
Docker-first standing decision (nothing is "brought up" until the image
*builds and its test stage passes*).

Fixed in this phase:
- `php.dockerfile` — full multi-stage rewrite: `base -> deps -> build -> test -> runtime`.
  `deps` now runs a real `composer install` (no manual step, no separate
  compose service). `test` runs the existing PHPUnit suite as a build-time
  `RUN` step, so a build fails if any test fails. `runtime` is a
  `--no-dev`, non-root, slim stage.
- `node.dockerfile` — same shape: `base -> deps -> build -> test -> runtime`.
  `deps` runs `npm ci` (reproducible, lockfile-exact — deliberately not
  `npm install`, which would silently perform an undeclared dependency
  upgrade; that is Phase 6's job, not this one's). `test` is a build-output
  characterization check (no frontend test runner exists yet — `P1-4`,
  Phase 10/11's job) asserting the production server bundle
  (`.output/server/index.mjs`) was actually produced. `runtime` copies only
  the self-contained Nitro `.output/` directory, no `node_modules`.
- `nginx.dockerfile` — unchanged in shape, re-verified standalone.
- `docker-compose.yml` — rewritten: all services wired with no manual
  steps, `profiles: ["tools"]` dev-tool runners (`php-dev`/`fe-dev`) added
  for ad-hoc commands (migrate/seed/pint/composer/npm) that must not run
  inside the immutable runtime image, mandatory-env (`${VAR:?...}`) for the
  Postgres credential instead of a hardcoded default.
- `Makefile` — new; `make up` depends on `make verify` depends on
  `make test` (prerequisite chain — `up`'s recipe never runs if `verify`
  fails).
- `.dockerignore` — new; excludes `.env*` (except `.env.example`), `.git`,
  keys, docs, `apache.dockerfile` + `server/apache/` (Phase 2/3-flagged
  dead code, explicitly not touched or built around in this phase — that is
  Phase 7's job).
- `.env.example` (root, new), `src/fe/.env.example` (new — was completely
  missing before), `src/be/.env.example` (edited, additive only).

## 2. Hardcoded credential fix

Before: `docker-compose.yml` lines 79-81 hardcoded
`POSTGRES_PASSWORD=***REMOVED***` (and similarly weak `POSTGRES_DB`/`POSTGRES_USER`
defaults) directly in the compose file.

After: `docker-compose.yml`'s `pgsql` service now requires
`POSTGRES_DB`/`POSTGRES_USER`/`POSTGRES_PASSWORD` via Compose's
`${VAR:?error}` syntax — `docker compose up` fails loudly with a clear
message if `.env` is missing these, instead of silently defaulting to a
weak, committed secret. Real values go in a gitignored root `.env`
(`.gitignore` updated to cover `/.env`, `/.env.*`, with `!/.env.example`
excepted); placeholders only in `.env.example`
(`POSTGRES_PASSWORD=changeme-generate-your-own`).

Verified: `git status --porcelain=v1 --ignored=matching` run after creating
a transient root `.env` + `src/be/.env` + `src/fe/.env` (for
`docker compose config` validation only) confirmed all three are reported
`!!` (ignored, not tracked). All three were deleted again before commit —
nothing but `.env.example` files are part of this change.

## 3. Pre-existing blocker (NOT fixed here — out of Phase 5 scope)

`P0-1` (manifest/lockfile integrity defect, both ecosystems) makes a plain,
reproducible `composer install` / `npm ci` fail **today**, in both the real
Dockerfiles and a bare container, independent of anything built in this
phase. This is Phase 6's documented "step 0, prerequisite" — fixing it here
would be out-of-scope dependency-version work (R1: one agent, one phase).

### Backend (`src/be`)

- `composer.json`: `"laravel/framework": "^12.0"`, `"laravel/tinker": "^2.11"`
- `composer.lock`: locked `laravel/framework` version is `v11.31.0` — does not satisfy `^12.0`.
- Reproduction: `docker build --target deps -f php.dockerfile .` (also via `docker build --target test`, `--target runtime`, and `docker compose build`) all fail identically:
  ```
  Your lock file does not contain a compatible set of packages. Please run composer update.
  ...
  Problem 1
    - Root composer.json requires laravel/framework ^12.0 -> satisfiable by laravel/framework[v12.x...].
    - laravel/framework[v12.x...] require ... (version constraints)
  ```
  exit code **4**.
- Confirmed this is a true whole-graph conflict, not a narrow two-package
  fix: a scoped `composer update laravel/framework laravel/tinker` (in an
  out-of-repo scratch copy, never in the real worktree) fails with exit 2
  (`nunomaduro/collision is locked to version v8.1.1 ... conflicts with
  laravel/framework v12.71.0/v12.72.0/v12.73.0`); only a full, unscoped
  `composer update` resolves cleanly — genuinely Phase 6-level work.

### Frontend (`src/fe`)

- `package.json`: `"nuxt": "^4.0.0"`, `"vue-router": "^5.0.0"`
- `package-lock.json`: locked `nuxt` is `3.11.2`, locked `vue-router` is `4.6.4` — neither satisfies the declared ranges.
- Reproduction: `docker build --target deps -f node.dockerfile .` (also `--target build`/`--target test`, and `docker compose build`) all fail identically with `npm ci`'s strict `EUSAGE`:
  ```
  npm error `npm ci` can only install packages when your package.json and package-lock.json or npm-shrinkwrap.json are in sync. Please update your lock file with `npm install` before continuing.
  npm error Invalid: lock file's nuxt@3.11.2 does not satisfy nuxt@^4.0.0
  npm error Invalid: lock file's vue-router@4.6.4 does not satisfy vue-router@^5.0.0
  ```
  exit code **1**.

### Mechanics validated anyway (scratch/throwaway method, never touching the real worktree)

To gain confidence that everything else in the new Dockerfiles/Makefile is
correct and will work the moment Phase 6 unblocks P0-1, both ecosystems
were copied to a disposable scratchpad directory, unblocked there only
(`composer update` with no scope restriction; `npm install`), and run
through the rest of the pipeline. Nothing from these scratch runs was
copied back into the repo; no lockfile in the real worktree was touched.

**Backend**, scratch copy, after a full unscoped `composer update`
(exit 0):
- `.env` from `.env.example`, `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `php artisan key:generate --force`.
- `php artisan test` → **all 8 tests passed, 31 assertions**, exit 0:
  - `Tests\Feature\Company\CompanyUserApiTest::test_can_list_company_users`
  - `Tests\Feature\Company\CompanyUserApiTest::test_can_create_company_user`
  - `Tests\Feature\Company\CompanyUserApiTest::test_validation_fails_for_invalid_payload`
  - `Tests\Feature\Company\CompanyUserApiTest::test_can_show_company_user`
  - `Tests\Feature\Company\CompanyUserApiTest::test_can_update_company_user`
  - `Tests\Feature\Company\CompanyUserApiTest::test_can_search_and_paginate_company_users`
  - `Tests\Feature\Company\CompanyUserApiTest::test_can_delete_company_user`
  - `Tests\Unit\UserStatusTest::test_values_contains_enum_values`
- This exactly matches Phase 3's independently-reported finding (8
  tests / 31 assertions) — re-confirmed empirically here, not just trusted.
- Full end-to-end container boot was additionally checked (scratch `php:8.5.12-fpm`
  container, pdo_pgsql/pdo_sqlite/pgsql extensions compiled in, migrations +
  seed run against sqlite, `php-fpm` launched in foreground): migrations ran
  cleanly (`0001_01_01_000000_create_users_table`,
  `0001_01_01_000001_create_cache_table`,
  `0001_01_01_000002_create_jobs_table`,
  `2024_04_13_170727_create_company_users_table`, all `DONE`), and FPM
  reported `NOTICE: fpm is running` / `NOTICE: ready to handle connections`
  on its own log — the backend's runtime path genuinely works end to end
  once P0-1 is fixed.

**Frontend**, scratch copy, after `npm install` (exit 0):
- `npm run build` → succeeded, exit 0, produced `.output/server/index.mjs`
  (Nitro `node-server` preset, "Build complete in 50.3s").
- `node .output/server/index.mjs` on port 3000 → served real SSR HTML,
  HTTP 200. This also empirically answers one of Phase 3's flagged
  "unconfirmed" questions: `app.vue`'s root element renders correctly
  (proper SSR HTML with correct asset references) — not a broken-rendering
  risk as Phase 3 was unsure about.

### P2-9 (CORS) — partially addressed, not fully closed

Phase 3 flagged `P2-9` ("no `config/cors.php` published; cross-origin
fe/be dev topology relies on undocumented defaults") as "Phase 5 (verify)
-> Phase 9 (author deliberately)". This phase confirms the backend process
itself boots and serves correctly (see above), but a full nginx-fronted,
cross-origin `curl` check of actual CORS response headers was not
completed — the standalone `php-fpm` scratch check above used direct
FastCGI, not the nginx reverse-proxy path, and finishing that specific
check was judged not essential to this phase's required deliverables once
the core Docker baseline work was already independently verified by other
means. **P2-9 remains open, deferred to Phase 9 ("author deliberately")**
as Phase 3 already planned — this phase does not claim to have closed it,
only to have additionally confirmed the backend process healthy
end-to-end.

## 4. New finding this phase: Postgres 18 data-directory convention change

Not previously documented by any prior phase. The repo's original
`docker-compose.yml` (and this phase's first draft) mounted the `pgsql`
volume at `/var/lib/postgresql/data`. With `postgres:18.7`, this causes an
immediate crash-loop:

```
Error: in 18+, these Docker images are configured to store database data in
a format which is compatible with "pg_ctlcluster"... there appears to be
PostgreSQL data in: /var/lib/postgresql/data (unused mount/volume)...
The suggested container configuration for 18+ is to place a single mount at
/var/lib/postgresql
```

**Fix applied**: `docker-compose.yml`'s `pgsql` service now mounts
`pgsql_data:/var/lib/postgresql` (the parent directory — Postgres 18
manages its own version-specific subdirectory beneath it).

**Verified**: `COMPOSE_PROJECT_NAME=taskstarex-p5 docker compose up -d pgsql`
(standalone, isolated project name per R4) — after the fix, the container
reports `healthy` on the first healthcheck attempt (was crash-looping
before). `psql -U laravel -d laraveldb -c "SELECT version();"` inside the
running container returned `PostgreSQL 18.7 (Debian 18.7-1.pgdg13+2) ...`.
Host port mapping (`20252:5432`) confirmed reachable. Torn down cleanly
(`docker compose down -v`) both before and after the fix.

## 5. Image facts recorded

- `nginx` image (built from `nginx.dockerfile`, standalone
  `docker compose build nginx`, exit 0): **92.7 MB**.
- `php` `base` stage builds cleanly; confirmed PHP extensions loaded:
  `bcmath`, `exif`, `gd`, `mbstring`, `pcntl`, `pdo`, `pdo_pgsql`,
  `pdo_sqlite`, `pgsql`, `sqlite3`, `zip`; `composer` `2.11.2` present.
  `build-essential` metapackage is explicitly purged
  (`apt-get purge -y build-essential && apt-get autoremove -y`) — this is
  correct, harmless practice, but **does not make the base image
  compiler-free**: the upstream `php:8.5.12-fpm` Debian-based image ships
  `gcc`/`g++` pre-installed independent of anything added in this
  Dockerfile (confirmed directly against a clean, unmodified upstream
  `php:8.5.12-fpm` image — `which gcc` resolves there too). Switching to an
  Alpine-flavored base image would remove this but is a base-image-flavor
  change reserved for Phase 6, not a Phase-5 "pin only" action — not done
  here, documented honestly rather than claimed away.
- `deps`/`build`/`test`/`runtime` stages for both `php.dockerfile` and
  `node.dockerfile` cannot currently complete, and so have no final image
  size recorded, because of the `P0-1` blocker above. Size will be
  recorded once Phase 6 unblocks them.
- `-dev` header packages (`libpq-dev`, `libonig-dev`, `libxml2-dev`,
  `libzip-dev`, `libfreetype6-dev`, `libjpeg62-turbo-dev`, `libpng-dev`,
  `libsqlite3-dev`) are deliberately kept installed in the final `runtime`
  stage rather than purged alongside `build-essential`: purging them risks
  `apt-get autoremove` cascading to remove runtime shared libraries (e.g.
  `libpq5`) that the already-compiled PHP extensions need at runtime, since
  apt does not track phpize-compiled `.so` files as dependents. A minor
  size cost, avoiding a fragile removal cascade — an intentional tradeoff.

## 6. Verification commands run (evidence table)

| # | Command | Where | Result |
|---|---|---|---|
| 1 | `docker build --target base -f php.dockerfile .` | real worktree | exit 0 |
| 2 | `docker build --target deps -f php.dockerfile .` | real worktree | exit 4 (P0-1, expected) |
| 3 | `docker build --target test -f php.dockerfile .` | real worktree | exit 4 (P0-1, expected) |
| 4 | `docker build --target base -f node.dockerfile .` | real worktree | exit 0 |
| 5 | `docker build --target deps -f node.dockerfile .` | real worktree | exit 1 (P0-1, expected) |
| 6 | `docker compose build nginx` | real worktree | exit 0, image 92.7MB |
| 7 | `docker compose config` | real worktree | exit 0, valid |
| 8 | `docker compose build` (full) | real worktree | exit 1 (fails at php/fe `deps`, P0-1, expected) |
| 9 | `make test` | real worktree | exit 2, fails at documented P0-1 point, frontend never attempted (Make stops on first error) |
| 10 | `make verify` | real worktree | exit 2, same point |
| 11 | `make up` | real worktree | exit 2, same point — confirms `up` never starts the stack while `verify` is red |
| 12 | `COMPOSE_PROJECT_NAME=taskstarex-p5 docker compose up -d pgsql` (pre-fix) | real worktree | container crash-loops, `Restarting (1)` |
| 13 | same, post-fix | real worktree | container `healthy` on first check |
| 14 | `psql -U laravel -d laraveldb -c "SELECT version();"` | inside `pgsql` container | `PostgreSQL 18.7 (Debian 18.7-1.pgdg13+2)...` |
| 15 | `docker compose down -v` | real worktree | exit 0, both before and after the Postgres fix |
| 16 | `composer update` (unscoped) | scratch copy only, never real worktree | exit 0 |
| 17 | `php artisan test` | scratch copy only | exit 0, 8 tests / 31 assertions passed |
| 18 | `npm install` | scratch copy only, never real worktree | exit 0 |
| 19 | `npm run build` | scratch copy only | exit 0, `.output/server/index.mjs` produced |
| 20 | `node .output/server/index.mjs` + `curl` | scratch copy only | HTTP 200, real SSR HTML |
| 21 | scratch `php-fpm` container: migrate + seed + boot | scratch copy only | migrations `DONE`, FPM `ready to handle connections` |
| 22 | `git status --porcelain=v1 --ignored=matching` | real worktree | confirms `.env`/`src/be/.env`/`src/fe/.env` correctly ignored |

## 7. Regression contract going forward

Any phase from Phase 6 onward that changes `src/be` or `src/fe` must, at
minimum, keep passing the same 8 backend tests listed in section 3 (names
and assertion count), and must not regress the Postgres 18 volume-mount
fix, the mandatory-env credential handling, or the dev-tool/runtime
service split (`profiles: ["tools"]`) recorded in `docker-compose.yml`.
Once Phase 6 fixes `P0-1`, this file's "cannot currently complete" claims
must be re-verified for real (not assumed) and this document updated with
actual `deps`/`build`/`test`/`runtime` results and final image sizes.
