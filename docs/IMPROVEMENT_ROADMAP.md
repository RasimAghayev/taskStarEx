# Improvement Roadmap — Phase 4-15 plan for `taskStarEx`

> This is the per-repo execution plan referenced by Gate 10. Nothing here is executed by Phase 3 — it is the plan to be approved before Phase 4 starts. Phase numbers and scopes follow `PROMPT.md`/`CLAUDE.md`.

## Critical path (must happen in this order)

1. **Phase 4** — git/PR hygiene, including triaging the open `vue-router-5.x` PR (#67) and untracking `.vscode/settings.json`. **2026-11-18 update:** #67 was merged directly by the repo owner before this plan's Phase 4 pass started, and the owner has since confirmed v5 as the intentional direction (not reverted) — see `docs/TECHNICAL_DEBT.md` P1-2 and the standing-decision-1 blocker record. Step 2 below is updated accordingly.
2. **Phase 6, step 0** — regenerate both lockfiles (`composer update laravel/framework laravel/tinker`; `npm install`/`update` in `src/fe`) so manifest and lock actually agree. **Nothing else in Phase 6 should proceed before this**, since every "current version" claim in `projects/info/deps/taskStarEx.md` is conditional on it.
3. **Phase 6, step 1** — Laravel framework to `>=12.68.0` or `>=13.27.0` (clears `CVE-2026-114509` for real; a plain "any 12.x" does not).
4. **Phase 5** — Docker baseline fix (multi-stage `php.dockerfile` with a real install + test stage; multi-stage `node.dockerfile`) — needed before any phase can honestly claim "verified in Docker" for this repo.
5. **Phase 9** — real authentication/authorization on `/api/company/users/*` (the repo's single biggest concrete security gap).

Everything else (frontend tests, dead-code removal, docs, CI expansion) can proceed in parallel with or after the above, per the phase-by-phase detail below.

## Phase 4 — Git/GitHub hygiene

- Full-history gitleaks audit (this Phase 3 pass only covered the working tree, per the orchestrator's directive — expected clean, must still be run properly).
- Triage all 4 open PRs:
  - `#67` (`renovate/vue-router-5.x`) — **superseded by events:** the repo owner merged this directly to `master` on 2026-11-17, before this Phase 4 pass started. Not a triage action; see `docs/TECHNICAL_DEBT.md` P1-2/P2-7. The owner's follow-on decision (2026-11-18, keep v5) also closed Renovate's resulting revert proposal, `#69`.
  - `#66` (`renovate/laravel-tinker-2.x`) — investigate the unconfirmed churn cause with a real install; merge if clean, close with a documented reason otherwise.
  - `#27` / `#20` (2024-vintage Dependabot PRs) — almost certainly superseded by the Phase 6 upgrade; close as stale with a note, or let a fresh Dependabot run (post Phase 10) supersede them naturally.
- `git rm --cached .vscode/settings.json`; confirm `.gitignore` already covers it going forward (it does).
- Stand up the 3-branch model (`main`/`test`/`preview`) — `preview` doesn't exist yet for this repo (expected, this phase creates it).
- `master`→`main` default-branch rename — **Gate 4**, bundle the approval ask with this Gate 10 presentation per the established precedent in `APPROVALS.md`. **2026-11-18: approved and executed** — `main` verified content-identical to `master` (tree-hash match after a PR-based fast-forward sync, PR #75), set as the GitHub default branch, CI workflow triggers repointed, `master` deleted post-mirror-refresh.
- Acceptance: gitleaks full-history exit 0 or findings triaged; 0 stale open PRs remaining unaddressed; `.vscode/settings.json` no longer tracked; 3-branch model in place.

## Phase 5 — Containerization baseline

- Rewrite `php.dockerfile` as a genuine multi-stage build: a stage that actually runs `composer install --no-dev --optimize-autoloader` (or an equivalent dev/test variant), then a runtime stage; add a test stage that runs `phpunit`/`php artisan test`.
- Rewrite `node.dockerfile` similarly: build stage with `devDependencies` + `npm run build`, slim runtime stage serving `.output/server/index.mjs`.
- Author `docs/BASELINE.md` recording the current PHPUnit pass/fail counts as the regression contract for later phases.
- Add the missing `src/fe/.env.example` (document `NUXT_PUBLIC_API_URL`).
- Move the hardcoded dev Postgres credential (`docker-compose.yml:79-81`) to an env var while this phase is touching Compose anyway.
- Empirically verify the fe(:3000)/be(:80) CORS behavior once containers actually build; note findings even if no fix is needed yet (deliberate authoring is Phase 9's job).
- Explicitly leave `apache.dockerfile`/`server/apache/` alone — Phase 7's job, not this one.
- Acceptance: both images build standalone (no external manual steps); `make verify`-equivalent passes (build + test stage green) in Docker, not on host.

## Phase 6 — Dependency upgrade (ordered)

| Step | Target | Status | Note |
|---|---|---|---|
| 0 | Regenerate both lockfiles | **Done** | Prerequisite — see Critical path above. Full unscoped `composer update` (scoped attempts failed on a whole-graph dev-tooling conflict); `npm install` + explicit `axios@latest`/`vuetify@latest` force (plain `npm install` silently kept stale-but-range-satisfying resolutions). Commits `738b61c`, `8c83663`. Docker-verified both ecosystems, zero regression vs. `docs/BASELINE.md` (8/8 tests, 31 assertions). |
| 1 | `laravel/framework` → `>=12.68.0` or `>=13.27.0` (latest stable at run time) | **Done** | Landed on `v13.43.0`. Clears `CVE-2026-114509` for real — `composer audit --locked` now clean. Docker-verified. Commit `738b61c`. |
| 2 | `laravel/tinker` | **Done** | Bumped to `^3.0` (resolved `3.2.0`) alongside step 1. PR #66's churn resolved by this same regeneration; the separate live bot PR proposing the identical bump (`#78`) closed as superseded. Commit `738b61c`. |
| 3 | PHP runtime off `^8.2` | **Done** | Raised to `^8.4` — the tightest floor consistent with `spatie/laravel-ignition`'s own `^8.4` requirement pulled in by step 1, not an arbitrary choice. CI (`laravel.yml`) matrix updated to `8.5` in lockstep so it doesn't start failing against the new floor. Commits `77cfe26`, `6850d5c`. |
| 4 | Dev-only Composer packages (pint, sail, mockery, collision, phpunit, spatie/laravel-ignition, fakerphp/faker) | **Done** | Resolved automatically by the step-0 whole-graph `composer update` for most; `phpunit` additionally bumped explicitly `^11.0.1`→`^13.0` (commit `95c633b`) — confirmed empirically (real test run) that the suite needed no migration since it already used plain `test_*` naming, not deprecated `@test` doc-comments. |
| 5 | `nuxt` 3→4 major | **Done** | Resolved `4.6.4`. Vuetify 4 / `vite-plugin-vuetify` 2.x compatibility with Nuxt 4's Vite changes verified via a green Docker build (frontend `test` stage asserts the production server bundle is actually produced). Commit `8c83663`. Live bot PR proposing the same bump (`#74`) closed as superseded. |
| 6 | `vue-router` | **Done** | Kept `^5.0.0` per the owner's confirmed decision (not reverted). `pinia@^4.0.0` declared (resolved `4.1.0`) and the lockfile regenerated so the v5/pinia graph is genuinely consistent (decision entry in `docs/DEPENDENCY_DECISIONS.md`); commit `8c83663`. Live bot PRs triaged: `#81` (propose revert to v4.6.5) declined/closed as contradicting the owner's decision; `#72` (propose v5.5.1 directly) closed as superseded, same target version already reached. **The Vuex→Pinia migration itself is explicitly NOT done in this step** — `vuex` stays declared/installed unchanged; that migration is real Phase 7/8 scope (migrate store module(s), re-point components, remove `vuex` only after Phase 7's tooling+coverage check), tracked in `docs/TECHNICAL_DEBT.md` P1-2. |
| 7 | `vite` / `laravel-vite-plugin` (src/be asset pipeline) | **Done** | Bumped anyway via the step-0/step-5 lock regenerations despite the unfixed `picomatch` chain — residual risk documented in `docs/TECHNICAL_DEBT.md` P1-5 and the standing-decision-1 blocker record, updated this phase with the finding that the same root advisory also accounts for all 41 remaining `src/fe` high findings at much larger scale through Nuxt's own toolchain. `src/be` previously had no lockfile to audit at all — one now exists (`src/be/package-lock.json`, commit `d205016`), reproducing the same 6 already-known findings. |
| 8 | Docker base images + CI action pins | **Done** | Base images (php, node, postgres, nginx, composer) already digest-pinned current from Phase 5 — verified live, no change needed. CI actions in `.github/workflows/laravel.yml` re-pinned by resolved commit SHA (`actions/checkout@...# v7.0.1`, `shivammathur/setup-php@...# v2.40.0`), commit `6850d5c`. `docker/dockerfile` BuildKit syntax directive bumped `1.7`→`1.29` in all three Dockerfiles, commit `04a9d7d` (applied directly, superseding live bot PR `#82`, since this phase's agent cannot merge PRs itself). Live bot PR `#83` (nginx `1.30.6`→`1.31.8`) declined/closed: nginx.org's own branch labels (verified live) show `1.30.6` is the current **stable** release and `1.31.8` is current **mainline** — standing decision 1 calls for latest *stable*, so no change was made. |
| 9 | `.github/dependabot.yml` | **N/A — already resolved in Phase 4** | See `docs/TECHNICAL_DEBT.md` P2-6: config already added in Phase 4 (git-steward), `preview` target branch, weekly Monday 06:00 UTC. Nothing further needed this phase. |

- Each step: one upgrade/group at a time, built and tested in Docker, breaking changes migrated and logged in `CHANGELOG.md`, per standing decision 1.
- Acceptance: both lockfiles regenerate cleanly; `composer audit`/`npm audit` re-run post-upgrade show the critical/high counts collapse as expected (P0-2/P0-3 cleared or newly-justified exceptions documented). **All met as of 2026-11-18** — see `docs/TECHNICAL_DEBT.md` P0-1/P0-2/P0-3 and `projects/info/state/handoff/6/taskStarEx.md` for full evidence.

## Phase 7 — Dead code removal

- Remove (separate commits, tooling+coverage evidence per the skill's "never delete on a guess" rule, after Phase 6 lands so nothing is removed prematurely):
  - `apache.dockerfile` + `server/apache/httpd-vhosts.conf`. **Done** — commit `c8b336b`. Re-confirmed unreferenced (grep across CI, Makefile, `docker-compose.yml`, `Command.txt`, README) before deletion.
  - `vuex` dependency (re-confirm zero usage survived the Nuxt 4 bump before deleting). **Done** — commit `ae86e26`. Re-confirmed via grep + `depcheck` + `knip` (all three agree). `vue-router`/`pinia`, also flagged "unused" by the same tools, were deliberately **not** removed — `vue-router` is a tooling false positive (Nuxt's file-based routing depends on it internally; `app.vue`'s `<router-view/>` confirms real usage) and `pinia`'s fate is Phase 8 scope, not Phase 7's. Dead-dependency removal only, not a Vuex→Pinia migration (no store ever existed to migrate).
  - `src/fe/components/DatePicker.vue`. **Done** — commit `b97d891`. Re-confirmed via grep + `knip`.
  - Structural realignment (Laravel 13 app structure, Nuxt 4 `app/` directory convention): considered, **no action** — Laravel 13 keeps Laravel 11's already-adopted skeleton (this repo already uses it); Nuxt 4's `app/` directory is optional/backward-compatible for upgraded-in-place projects and the current flat layout is already proven building/serving correctly in Docker. No real, present mismatch to fix (KISS/YAGNI, standing decision 7).
- Decide and document (ADR, paired with Phase 8) the fate of the default Laravel auth scaffold (`users`/`password_reset_tokens`/`sessions` migrations, default `User` model+factory, `DatabaseSeeder`'s seed, the dead `/user` route). Recommendation to evaluate: keep the already-shipped migrations as-is (don't rewrite released migration history casually), delete only the dead `/user` route, write the decision down either way. **Explicitly left for Phase 8** — not decided or acted on in this phase.
- Acceptance: each deletion has a tooling/coverage citation in its commit; no behavior regression per Phase 11's regression baseline. **Met** — `make verify` green (exit 0) after all three deletions; 8 backend tests/31 assertions unchanged (docs/BASELINE.md contract held exactly); `npm audit` high-severity count unchanged at 41, 0 new/worse findings.

## Phase 8 — Architecture

- Write an ADR resolving `UserPolicy`'s fate: wire it up for real (pairs with Phase 9's authz work) or delete it as premature scaffolding (YAGNI) — don't leave it half-present either way.
- Write an ADR resolving the status-list duplication (`create.vue`/`[fin].vue`): consume the backend's existing `status_label` computation vs. centralize a single frontend constant.
- Explicitly record: Repository pattern stays as-is (already proportionate); **no** DDD/EDA/hexagonal/modular-monolith layering is introduced — a single-entity CRUD interview-task app has no real complexity to justify it (KISS/YAGNI win, standing decision 7).
- Acceptance: both ADRs written and checked in; no unjustified pattern introduced.

## Phase 8B — Feature enhancement (Gate 12 required before implementation)

- Propose (not pre-decided here): real auth+authorization on the CRUD endpoints (overlaps Phase 9), CSV export of the user list, an audit/activity log on `company_users` changes.

## Phase 9 — Hardening

- Implement real authentication + authorization on `/api/company/users/*`, per Phase 8's ADR on `UserPolicy`.
- Add/verify rate limiting on the CRUD endpoints.
- Author `config/cors.php` deliberately (resolves the P2-9 unconfirmed-default question from the audit).
- Add static analysis (phpstan/larastan) and a frontend lint/type-check gate — both are new dependencies, so each goes through `docs/DEPENDENCY_DECISIONS.md` first per standing decision 3.
- Re-check N+1/performance only if Phase 8B introduces relations; nothing to fix today.
- Acceptance: unauthenticated requests to the CRUD endpoints are rejected; `UserPolicy` (or its replacement) is actually exercised by tests; CORS behavior is explicit and documented.

## Phase 10 — CI/CD

- Add a frontend CI job: lint/type-check now, test stage once Phase 11 lands frontend tests.
- Pin `actions/checkout`/`shivammathur/setup-php` by commit SHA.
- Add a dependency/image vulnerability-scan step (composer audit / npm audit / image scan), publish-on-tag only per the runner-time standard.
- Add `.github/dependabot.yml` (weekly, Composer+npm+Docker+Actions, PR limits 10/5, commit prefixes per standing decision 14).
- Workflow branch triggers already repointed to `main` as part of Phase 4's Gate 4 execution (2026-11-18); re-verify still correct when Phase 10 does its own CI pass.
- Acceptance: a PR touching only `src/fe` fails CI if it breaks lint/type-check/build; Dependabot opens its first PR under the new config within a week.

## Phase 11 — Tests

- Backend: keep and extend the existing 8-test suite. Add: duplicate-fin/email conflict (expect 422), invalid-status value, pagination boundary (0/negative `per_page`), and specifically pin down 404 behavior on an unknown `fin` for show/update/delete (currently unconfirmed whether `findOrFail`'s `ModelNotFoundException` renders as JSON or Laravel's default HTML 404 page, since `bootstrap/app.php`'s `withExceptions()` is empty) — fix the contract if it's not JSON-shaped.
- Frontend: author Vitest + `@vue/test-utils` coverage for the three pages and the service layer (zero today), once Phase 6's Nuxt 4 bump and Phase 10's FE CI job are in place.
- Postman: fix the embedded `pm.test()` scripts to match the real schema, or strip them if not worth maintaining — document which, with evidence.
- Acceptance: regression-tested against Phase 5's `docs/BASELINE.md`; honest coverage numbers reported, not claimed.

## Phase 15 — Docs, manifest, diagrams

- Create all 9 missing community-standard files (`CODE_OF_CONDUCT.md`, `CONTRIBUTING.md`, `SECURITY.md`, `SUPPORT.md`, `CHANGELOG.md`, `CODEOWNERS`, `ACCESSIBILITY.md`, issue templates, PR template), same process as every other repo in this program.
- `repo-manifest.yaml` + Mermaid diagrams derived from the real code (feeds `ECOSYSTEM.md`).
- Acceptance: standard Phase 15 verification checklist (CLAUDE.md "Community Standards" section) passes in full.

## Phases marked `n/a` or deferred (with reason)

| Phase | Status | Reason |
|---|---|---|
| 12 Merge small repos | n/a | Not a merge candidate; nothing identified to merge into or out of it. |
| 13 Shared-lib extraction | Deferred | Cross-repo decision outside a single-repo audit's authority; no duplication found against the 9 repos already in `DEPENDENCY_MAP.md`, not checked against the rest. |
| 14 Rename | Deferred, policy question | Whether an interview-take-home scaffold repo is in scope for the `<name>_BestPracticeArcht` rename convention is an Orchestrator/user call, not decided here. |
| 16 Platform/umbrella | n/a | Single-service scaffold; no multi-service platform need identified. |
| 17 Final report | n/a (per-repo) | Program-wide, produced once every repo closes every phase. |
