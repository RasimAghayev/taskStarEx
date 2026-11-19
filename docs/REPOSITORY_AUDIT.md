# Repository Audit

> Phase 3 full-discovery audit. All findings are derived from real code/config in this repo at `master` (`6e9bb01`), Phase 2's dependency data (`projects/info/deps/taskStarEx.md`), and live checks (`gh`, `gitleaks`) run during this phase. Nothing below is inferred from memory of similar projects.

## Scope and method

- Full read of both app roots (`src/be` Laravel, `src/fe` Nuxt), their config/env files, routes, migrations, models, repository/policy/resource/request classes, tests, Docker files, CI workflow, and the Postman collection.
- Dependency currency/vulnerability data is inherited from Phase 2 (`projects/info/deps/taskStarEx.md`) and cited, not re-run, per the orchestrator's directive.
- This phase made **no code changes** and ran **no build/install/container** commands — read-only discovery, consistent with the Phase 3 skill's scope. A redacted secret scan of the working tree (`gitleaks`) and `gh`-based GitHub-metadata checks were run as live verification, both read-only.

## Dependency analysis (summary — full detail in `projects/info/deps/taskStarEx.md`)

- **Manifest/lockfile integrity defect in both ecosystems.** `src/be/composer.json` declares `laravel/framework ^12.0`/`laravel/tinker ^3.0`; `composer.lock` resolves `v11.31.0`/`v2.9.0` — neither satisfies the manifest's own range. `src/fe/package.json` declares `nuxt ^4.0.0`; `package-lock.json` resolves `3.11.2`. Root cause (Laravel side): Renovate commit `f0fe87a` bumped only `composer.json`, never regenerated the lock.
- **1 critical CVE already in range at the locked version**: `CVE-2026-114509` (Laravel TokenGuard auth bypass), affecting `>=11.0.0,<11.56.1` and `>=12.0.0,<12.68.0` — the locked `11.31.0` and even the manifest's declared `^12.0` floor are both vulnerable.
- **70 vulnerable npm packages on `src/fe`, 5 critical** (`nuxt`, `@nuxt/devtools`, `shell-quote`, `simple-git`, `tar`), all `fixAvailable: true`, attributable to the stale lock.
- **6 high findings on `src/be`'s unlocked Vite asset pipeline** (`picomatch` ReDoS, `GHSA-fc5q-39xc-vjpf`), unfixed even at the newest `vite`/`laravel-vite-plugin`.
- **`vue-router` 4→5 is blocked**, not merely pending: v5 requires `pinia`; this repo uses `vuex` (itself unused). 3 reverted Renovate cycles already happened (PRs #56-#65); a 4th attempt (`PR #67`) is open and unmerged as of this audit.
- `laravel/tinker` v3↔v2.11.1 has also churned 3 times (PRs #59-#63) with no confirmed registry-level cause (unconfirmed, not asserted); a further attempt (`PR #66`) is open now.
- No copyleft anywhere: all 110 locked Composer packages are MIT/BSD-3-Clause; all 8 direct npm packages are MIT/Apache-2.0.
- No registry tokens, no `.npmrc`/`auth.json` secrets anywhere in the repo (Phase 2).

## Config review

| File | Finding |
|---|---|
| `src/be/.env.example` | Placeholders only — compliant. Default `DB_CONNECTION=sqlite` in the example doesn't match the real dev stack's Postgres service — a documentation mismatch, not a security issue. |
| `src/fe` | No `.env.example` at all, despite reading `process.env.NUXT_PUBLIC_API_URL` at runtime (a public URL, not a secret) — documentation gap. |
| `docker-compose.yml:79-81` | Hardcoded dev Postgres credentials (`POSTGRES_DB=laraveldb`, `POSTGRES_USER=laravel`, `POSTGRES_PASSWORD=<REDACTED>`) directly in the committed compose file. Placeholder-style value, local-only port `6432` — not a live leak, flagged by location per the letter of the secrets policy. |
| `src/be/config/auth.php` | Only a `web`/session guard defined. No `api`/`sanctum` guard exists, despite `routes/api.php` referencing `auth:sanctum`. |
| `src/be/config/` | No `cors.php` published — CORS behavior for the cross-origin fe/be dev topology relies on undocumented framework defaults. |
| `src/be/bootstrap/app.php` | `withExceptions()` closure is empty — no custom JSON-shaped error mapping; unauthenticated-API 404/500 behavior on edge cases (e.g. unknown `fin`) is unconfirmed without a live run. |
| `.gitignore` (root) | Covers `/.idea`, `/.vscode`, `.vscode/*`, env files, build output appropriately — but see Git hygiene below for a file the ignore rule doesn't retroactively catch. |

## Code quality findings

- **Repository pattern is correctly wired**: `UserRepositoryInterface` → `UserRepository`, bound in `RepositoryServiceProvider`, registered in `bootstrap/providers.php`. No DI defect.
- **`UserPolicy` (`app/Policies/Company/UserPolicy.php`) is dead scaffolding**: all 6 methods declare `: bool` but have empty bodies with no `return` statement — would throw `TypeError` if ever invoked. Confirmed never invoked anywhere (no `authorize()`/`Gate::` call references it in `app/` or `routes/`).
- **No auth/authz middleware on the real CRUD routes** (`routes/api.php:11`) — the single biggest concrete security gap in the repo.
- **Validation is genuinely solid**: `StoreUserRequest`/`UpdateUserRequest` apply regex-constrained names, a FIN format check, email/FIN uniqueness (correctly `->ignore()`'d on update), and enum-constrained status, with a `prepareForValidation()` normalization step (uppercase/trim the FIN). `ApiFormRequest::failedValidation()` deliberately forces a JSON 422 rather than Laravel's default redirect — a correct, intentional API-contract choice.
- **Default Laravel auth scaffold is fully inert**: Sanctum is not installed (confirmed via structural `composer.lock` parse and a `git log -p -- composer.json | grep sanctum` history search — zero hits, ever), no `sanctum` guard is registered, and the `/user` route that depends on it would error if hit. Coexists with, but is entirely disconnected from, the real `company_users` feature.
- **`DatePicker.vue` is unused** — neither `create.vue` nor `[fin].vue` import it; both use a plain `v-text-field type="date"` instead.
- **Status-list duplication**: the 8-option status dropdown (value + Azerbaijani label) is hand-copied verbatim between `create.vue` and `[fin].vue`, duplicating information the backend already computes once (`UserStatus`'s `#[Description]` attributes, surfaced as `status_label` in `UserResource`).
- **`vuex` is a declared, unused dependency** — the original task spec required it; the implementation uses local component state instead, without removing the dependency.
- No N+1 query risk identified — single flat table, no relations, no loops over queries.
- The one dynamic query (`UserRepository::index()`'s search) is correctly parameterized (`where($operator, $term)` via Eloquent's query builder) — no SQL injection risk.

## Security scan

- **Redacted secret scan of the working tree** (`gitleaks dir . --redact --no-banner`, this phase): exit 0, "no leaks found".
- Phase 2's prior checks (cited, not re-run): no registry tokens anywhere in the repo; no `.npmrc`/`auth.json`/`.yarnrc*` present.
- **Authentication/authorization**: none on the business CRUD routes — see Code quality findings above. This is the repo's primary security finding.
- **CVE exposure**: see Dependency analysis above — 1 critical already in-range at both the locked version and the manifest's own declared floor.
- **Secrets policy**: one hardcoded dev-only Postgres credential (placeholder value, local port only) — see Config review.

## CI/CD status

- One workflow, `.github/workflows/laravel.yml`: checkout → `shivammathur/setup-php@v2` (PHP 8.3) → copy `.env` → `composer install` → `key:generate` → chmod storage dirs → SQLite DB → `php artisan test`. Backend-only.
- **No frontend CI job exists at all** — nothing lints, type-checks, builds, or tests `src/fe` or `src/be`'s npm asset pipeline in CI.
- Both CI actions (`actions/checkout@v7`, `shivammathur/setup-php@v2`) are pinned by floating major-version tag, not by commit SHA — consistent with the program-wide gap recorded for every other repo in `DEPENDENCY_MAP.md`.
- No dependency/image vulnerability scanning step in CI.
- No `.github/dependabot.yml` — standing decision 14 not yet satisfied. Two stale 2024-vintage Dependabot PRs (`#20` nuxt→3.12.4, `#27` rollup→4.22.4) are still open, proving Dependabot ran here at some point.

## Git hygiene

- Default branch is `master` (GitHub-confirmed via `gh repo view`), not `main` — standard rename candidate, Gate 4 applies.
- GitHub Discussions and Wiki are both **already enabled** (confirmed via `gh repo view` this run) — standing decision 13 compliant, no action needed.
- `.vscode/settings.json` is **tracked in git** despite `.gitignore` excluding `/.vscode` and `.vscode/*` — the file predates the ignore rule and was never untracked. Violates the IDE-artifacts rule; `git rm --cached` is the fix (Phase 4).
- **4 open, unmerged PRs** as of this audit: `#67` (`renovate/vue-router-5.x`, a 4th attempt at the confirmed-blocked v5 bump), `#66` (`renovate/laravel-tinker-2.x`, the unconfirmed-cause churn), `#27` (`dependabot/.../rollup-4.22.4`, opened 2024-09-24), `#20` (`dependabot/.../nuxt-3.12.4`, opened 2024-08-05). 0 open issues.
- No history-rewrite candidates found in this phase (working-tree secret scan clean); a full-history gitleaks pass is still Phase 4's own remit and has not yet been run.

## Docker/container review

- `php.dockerfile` has **no dependency-install step at all** — it only installs PHP extensions and `COPY`s source; `vendor/` is expected to already exist from a separately-run, bind-mounted `composer` Compose service (documented as a manual step in `Command.txt`). Built standalone (CI, real deploy), this image cannot boot.
- `node.dockerfile` does run `npm install && npm run build`, but is single-stage (dev dependencies/build tools remain in the final image) with no test stage.
- Neither Dockerfile has a test stage — "the image builds and its test stage passes" (standing decision 2) is not currently achievable with these files.
- `apache.dockerfile` + `server/apache/httpd-vhosts.conf` are confirmed dead/unreferenced (Phase 2; re-confirmed this phase).
- All 5 Docker base images (`php:8.5-fpm`, `postgres:latest`, `nginx:stable-alpine`, `node:current-alpine`, `composer:latest`) use floating tags; Phase 2 confirmed their current resolved digests are all up to date in content, just not pinned.

## Documentation review

- Root `README.md` is the original interview-task spec (useful context, kept as-is for now).
- `src/be/README.md` is the stock, unmodified Laravel skeleton README — never customized.
- `src/fe/README.md` exists but was not separately audited in depth here (low-risk, standard Nuxt scaffold README).
- No `docs/` directory existed before this phase — this audit creates the first four (`ARCHITECTURE.md`, this file, `TECHNICAL_DEBT.md`, `IMPROVEMENT_ROADMAP.md`).
- None of the 9 CLAUDE.md community-standard files exist yet (`CODE_OF_CONDUCT.md`, `CONTRIBUTING.md`, `SECURITY.md`, `SUPPORT.md`, `CHANGELOG.md`, `CODEOWNERS`, `ACCESSIBILITY.md`, issue/PR templates) — expected at this stage, Phase 15's job.

## Duplication

- The status-option list duplicated between `create.vue`/`[fin].vue` (see Code quality findings) is the only concrete code duplication found. No other repeated business logic identified — the codebase is small and mostly free of copy-paste beyond that one case.

## Test coverage/quality assessment

- **Backend**: 8 real tests (`CompanyUserApiTest` ×7, `UserStatusTest` ×1), using `RefreshDatabase` + factories (not hardcoded IDs — avoids the known Laravel-testing anti-pattern), asserting real response shapes and database state. This is genuine, working coverage of the CRUD happy paths, validation failure, search, and pagination. Gaps: no test for an unknown `fin` (404 behavior unconfirmed), no duplicate-fin/email conflict test, no invalid-status test.
- **Frontend**: zero tests. No `vitest`/`@vue/test-utils` dependency, no test script.
- **Postman collection** (`taskStarEx.postman_collection.json`): requests are correctly built against the real API (`/company/users[/:fin]`, sensible dynamic test data via Postman variables). However, **every embedded `pm.test()` script is generic boilerplate** asserting a schema (`id` number, `username`, `email` format, `fullName`, `avatar`, `isPublic`, `user.id`) that bears no resemblance to this API's actual response shape (`fin`, `name`, `surname`, `father`, `dob`, `email`, `status`). If the collection's tests were ever run (e.g. via Newman in CI), 100% would fail or assert nothing meaningful. The collection is useful as a manual request reference; it provides **no real contract-test coverage** despite its presence suggesting otherwise. This is the audit's clearest "looks tested, isn't" finding.
