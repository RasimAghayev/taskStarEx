# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
for its own releases where applicable.

## [Unreleased]

### Security — Gate 1 history rewrite: remove literal secret from git history (2026-11-19)

- Removed the literal value `POSTGRES_PASSWORD=<REDACTED>` (introduced at
  `f8805cb`, fixed forward in the live file at `5f8dba6`, but never
  removed from history) from the git history of `main`, `test`, and
  `preview` via `git filter-repo --replace-text`
  (`POSTGRES_PASSWORD=<REDACTED>==>POSTGRES_PASSWORD=***REMOVED***`),
  approved by the repo owner as Gate 1 ("Tarixdən təmizlə —
  Gate 1 — force-push").
- Verified before force-pushing: tip tree hashes byte-identical
  before/after on all three branches (`main`/`test`
  `3b607aa9581d8f9e2b41d3c222efd77e5ea785e6`, `preview`
  `f2f201dbd865beb7a6e44f4dba9aeeaed02d61cf`), commit counts unchanged
  (`main` 222, `test` 222, `preview` 214), zero remaining occurrences of
  the literal string anywhere in the rewritten history.
- Force-pushed one branch at a time (`main` then `test` then `preview`),
  each cycle temporarily relaxing branch protection
  (`enforce_admins:false`, `allow_force_pushes:true`), pushing, then
  restoring it to the exact original snapshot
  (`enforce_admins:true`, `allow_force_pushes:false`,
  `allow_deletions:false`) before moving to the next branch. New tips:
  `main` `037c698ed71970e696f78d9583e9d8ccfdef9bd5`, `test`
  `64be5fa7279a89327b2b37f47a91da22d2ed2bdd`, `preview`
  `492d91847475a66e221241182a45919eb67f8327`.
- Live re-verification against freshly-fetched `origin` refs confirmed
  `f8805cb` (the commit that introduced the secret) is no longer an
  ancestor of any of the three branches, and `make verify` (Docker
  build + test stage) passed, exit 0, on the rewritten `test` tip.
- Two consumer-facing side effects of the pre-rewrite cleanup (not the
  rewrite itself): PR #103 was merged to land the forward compose fix
  on `main` before the rewrite started, and PRs #105/#106/#107 redacted
  two docs files' prose (`docs/REPOSITORY_AUDIT.md`,
  `docs/BASELINE.md`) that quoted the literal value, so the mechanical
  content-level rewrite would not also mangle current, accurate
  documentation.
- Full evidence (exit codes, before/after tables, protection diffs):
  `projects/info/state/handoff/4/taskStarEx-gate1-secret-history-rewrite.md`.
  Technical-debt record: `docs/TECHNICAL_DEBT.md` P3-10.

### Changed — resolve `test`↔`main` merge conflict ahead of first promotion (2026-11-19)

- Merged `main` into `test` (via `chore/resolve-test-main-conflict`) to
  converge the two branches' independent, parallel edits to
  `php.dockerfile`, `src/be/composer.json`, and `src/be/composer.lock`
  (plus a modify/delete conflict on `.github/workflows/laravel.yml`,
  surfaced only once the merge actually ran) ahead of the repo's first
  `test`→`main` promotion (PR #103).
- `php` runtime floor `^8.4` → `^8.5` — a re-checked decision, not a
  default to either branch's existing value. See `docs/TECHNICAL_DEBT.md`
  P2-8's 2026-11-19 addendum for the full registry-check evidence
  (PHP 8.5 confirmed as the current latest active-support branch;
  `php:8.5.12-fpm` confirmed as the latest available Docker Hub tag and
  already what this repo's own `php.dockerfile`/CI run on; every
  `require`/`require-dev` package's latest version re-checked against
  Packagist, none capping below 8.5). `laravel/framework` stays `^13.0`
  (resolved `v13.43.0`) and `laravel/sanctum`/`larastan` stay declared —
  `main`'s own `^12.0`, no-sanctum state (from its direct PR #97) is
  superseded, per PR #103's own stated intent.
- `src/be/composer.lock` regenerated from scratch (`composer update`
  inside the project's own `php.dockerfile` `base` stage, i.e. the real
  `php:8.5.12-fpm` image, not a throwaway substitute) against the merged
  manifest. Every package resolved to its true current latest within its
  declared range (`laravel/pint` 1.32.3, `laravel/sail` 1.71.0,
  `nunomaduro/collision` 8.9.6, `phpunit/phpunit` 13.4.8,
  `spatie/laravel-ignition` 2.13.0, `fakerphp/faker` 1.24.1) — confirming
  the manifest's existing require-dev floor text didn't need bumping to
  get latest-compatible resolutions.
- `.github/workflows/laravel.yml` deleted (kept test's side of the
  modify/delete conflict) — it was already superseded by
  `.github/workflows/ci.yml` in Phase 10; `main`'s PR #97 had kept
  patching the old file without that context.
- `php.dockerfile`: kept `test`'s multi-stage
  `base -> deps -> build -> test -> runtime` version wholesale — `main`'s
  single-`FROM` conflicting line was already redundant with the `base`
  stage's own `php:8.5.12-fpm` pin.

### Security

- `composer audit` on the regenerated lockfile: "No security vulnerability
  advisories found" — `CVE-2026-114509` (Laravel TokenGuard auth bypass,
  the original reason for this repo's lockfile-regeneration discipline,
  `docs/TECHNICAL_DEBT.md` P0-2) stays out of range at `laravel/framework
  v13.43.0`.
