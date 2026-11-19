![Views](https://hits.sh/github.com/RasimAghayev/taskStarEx.svg?label=views&color=0e75b6)
[![CI](https://github.com/RasimAghayev/taskStarEx/actions/workflows/ci.yml/badge.svg?branch=preview)](https://github.com/RasimAghayev/taskStarEx/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

# taskStarEx

A small company user registry: Laravel REST API backend + Nuxt/Vue 3
frontend, CRUD over one business entity (`company_users`) plus CSV
export/import and a change-history audit log. Started as an interview
take-home assignment (see `docs/ARCHITECTURE.md` for the original brief);
this README documents the system **as it actually exists today**, not the
original spec.

> Qısa xülasə (AZ): Şirkət işçilərinin qeydiyyatını aparan kiçik bir
> tətbiqdir — Laravel API backend və Nuxt/Vue 3 frontend. CRUD
> əməliyyatları, CSV ixrac/idxal və dəyişiklik tarixçəsi (audit log)
> funksiyalarını dəstəkləyir. Bütün endpointlər token əsaslı
> autentifikasiya (Laravel Sanctum) tələb edir.

## What it does

- **Authentication** — token-based (Laravel Sanctum personal-access
  tokens), one actor type, no self-service registration (`POST /api/login`,
  `POST /api/logout`).
- **Company user CRUD** — `GET/POST/PUT/DELETE /api/company/users[/{fin}]`,
  with server-side search and pagination. Every record is keyed by `fin`
  (a 7-character Azerbaijan national-ID-style identifier), plus
  name/surname/father/dob/email/status.
- **CSV export** — `GET /api/company/users/export`, streamed, with
  CSV-injection-safe cell sanitization.
- **CSV bulk import** — `POST /api/company/users/import`, per-row
  validation (same rules as a single create), partial-success reporting,
  formula-injection and duplicate-row rejection.
- **Audit log** — `GET /api/company/users/{fin}/audit`: a read-only,
  newest-first change history per user, written automatically by an
  Eloquent model observer on every create/update/delete — including after
  the underlying `company_users` row has been hard-deleted.
- **Health probes** — `GET /health/live`, `GET /health/ready` (checks the
  database).

Full endpoint-level contract: [`openapi.yaml`](openapi.yaml). Design and
module-level detail: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Stack

- Backend: PHP 8.5 / Laravel 13, PostgreSQL 18, Laravel Sanctum, Larastan
  (static analysis), Pint (style), PHPUnit 13.
- Frontend: Nuxt 4 / Vue 3, Vuetify, Axios, ESLint (`@nuxt/eslint`),
  Vitest + happy-dom.
- Everything builds, tests, and runs in Docker (`php.dockerfile`,
  `node.dockerfile`, `nginx.dockerfile`, `docker-compose.yml`) — see
  standing decision 2, Docker-first.

## Getting started (Docker-first)

Nothing here is "brought up" until the image builds and its test stage
passes — a run on the bare host is never treated as verification.

```bash
git clone https://github.com/RasimAghayev/taskStarEx.git
cd taskStarEx
make verify   # builds the test stage for both src/be and src/fe; fails
              # the build itself if a test fails — see docs/BASELINE.md
make up       # make up depends on make verify; starts the full stack
```

`make up` starts: `nginx` (:`APP_PORT`, default `20250`, fronting the
Laravel API at `/api/*`), `fe` (:`FE_PORT`, default `20251`, the Nuxt
app), and `pgsql` (:`POSTGRES_PORT`, default `20252`). First run only:

```bash
make migrate   # run database migrations
make seed      # seed a user you can log in with
```

See [`make help`](Makefile) for every target (`shell`/`shell-fe` dev
shells, `lint`/`stan`/`lint-fe`, `security`, `clean`). Full environment
variables and port reservations: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

## Usage

**API** (see `openapi.yaml` for the full contract):

```bash
# Log in, get a Sanctum token
curl -X POST http://localhost:20250/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"..."}'

# Authenticated request
curl http://localhost:20250/api/company/users \
  -H 'Authorization: Bearer <token>'
```

A Postman collection is also included: [`taskStarEx.postman_collection.json`](taskStarEx.postman_collection.json).
Its requests target the real endpoints correctly; its embedded `pm.test()`
assertions are generic boilerplate left over from a template and do not
assert this API's actual response shape — useful as a manual request
reference, not as contract tests (see `docs/REPOSITORY_AUDIT.md`).

**Web UI**: open `http://localhost:20251` after `make up`, log in, and use
the company-user list page (search, pagination, CSV export/import
buttons) and the create/edit pages.

## Testing

```bash
make test    # runs the Docker test stage for both src/be and src/fe
```

Current measured state (see [`docs/TEST_REPORT.md`](docs/TEST_REPORT.md)
for full detail and exact commands/exit codes — these are real, executed
numbers, not estimates):

- Backend (PHPUnit): 66 tests / 185 assertions passing. Code coverage is
  **not measured** — no coverage driver (`pcov`/`xdebug`) is installed in
  the PHP image; this is a documented, known gap, not a hidden one.
- Frontend (Vitest): 19 tests passing, 97.14% statement coverage on the
  plain-JS modules under test (`services/`, `constants/`) — see
  `docs/TEST_REPORT.md` for the per-file table and the one documented
  uncovered line.

There is no public coverage-reporting service (Codecov/Coveralls) wired
for this repo, and no published Docker image (see
`docs/adr/0005-no-ghcr-publish-for-interview-task-repo.md`), so this
README does not carry a coverage or image-size badge — a real number with
nowhere public to source it live is not a substitute for one, and an
invented badge is explicitly against this program's standards. The CI
badge above is live and real.

## Documentation

- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — environments, ports,
  env vars, how to actually stand this up.
- [`docs/OPERATIONS.md`](docs/OPERATIONS.md) — health checks, logging,
  rate limits, runbook-style notes for running this day to day.
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — module/data-flow detail.
- [`docs/SECURITY_REVIEW.md`](docs/SECURITY_REVIEW.md) — security posture
  as implemented.
- [`docs/DIAGRAMS.md`](docs/DIAGRAMS.md) — system context, data model,
  core flows, deployment topology (Mermaid).
- [`docs/TECHNICAL_DEBT.md`](docs/TECHNICAL_DEBT.md) /
  [`docs/DEPENDENCY_DECISIONS.md`](docs/DEPENDENCY_DECISIONS.md) — what's
  been decided and why.
- [`CHANGELOG.md`](CHANGELOG.md) — Keep a Changelog format.

## Community

- [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md) (Contributor Covenant 2.1)
- [`CONTRIBUTING.md`](CONTRIBUTING.md)
- [`SECURITY.md`](SECURITY.md) — private vulnerability reporting
- [`SUPPORT.md`](SUPPORT.md)
- [`ACCESSIBILITY.md`](ACCESSIBILITY.md)

## License

MIT — see [`LICENSE`](LICENSE). Decision record:
[`docs/DEPENDENCY_DECISIONS.md`](docs/DEPENDENCY_DECISIONS.md#license-mit--ratified-phase-15-2026-11-19).

## Use together with

No other `RasimAghayev` repository is a dependency of, or depends on,
taskStarEx — confirmed in `projects/info/DEPENDENCY_MAP.md` (Phase 2) and
in this repo's own `repo-manifest.yaml` (`depends_on`/`related` both
empty). This section is generated from `repo-manifest.yaml` by
`tools/generate_graph.*`; as of this phase that tool does not yet exist in
this program (see this phase's handoff) — the empty state above is
written by hand in the meantime and matches the manifest honestly.
