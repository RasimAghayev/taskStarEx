# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
for its own releases where applicable.

## [Unreleased]

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