- `docker build --target test -f php.dockerfile .`: exit 0, **66 passed
  (185 assertions)** — unchanged from the pre-existing regression floor
  (`docs/BASELINE.md` §8), confirming zero regression from the floor bump
  or the lockfile regeneration.

### Added — Phase 15 docs, manifest, diagrams (2026-11-19)

- Full community-standards file set, none of which previously existed in
  this repo: `CODE_OF_CONDUCT.md` (Contributor Covenant 2.1),
  `CONTRIBUTING.md`, `SECURITY.md`, `SUPPORT.md`, `ACCESSIBILITY.md`,
  `.github/CODEOWNERS`, `.github/ISSUE_TEMPLATE/{bug,feature,docs}.md`,
  `.github/PULL_REQUEST_TEMPLATE.md`.
- `docs/DEPLOYMENT.md`, `docs/OPERATIONS.md`, `docs/SECURITY_REVIEW.md`,
  `docs/DIAGRAMS.md` (Mermaid: system context, cross-repo dependency
  chain, 5 core-flow sequence diagrams, data model `erDiagram`,
  deployment topology) — all derived from the real code/config (routes,
  controllers, migrations, `docker-compose.yml`), not invented; every
  diagram cites the source file(s) it was read from.
- `repo-manifest.yaml` (repo root) — `type: service`, `provides.api:
  openapi.yaml`, `license: MIT` (matches `LICENSE`); `depends_on`/
  `related` both confirmed empty against
  `projects/info/DEPENDENCY_MAP.md` (no other `RasimAghayev` repo
  depends on, or is depended on by, taskStarEx).
- `README.md` rewritten from scratch — it previously contained only the
  original interview-task brief, not a real project README. Now covers
  what the app does, Docker-first setup, usage examples, real testing
  numbers (cites `docs/TEST_REPORT.md`), and links to every doc above. A
  live CI badge was added (`actions/workflows/ci.yml/badge.svg`); no
  Docker-image-size or coverage badge was added since neither a
  published image (see ADR-0005) nor a coverage-reporting service exists
  for this repo to source one from live — real measured coverage numbers
  are given as text instead, not faked as a badge.
- `docs/DEPENDENCY_DECISIONS.md`: added a decision entry ratifying the
  existing MIT `LICENSE` choice (it was already MIT from this repo's
  inception; this phase is the first to record *why*, per standing
  decision on license choice needing a written decision entry).
- Verified live (not assumed from an earlier phase's note,
  `gh api repos/RasimAghayev/taskStarEx`): `secret_scanning`,
  `secret_scanning_push_protection`, and `dependabot_security_updates`
  are `enabled`; private vulnerability reporting is `enabled`. Reflected
  accurately in `SECURITY.md`/`docs/SECURITY_REVIEW.md`.
