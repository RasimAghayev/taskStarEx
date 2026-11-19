# Deployment

> Phase 15. Describes the system as it actually exists today — a single
> dev/local Docker Compose topology. There is no staging/production
> environment, no cloud deployment target, and no CI publish-on-tag job
> for this repo (deliberate: `docs/adr/0005-no-ghcr-publish-for-interview-task-repo.md`).
> Nothing below is invented or aspirational; where this repo genuinely has
> no "real" environment, that is stated plainly rather than papered over.

## Environments

| Environment | Exists today? | Notes |
|---|---|---|
| Local / dev (Docker Compose) | Yes | The only environment this repo currently has. `docker-compose.yml` at repo root. |
| CI (GitHub Actions) | Yes | Builds and validates every image on every PR to `preview`/`test`/`main`; never publishes anywhere. See `.github/workflows/ci.yml`, `docs/TEST_REPORT.md`. |
| Staging | No | Not built. Phase 16 (platform/umbrella repo) is marked `n/a` for this repo (`docs/IMPROVEMENT_ROADMAP.md`) — no multi-service platform need was identified. |
| Production | No | Same as above. If this repo is ever pointed at a real cluster/domain, that is Approval Gate 9 territory (CLAUDE.md) — not pre-approved, not assumed here. |

## Images

Three images are built from this repo, none of them published to any
registry (GHCR or otherwise):

| Dockerfile | Target(s) | What it is |
|---|---|---|
| `php.dockerfile` | `build`, `test`, `runtime` | Laravel API (`src/be`), served by php-fpm |
| `node.dockerfile` | `build`, `test`, `runtime` | Nuxt SPA (`src/fe`), built to `.output/` |
| `nginx.dockerfile` | (single stage) | Reverse proxy fronting `php` on `/api/*`; config at `server/nginx/default.conf` |

`make test` / CI's `*-test` jobs run `docker build --target test`, which
fails the build itself (non-zero exit) if the real test suite fails —
there is no separate "run tests, ignore the result" step.

## Standing up the stack

```bash
make env       # copies .env.example -> .env at root, src/be, src/fe (if missing)
make verify    # test stage (both apps) + build all compose images — the gate
make up        # = verify + migrate-free `docker compose up -d`
make migrate   # first run only: applies database/migrations/*
make seed      # first run only: seeds one user you can log in with
```

`make up` depends on `make verify` — the stack is never started on an
unverified build.

## Services and ports (docker-compose.yml)

Reserved block for this repo: **20250–20259**
(`projects/info/state/ports.md`).

| Service | Published port (env var, default) | Container port | Notes |
|---|---|---|---|
| `nginx` | `APP_PORT` (`20250`) | 80 | Entry point for the API (`/api/*`) |
| `fe` | `FE_PORT` (`20251`) | 3000 | Nuxt SPA |
| `pgsql` | `POSTGRES_PORT` (`20252`) | 5432 | PostgreSQL 18.7 (pinned by digest) |
| `php` | not published | 9000 (fpm) | Only reachable via `nginx`; depends on `pgsql` being healthy |

Two dev-only "tool runner" services (`php-dev`, `fe-dev`) exist under the
`tools` Compose profile — they are not started by `docker compose up`;
see the Makefile's `shell`/`shell-fe`/`lint`/`stan`/`migrate`/`seed`
targets for how they're invoked.

## Environment variables

### Root `.env` (Compose interpolation only)

| Var | Default | Purpose |
|---|---|---|
| `POSTGRES_DB` | `laraveldb` | |
| `POSTGRES_USER` | `laravel` | |
| `POSTGRES_PASSWORD` | `changeme-generate-your-own` | **Placeholder only** — generate a real one per `.env.example`'s own comment; never reuse this value anywhere real. |
| `POSTGRES_PORT` | `20252` | |
| `APP_PORT` | `20250` | nginx published port |
| `FE_PORT` | `20251` | Nuxt published port |
| `NUXT_PUBLIC_API_URL` | `http://localhost:20250/api` | Frontend → backend URL, as seen from the browser |

### `src/be/.env` (Laravel)

Standard Laravel vars (`APP_KEY` — generate with `make key`, `APP_DEBUG`,
`DB_*`, etc.) plus this repo's own additions:

| Var | Default | Purpose |
|---|---|---|
| `CORS_ALLOWED_ORIGINS` | `http://localhost:20251` | Comma-separated list of origins allowed to call `/api/*` cross-origin (`config/cors.php`). Must match the real frontend origin(s) in any real deployment — **never** `*`. |

`DB_CONNECTION` defaults to `sqlite` in `.env.example` (so the backend can
run its test stage with zero external services); switch to the commented
`pgsql` block to point it at the Compose `pgsql` service instead.

### `src/fe` — no `.env.example` committed

`nuxt.config.ts` reads one variable at runtime,
`NUXT_PUBLIC_API_URL` (public, non-secret — a URL, not a credential). It
is supplied by `docker-compose.yml`'s `fe` service `environment:` block,
not a `src/fe/.env` file. This is a documented gap (`docs/TECHNICAL_DEBT.md`),
not a secrets violation.

## Secrets

- No secret is committed anywhere in this repo (`gitleaks dir . --redact`,
  re-run Phase 3/4; GitHub secret scanning + push protection enabled on
  the repo itself — see `docs/SECURITY_REVIEW.md`).
- `docker-compose.yml`'s Postgres credentials are a local-only,
  placeholder dev value (not a live leak) but are intentionally
  env-driven (`POSTGRES_PASSWORD:?Set ... in .env`) rather than
  hardcoded, so Compose refuses to start without a value being set.
- Real secrets (`APP_KEY`, a real `POSTGRES_PASSWORD`) must come from a
  `.env` file (gitignored) or a secret manager if this ever deploys
  somewhere real — never hardcoded, per standing decision on secrets.

## Rollback

There is no deployment pipeline to roll back (no publish-on-tag, no real
environment). "Rollback" today means: `git revert`/`git checkout` to a
prior commit and re-run `make verify && make up` locally. If a real
deployment target is ever added, this section is the first thing a new
Phase 16 (platform-engineer) pass should replace with a real rollback
procedure — not assumed here.