- Real, concrete accessibility findings recorded in `ACCESSIBILITY.md`
  (not boilerplate): icon-only edit/delete buttons in
  `src/fe/pages/company/users/index.vue` have no `aria-label`; no
  `lang` attribute is configured in `src/fe/nuxt.config.ts`; no
  automated accessibility tooling runs in CI or `make lint-fe`. None of
  these are fixed in this phase (Phase 15 is docs-only, per this
  program's R1) — tracked here as real, verified gaps.

### Known program-level gap (not this phase's to fix)

- `tools/generate_graph.*`, which is supposed to auto-generate
  `projects/info/ECOSYSTEM.md` and this README's "Use together with"
  section from `repo-manifest.yaml`, does not exist yet anywhere in this
  program (`tools/` currently holds only `_msgfilter.py` and
  `templates/`). `repo-manifest.yaml` was still authored per spec so the
  tool has something correct to consume once it exists; the "Use
  together with" section and `ECOSYSTEM.md` entry were written by hand
  in the meantime, reflecting the manifest honestly (empty
  `depends_on`/`related`). Flagged in this phase's handoff as a
  program-level gap, not invented or silently worked around.

### Tests — Phase 11 test suite (2026-11-19)

- **Backend**: 11 new tests added (55/161 → 66/185 assertions, zero
  regression). `UserPolicyTest` (unit) locks in the real
  authorization model documented in ADR-0004 (five methods `true`,
  `restore`/`forceDelete` fail-closed). `CompanyUserExportTest` gained
  two integration tests proving the export endpoint's CSV-injection
  defense actually fires for data that reached the database outside
  normal HTTP validation, and for a legitimately-created email starting
  with a trigger character. `CompanyUserImportTest` gained two tests for
  the previously-uncovered upload-hardening rules (`mimes:csv,txt`,
  `max:5120`).
- **Frontend**: first real test suite in this repo's history. Added
  `vitest`/`happy-dom`/`@vitest/coverage-v8` (dev-only;
  `docs/DEPENDENCY_DECISIONS.md`), `src/fe/vitest.config.ts`, and
  `src/fe/tests/unit/**` covering `services/authService.js`,
  `services/companyUsersService.js`, and `constants/userStatus.js`
  (including a value-for-value contract test against the backend's
  `UserStatus` enum, per `docs/adr/0002-...md`'s documented no-runtime-link
  risk). `node.dockerfile`'s `test` stage now runs `npm run test` before
  its pre-existing build-output characterization check.
- **Docs**: `docs/TESTING_STRATEGY.md` added; `docs/TEST_REPORT.md`
  extended with a Phase 11 section (Phase 10's CI-timing report kept
  as-is); `docs/BASELINE.md` addendum correcting its stale "8 tests"
  figure to the real, current regression floor.
- See `projects/info/state/handoff/11/taskStarEx.md` for full evidence,
  coverage numbers, and an incident note about out-of-scope commits
  found (and discarded, not built upon) on this phase's intended branch
  name before this phase's real work started.

### CI/CD — Phase 10 pipeline (2026-11-18)

- **`.github/workflows/ci.yml` added, replacing the backend-only
  `laravel.yml`.** Single consolidated pipeline covering both app roots,
  per the cross-phase CI/CD runner-time standard:
  - `changes` (path-filter, `dorny/paths-filter`): gates every job below
    on whether `src/be`/`src/fe` actually changed — a frontend-only PR no
    longer pays for a backend Docker build and vice versa.
  - `backend-quality` / `frontend-quality` (parallel, cheap, first):
    `pint --test` + Larastan (backend), `@nuxt/eslint` (frontend) — the
    exact checks Phase 9 wired as `make lint`/`make stan`/`make lint-fe`,
    run directly against `docker build --target build` (GitHub Actions
    cache, `type=gha`, so layers are reused across jobs/runs rather than
    rebuilt from scratch every time).
  - `backend-test` / `frontend-test`: wire the already-proven
    `docker build --target test` for `php.dockerfile`/`node.dockerfile`
    (Phase 5/6/9's own Docker-first test stages) as real CI jobs — no
    reinvented test runner.
  - `backend-audit` / `frontend-audit`: `composer audit --locked` +
    `npm audit` for both `src/be` and `src/fe`'s npm manifests. Policy:
    any new critical/high finding blocks the job; the one standing
    accepted-risk advisory (`GHSA-fc5q-39xc-vjpf`, picomatch ReDoS, no
    upstream fix — `docs/TECHNICAL_DEBT.md` P1-5) is allow-listed by GHSA
    ID so it does not perpetually fail CI; low/medium are reported only.
  - `docker-build-validate`: builds all three `runtime`-target images
    (`php`/`fe`/`nginx`) plus `docker compose config -q` — validates the
    images a real deployment would use, without publishing anywhere (see
    `docs/adr/0005-no-ghcr-publish-for-interview-task-repo.md`,
    `docs/TECHNICAL_DEBT.md` P3-9).
  - Hygiene: `concurrency`/`cancel-in-progress` on PRs; `timeout-minutes`
    on every job; top-level `permissions: contents: read` (no job touches
    secrets — none exist in this pipeline — so fork PRs are unaffected);
    every new action pinned by resolved commit SHA
    (`dorny/paths-filter@v4.0.3`, `docker/setup-buildx-action@v4.4.1`,
    `docker/build-push-action@v7.4.0`, `actions/setup-node@v7.1.0`),
    matching the existing `actions/checkout`/`shivammathur/setup-php`
    pins already established in Phase 6.
  - Triggers repointed to the 3-branch model: `pull_request`/`push` on
    `preview`, `test`, and `main` (the old workflow only ran against
    `main`, a leftover from before Phase 4 built the 3-branch model).
- **`style(be)`: `./vendor/bin/pint` fix applied once** (18 pre-existing
  style violations across 64 files, found only because this phase wired
  `pint --test` into CI for the first time — `make lint` existed since
  Phase 9 but had never actually been run/verified). Purely cosmetic
  (import ordering, `(new X())`→`(new X)`, collapsed empty constructor
  bodies, `!$x`→`! $x`); zero behavior change, 55/55 tests still pass
  (161 assertions, byte-for-byte unchanged). See `docs/TECHNICAL_DEBT.md`
  P3-8.
- **`fix(ci)`: provision `.env` before `docker compose config -q`.**
  `docker-build-validate`'s compose-validation step ran on a bare
  checkout with no `.env` (gitignored by design) and failed closed on
  the `pgsql` service's required `POSTGRES_DB`/`USER`/`PASSWORD`
  interpolation — exactly correct behavior for an actual deploy, wrong
  for a config-lint step. Fixed by running the pre-existing `make env`
  target (copies `.env.example` → `.env`, placeholders only, no
  secrets) immediately before the validate step, mirroring what `make
  up` already does for a human developer. Caught by watching the real
  first CI run go red (PR #92, run `44281068441`), not by local
  reasoning about the YAML — local `docker build`/`docker compose`
  testing never exercised a checkout without a pre-existing `.env`.
- Measured real CI run time and the slowest jobs: `docs/TEST_REPORT.md`.

### Security — Phase 9 hardening (2026-11-18)

- **P0 fixed: real authentication + authorization on every
  `/api/company/users/*` route** (full PII, previously reachable
  unauthenticated by anyone — `docs/TECHNICAL_DEBT.md` P1-1). Added
  `laravel/sanctum` `^4.5` (resolved `v4.5.0`), token-based (not SPA/
  cookie); `POST /api/login` / `POST /api/logout` / `GET /api/user`
  added; every `/api/company/users/*` route (CRUD + the Phase 8B export/
  import/audit additions) now requires `auth:sanctum`.
  `App\Policies\Company\UserPolicy` (fixed fail-closed in Phase 8,
  ADR-0001) now wired for real via `$this->authorize()` in every
  `UserController`/`UserAuditController` action — single-authenticated-
  actor model (every real method returns `true`; this app has no role/
  ownership concept to encode a finer rule against, per KISS/YAGNI). See
  `docs/adr/0004-wire-authentication-and-authorization.md`.
- `App\Observers\Company\UserAuditObserver`: `performed_by` now populated
  (`auth('sanctum')->user()?->email`) now that a real authenticated actor
  exists — was `null` for every row before this phase.
- `config/cors.php` published (`docs/TECHNICAL_DEBT.md` P2-9, previously
  undocumented engine defaults): env-driven `allowed_origins`
  (`CORS_ALLOWED_ORIGINS`, default the dev Nuxt container's port), not a
  wildcard; `supports_credentials: false` (bearer-token auth, not
  cookies).
- Rate limiting added — **none existed before this phase**: a general
  `api` limiter (60/min, `bootstrap/app.php`'s `throttleApi()`), a
  tighter `login` limiter (5/min per IP, brute-force target), and an
  `import` limiter (10/min per authenticated user, CSV bulk-write
  surface) — `App\Providers\AppServiceProvider::configureRateLimiting()`.
- CSV-injection (formula-injection) defenses added on both Phase 8B CSV
  endpoints (`App\Support\Csv\CsvSafety`, new): `GET
  /api/company/users/export` now sanitizes any cell starting with
  `=`/`+`/`-`/`@`/tab/CR (prefixes `'`) before writing it; `POST
  /api/company/users/import` **rejects** (does not silently rewrite) any
  row with such a value in any column, since mutating submitted data on
  the way in would be a correctness bug, not a safety fix.
  `email`'s local-part may legitimately start with `+`/`-` per RFC
  5321/5322 and still pass Laravel's own `email` validation rule, which
  is why this check is field-agnostic.
- `App\Http\Requests\Company\ImportUsersRequest`: upload hardening —
  `max:5120` (5 MB) and `mimes:csv,txt` + content-sniffed
  `mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel`
  (neither existed before this phase; previously any file of any size/
  type was accepted and only failed later on CSV parsing).

### Added — Phase 9 hardening (2026-11-18)

- `GET /health/live` and `GET /health/ready` (liveness/readiness,
  registered outside the `web`/`api` middleware groups in
  `bootstrap/app.php`, same convention as Laravel's built-in `/up`).
  `/health/ready` checks the database connection (`DB::select('select 1')`)
  and returns 503 on failure, logging the exception.
- `openapi.yaml` (repo root): full current API surface, including every
  Phase 8B endpoint and the new auth/health routes.
- `src/be/phpstan.neon.dist` + `make stan` (Larastan, level 5) — this
  repo had zero static-analysis tooling before this phase (only
  formatting via `laravel/pint`). Initial run found 18 real findings
  (all genuine Larastan/Laravel-ecosystem annotation gaps, zero
  behavior change to fix): `@mixin` annotations on `UserResource`/
  `UserAuditLogResource` (a `JsonResource`'s dynamic property proxying
  is otherwise invisible to static analysis), `@property` docblocks on
  `App\Models\Company\User`/`UserAuditLog`, a `@method` annotation on
  `App\Enums\UserStatus` for its reflection-based `description()`, and
  a `list<string>` docblock correction on the stock
  `App\Models\User::$fillable`/`$hidden`. Re-run: 0 errors.
- `src/fe`: `@nuxt/eslint` `^1.17.0` + `eslint` `^10.15.0`, `eslint.config.mjs`,
  `npm run lint` / `make lint-fe` — zero frontend lint tooling existed
  before this phase. Initial run found 19 real findings (3 errors, 16
  warnings): 16 auto-fixable `vue/html-self-closing` style warnings
  (applied via `eslint --fix`); `src/fe/layouts/default.vue`'s bare
  `<slot/>` template root (invalid per `vue/no-multiple-template-root`,
  fixed by wrapping in one `<div>`; this file is confirmed dead code,
  see `docs/TECHNICAL_DEBT.md` P3-7 — not removed, out of this phase's
  scope); two targeted, documented `eslint-disable-next-line` comments
  for a known `eslint-plugin-vue` false positive on Vuetify's dotted
  data-table slot names (`#item.status_label`, `#item.actions`).
  See `docs/DEPENDENCY_DECISIONS.md` for both new-dependency entries.
- Minimal frontend auth wiring so the existing SPA stays usable now that
  the backend requires a token: `src/fe/pages/login.vue`,
  `src/fe/services/authService.js`, a bearer-token request interceptor
  + 401 handler in `src/fe/plugins/axiosApiClient.ts`, a client-side
  route guard (`src/fe/middleware/auth.global.js` — UX convenience only,
  the real enforcement is server-side), and a sign-out action in
  `app.vue`'s nav drawer.
- Structured logging: failed login attempts and successful login/logout
  (`App\Http\Controllers\Auth\AuthController`), readiness-check database
  failures (`HealthController`), and CSV import completion with its
  per-row created/failed counts (`UserController::import()` — row-level
  messages deliberately omitted from the log, since they may contain
  user-submitted values).
- Tests: `CompanyUserAuthorizationTest` (one unauthenticated-401
  assertion per `/company/users/*` route), `AuthApiTest` (login success/
  failure/validation/rate-limit, `/user`, logout token revocation),
  `HealthEndpointTest`, `CsvSafetyTest` (unit), a formula-injection-
  rejection test in `CompanyUserImportTest`, and a `performed_by`
  population test in `CompanyUserAuditLogTest`. Every pre-existing test
  that calls a now-protected route updated to authenticate via
  `Sanctum::actingAs()` (`tests/TestCase::actingAsApiUser()`).

### Fixed — Phase 9 hardening (2026-11-18)

- `php.dockerfile`'s runtime `HEALTHCHECK` always reported unhealthy
  (`pgrep -x php-fpm`, but the image has no `procps`), silently blocking
  `docker-compose.yml`'s `nginx` service (`depends_on: php: condition:
  service_healthy`) from ever starting even though php-fpm was actually
  running fine. Replaced with a real FastCGI request to `/health/live`
  via `cgi-fcgi` (`libfcgi-bin`) — proves the app actually answers a
  request, not just that a process exists. See `docs/TECHNICAL_DEBT.md`
  P2-10.
- **Real 500 found by this phase's own manual Docker smoke test** (not
  by the test suite — every existing test uses `getJson()`/`postJson()`,
  which always send `Accept: application/json`): an unauthenticated
  request to any `/api/company/users/*` route made without that header
  crashed with `RouteNotFoundException: Route [login] not defined`
  instead of returning 401. Cause: Laravel's own
  `Illuminate\Foundation\Configuration\ApplicationBuilder` registers a
  default guest-redirect callback (`Authenticate::redirectUsing(fn () =>
  route('login'))`) regardless of app-level config, and this app — a
  pure Sanctum-token JSON API with no web/session guard — has no
  `login`-named route for it to resolve. Fixed in `bootstrap/app.php`:
  `$middleware->redirectGuestsTo(fn () => null)` removes the crash, and
  a `$exceptions->render()` callback for `AuthenticationException`
  guarantees the same `{"success":false,"message":"Unauthenticated."}`
  JSON 401 regardless of the caller's `Accept` header. In
  `APP_DEBUG=true` this bug also leaked a full framework stack-trace
  page (Ignition) to an unauthenticated caller — an information-
  disclosure risk on top of the availability one. Regression test:
  `CompanyUserAuthorizationTest::test_index_requires_authentication_even_without_an_accept_json_header`
  (uses plain `get()`, not `getJson()`). Verified against the real
  running stack (`docker compose up`, real `curl`, no test-harness
  shortcuts): unauthenticated → 401, login → token, authenticated → 200,
  logout → 200, reused revoked token → 401.
- Three real `docker build --target test` failures found and fixed, none
  by weakening or skipping a test:
  - `CsvSafetyTest`'s `@dataProvider` PHPDoc annotation is silently
    ignored by PHPUnit 13 (PHPDoc-based test metadata was removed, not
    merely deprecated) — the test ran with 0 arguments instead of 6.
    Fixed with the `#[DataProvider('triggerCharacterProvider')]`
    attribute, the only mechanism this PHPUnit version supports.
  - `AuthApiTest::test_logout_revokes_the_current_token` failed because
    `Illuminate\Auth\AuthManager` caches a resolved guard per name for
    its own lifetime, and `Illuminate\Auth\RequestGuard` caches the user
    it resolves on first use — both caches live on objects that, in real
    production, exist for exactly one HTTP request (PHP-FPM boots a
    fresh container per request), but are shared across the test
    method's several simulated `postJson()`/`getJson()` calls. Fixed in
    the test only (`Auth::forgetGuards()` between the logout call and
    the final assertion) — not a production bug; confirmed separately by
    the real multi-request smoke test above that token revocation
    genuinely works outside the test harness.
  - `HealthEndpointTest`'s DB-unreachable test failed with a Mockery
    `BadMethodCallException` on `getConnections()` — Laravel's own test
    teardown (`disconnectDatabaseConnections()`) calls that method on
    whatever `db` resolves to, which after `DB::shouldReceive(...)` is
    the same partial mock, so it needs its own expectation too. Fixed by
    adding `DB::shouldReceive('getConnections')->andReturn([])`.

### Added — Phase 8B feature enhancement (2026-11-18, Gate 12 approved)

- **CSV export of the company user list.** `GET /api/company/users/export`
  (accepts the same `search` query parameter as the existing `index()`
  endpoint, no page cap) streams every matching row as
  `fin,name,surname,father,dob,email,status,status_label` via Laravel's
  built-in `response()->streamDownload()` — no new dependency. Frontend:
  an "Export CSV" button on `company/users/index.vue`'s toolbar. See
  `projects/info/state/handoff/8B/taskStarEx.md` for the full acceptance
  criteria and verification evidence.
- **CSV bulk import of company users.** `POST /api/company/users/import`
  (multipart `file`) accepts a CSV with the exact header
  `fin,name,surname,father,dob,email,status` and validates every row with
  the same rules `StoreUserRequest` already enforces for a single create
  — no duplicated validation logic. A structurally wrong header is
  rejected up front (422) before any row is touched; once the header is
  accepted, a bad row (failed validation, in-file duplicate `fin`/email,
  or duplicate against an existing row) is reported per row with its row
  number and message and does not abort the batch — the response is
  `{created, failed, errors[]}`. Frontend: an "Import CSV" button on
  `company/users/index.vue`'s toolbar with an inline results panel. See
  `projects/info/state/handoff/8B/taskStarEx.md` for the full acceptance
  criteria and verification evidence.
- **Change-history / audit log on company users.** Every `create`/`update`/
  `delete` on a `company_users` row now writes exactly one row to the new
  `company_user_audit_logs` table (`fin, action, changes (JSON), performed_by,
  created_at`) via a Laravel model observer (`App\Observers\Company\UserAuditObserver`,
  registered on `App\Models\Company\User`) — `UserController`/`UserRepository`
  are completely unchanged. `changes` records only the fields that actually
  changed on an update (old/new), and the full field snapshot on create/delete.
  Deliberately **no foreign key** to `company_users.fin`, so a deleted user's
  history survives the hard delete that removed it. `performed_by` is left
  `null` for now — no authentication exists yet (Phase 9's job); Phase 9
  backfills it going forward once real auth lands. An audit-write failure is
  caught and logged, never allowed to block or roll back the business
  operation it observes. New read-only `GET /api/company/users/{fin}/audit`
  (paginated, newest first) keeps working after the parent row is deleted.
  Frontend: a read-only "History" panel on the edit page. See
  `projects/info/state/handoff/8B/taskStarEx.md` for the full acceptance
  criteria and verification evidence.

### Changed — Phase 8 architecture (2026-11-18)

- `src/be/app/Policies/Company/UserPolicy.php`: fixed — every one of its 7
  methods had a `: bool` return type with no `return` statement at all
  (would throw `TypeError` if ever invoked). Each now explicitly
  `return false;` (fail-closed/deny-by-default placeholder; this repo has
  no role/ownership model yet to encode a real rule, and this class is
  still not wired into any route/controller — that remains Phase 9's job).
  Also removed an unused `use Illuminate\Auth\Access\Response;` import.
  Kept, not deleted — it already sits at Laravel's policy-auto-discovery
  convention location for `App\Models\Company\User`, so Phase 9 can wire
  it in with zero registration boilerplate. See `docs/adr/0001-fix-and-keep-userpolicy.md`.
- `src/fe/pages/company/users/create.vue`, `src/fe/pages/company/users/[fin].vue`:
  the identical, hand-duplicated 8-entry status dropdown (`statusItems`)
  is now sourced from one new module, `src/fe/constants/userStatus.js`
  (`USER_STATUS_OPTIONS`), instead of being declared twice. No behavior
  change — the rendered options are byte-for-byte identical. See
  `docs/adr/0002-status-option-list-shared-constant.md`.

### Removed — Phase 8 architecture (2026-11-18)

- `pinia` dependency (`src/fe`) — added in Phase 6 only to satisfy
  `vue-router@5`'s *optional* peer dependency; independently re-confirmed
  this phase (reading `vue-router`'s own `peerDependenciesMeta`) that it
  is not actually required, and re-confirmed zero usage anywhere in the
  codebase. No present feature needs client-side shared state. Removed
  via `npm uninstall pinia` inside the exact `node:26.13.1-alpine` image
  `node.dockerfile` uses — diff scoped to exactly 1 package, 0 orphaned
  sub-dependencies. `npm audit` high-severity count unchanged (41,
  pre-existing unfixed-upstream `picomatch`). See
  `docs/adr/0003-remove-unused-pinia-dependency.md`.

### Removed — Phase 7 dead-code removal (2026-11-18)

- `apache.dockerfile` + `server/apache/httpd-vhosts.conf` — confirmed unreferenced anywhere (CI, Makefile, `docker-compose.yml`, `Command.txt`, README) by Phase 2/3 and independently re-confirmed here by repo-wide grep. The real web server is the `nginx` service (`nginx.dockerfile` + `server/nginx/default.conf`). The two now-dangling `.dockerignore` entries for these paths were removed in the same commit.
- `src/fe/components/DatePicker.vue` — confirmed unused: both consuming pages (`create.vue`, `[fin].vue`) use a plain `v-text-field type="date"` instead. Re-confirmed independently via grep (catches Nuxt's auto-registered-component template usage, not just explicit imports) and via `knip` (Nuxt-aware static analysis, run in Docker), which also lists it under "Unused files". Was the only file under `src/fe/components/`.
- `vuex` dependency (`src/fe/package.json`) — confirmed zero usage: no `store/` directory, zero `vuex`/`useStore`/`createStore`/`$store`/`mapState`/`mapGetters`/`mapActions`/`mapMutations` references anywhere. Independently re-confirmed by three methods: grep, `depcheck`, and `knip` (both run in Docker against the `node:26.13.1-alpine` base image used by `node.dockerfile`). This is dead-dependency removal only — **not** a Vuex→Pinia migration; there was never an actual store to migrate. `pinia` (added in Phase 6) is left untouched; its real wiring, if any, is Phase 8 scope.

**Tooling cross-check, not acted on:** both `depcheck` and `knip` also flag `vue-router` and `pinia` as "unused" by their static-analysis heuristics. Neither was removed:
- `vue-router` is a false positive — `app.vue` uses the `<router-view/>` primitive (confirmed by grep), and Nuxt's file-based `pages/` routing depends on `vue-router` internally with no explicit source-level import to detect. Removing it would break routing. It is also explicitly out of this phase's scope (kept at `^5.0.0` per the repo owner's Phase 4/6 decision).
- `pinia` is genuinely unused today (no `createPinia()`/`defineStore` call anywhere) but was deliberately added in Phase 6 to keep the `vue-router@5` dependency graph consistent; its real wiring (if any) is Phase 8 territory, not Phase 7's.

Per standing decision 6, "dead is proven by tooling + tests, not guessed" — this is also why `vue-router`/`pinia` were left alone rather than removed on a tooling guess that conflicts with known framework behavior and prior-phase decisions.

**Structural realignment considered, no action taken:**
- Laravel 13 keeps the streamlined application skeleton Laravel 11 introduced (`bootstrap/app.php` + `bootstrap/providers.php`, no `app/Http/Kernel.php`/`app/Console/Kernel.php`) — this repo already uses exactly that structure (confirmed by reading `bootstrap/app.php`). No real mismatch exists to realign.
- Nuxt 4's new default `srcDir` is `app/`, but this is the default for *newly scaffolded* projects only; Nuxt auto-detects and continues supporting the flat `pages/`/`components/`/`layouts/`/`plugins/` layout this repo already uses, and migration is explicitly optional (confirmed against Nuxt's own upgrade guidance). The current flat layout is already proven building and serving correctly under Nuxt 4 in Docker (Phase 5/6). Moving the tree into `app/` now would be speculative restyling with no present problem to solve — KISS/YAGNI (standing decision 7) says leave it.

### Changed — Phase 6 dependency upgrade (2026-11-18)

- **BREAKING (dependency major, pre-approved by standing decision 1):** `laravel/framework` `^12.0` → `^13.0` (resolved `v13.43.0`). Clears `CVE-2026-114509` (Laravel TokenGuard auth bypass), which was in range at both the previously-locked `v11.31.0` and the manifest's own `^12.0` floor. No migration needed beyond the framework bump itself — the existing test suite passed unmodified.
- **BREAKING (dependency major):** `laravel/tinker` `^2.11` → `^3.0` (resolved `3.2.0`).
- **BREAKING (dependency major):** `phpunit/phpunit` `^11.0.1` → `^13.0`. No test-suite migration needed — the suite already used plain `test_*` method naming, not deprecated doc-comment `@test` annotations.
- `php` runtime floor `^8.2` → `^8.4`, ahead of `8.2`'s 2026-12-31 upstream EOL and matching `spatie/laravel-ignition`'s own `^8.4` requirement (pulled in transitively by the framework bump).
- CI (`.github/workflows/laravel.yml`): PHP matrix version `8.3` → `8.5` (kept in step with the new runtime floor and phpunit's own `>=8.4.1` requirement); `actions/checkout` and `shivammathur/setup-php` re-pinned from floating major-version tags (`@v7`, `@v2`) to resolved commit SHAs.
- `docker/dockerfile` BuildKit syntax directive `1.7` → `1.29` in `php.dockerfile`, `node.dockerfile`, `nginx.dockerfile`.
- Dev-only Composer packages (pint, sail, mockery, collision, spatie/laravel-ignition, fakerphp/faker) moved forward within their already-declared manifest ranges as a side effect of the framework bump's whole-graph resolution.
- **BREAKING (dependency major, pre-approved by standing decision 1):** `nuxt` `^4.0.0` declared manifest range finally honored by the lockfile (previously locked at a stale `3.11.2`); resolved `4.6.4`. Vuetify 4 / `vite-plugin-vuetify` 2.x compatibility with Nuxt 4's Vite changes verified via a green Docker build.
- **BREAKING (dependency major):** `vue-router` `^5.0.0` declared manifest range (set by the repo owner directly, 2026-11-17) finally honored by the lockfile (previously still resolving `^4.x`); resolved `5.5.1`.
- **Added** `pinia` `^4.0.0` (resolved `4.1.0`) — required for a consistent `vue-router@5` dependency graph (`vue-router@5` lists `pinia` as a peer, formally optional but needed here for a genuinely consistent resolution). Declared and locked only; **not wired into any component this phase** — see `docs/DEPENDENCY_DECISIONS.md` and `docs/TECHNICAL_DEBT.md` P1-2. The existing `vuex` dependency is unaffected and stays in place; its removal is scoped, unstarted Phase 7/8 work (a real Vuex→Pinia migration), not part of this change.
- `axios` and `vuetify` forced to true latest (`1.21.1`, `4.3.4`) via an explicit `npm install <pkg>@latest --save`, since a plain `npm install` on the pre-existing lockfile silently preserved older-but-still-range-satisfying resolutions (`1.20.0`, `4.3.3`).
- Added an `overrides` entry forcing `minimatch@^11.0.1` on `src/fe`, fixing a real ReDoS advisory in a transitive dependency of Nuxt's internal toolchain (0 moderate findings remaining after re-audit).
- Generated `src/be/package-lock.json` for the first time (none previously existed for the Vite asset-pipeline manifest), making its dependency state auditable; reproduces 6 already-known, unfixed-upstream `picomatch` ReDoS findings (no new issue).
- Added `pinia` decision entry and the `src/fe` npm vulnerability-reduction evidence to `docs/DEPENDENCY_DECISIONS.md`.

### Security

- `composer audit --locked` on `src/be`: 1 critical / 16 high / 29 medium / 7 low / 6 unspecified → **0 findings** (CVE-2026-114509 cleared).
- `npm audit` on `src/fe`: 70 vulnerable packages / 5 critical → 44 / **0 critical**. All 44 remaining findings traced (via full `via`-chain analysis, not the flat per-package severity list) to a single root advisory, `picomatch` ReDoS (`GHSA-fc5q-39xc-vjpf`), with no upstream fix available at any current version of `vite`/`laravel-vite-plugin`/`nuxt` — documented as an accepted residual risk in `docs/TECHNICAL_DEBT.md`.
- `src/be`'s previously-unauditable Vite asset pipeline (no lockfile existed) now has a lockfile and reproduces the same already-known, unfixed `picomatch` findings — confirmed not a new or worse problem.

### Dependency bot housekeeping

- Closed as superseded by this phase's own commits: `#82` (docker/dockerfile syntax bump — applied directly instead, since this phase's agent does not merge PRs itself), `#78` (laravel/tinker v3), `#74` (nuxt 3→4), `#73` (axios latest), `#72` (vue-router 5.5.1).
- Declined/closed, not merged: `#81` (proposed reverting `vue-router` to v4 — contradicts the repo owner's confirmed 2026-11-18 decision to keep v5), `#83` (proposed nginx `1.30.6`→`1.31.8` — `1.30.6` is nginx.org's current **stable** release and `1.31.8` is current **mainline**; standing decision 1 calls for latest stable, so no change).
