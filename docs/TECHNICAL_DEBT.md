# Technical Debt

> Classified P0-P3. Cross-referenced to `projects/info/analysis/taskStarEx.md` IDs where applicable. Remediation owner = the Phase that will act on it (this phase documents only).

## P0 — Critical

| ID | Item | Owner phase | Remediation |
|---|---|---|---|
| P0-1 | Manifest/lockfile integrity defect, both ecosystems (`composer.json ^12.0` vs locked `v11.31.0`; `package.json nuxt ^4.0.0` vs locked `3.11.2`) | Phase 6 (step 0, prerequisite) | Regenerate both locks (`composer update laravel/framework laravel/tinker`; `npm install`/`update` in `src/fe`) before trusting or acting on any other version claim. |
| P0-2 | `CVE-2026-114509` — Laravel TokenGuard auth bypass, in range at locked `v11.31.0` AND at the manifest's own `^12.0` floor | Phase 6 (step 1) | Land on `>=12.68.0` or `>=13.27.0` specifically — a plain "bump to 12.x" is not sufficient. Target latest stable per standing decision 1. |
| P0-3 | 70 vulnerable npm packages / 5 critical on `src/fe` (`nuxt`, `@nuxt/devtools`, `shell-quote`, `simple-git`, `tar`) | Phase 6 (step 5, follows from P0-1 fix) | Expected to largely self-resolve once the lock is regenerated against the already-declared `nuxt ^4.0.0`; re-audit after. |

## P1 — High

| ID | Item | Owner phase | Remediation |
|---|---|---|---|
| P1-1 | Zero auth/authz on `/api/company/users/*` (full PII, unauthenticated CRUD); `UserPolicy` exists but is dead/broken scaffolding | Phase 8 (ADR) → Phase 9 (implementation) | Decide wire-up-for-real vs. delete-as-premature in an ADR, then implement. |
| P1-2 | `vue-router` 4→5 blocked (v5 requires `pinia`, repo uses `vuex`); 3 reverted Renovate cycles + a 4th open now (`PR #67`) | Phase 4 (PR triage) + Phase 6 (hold) | **Standing-decision-1 written blocker.** Hold at `^4.6.4` / npm's `v4-latest` dist-tag (currently `4.6.5`) until a Vuex→Pinia migration is separately scoped and approved as its own piece of work. Close/reconfigure Renovate for `#67` so it stops re-proposing the same blocked bump. |
| P1-3 | Docker-first standing decision not achievable with current Dockerfiles: `php.dockerfile` has no install step (depends on a manually-run separate `composer` Compose service); neither Dockerfile has a test stage | Phase 5 | Multi-stage rewrite of both Dockerfiles: build stage with real dependency install + a test stage, slim runtime stage. |
| P1-4 | Zero frontend tests, zero frontend CI job | Phase 10 (CI job) + Phase 11 (tests) | Add lint/type-check CI now; add Vitest + `@vue/test-utils` coverage once the Nuxt 4 bump (P0-1/Phase 6) lands; wire a test stage into CI after. |
| P1-5 | `picomatch` ReDoS (`GHSA-fc5q-39xc-vjpf`, CVSS 7.5) on `src/be`'s Vite asset pipeline — 6 high findings, unfixed even at latest `vite`/`laravel-vite-plugin` | Phase 6 (document as accepted residual risk) | **Standing-decision-1 written blocker** — no upstream fix exists yet. Bump to latest anyway (clears everything else); document the residual `picomatch` exposure here and re-check each time `vite`/`laravel-vite-plugin` release. |

## P2 — Medium

| ID | Item | Owner phase | Remediation |
|---|---|---|---|
| P2-1 | `.vscode/settings.json` tracked in git despite `.gitignore` excluding it | Phase 4 | `git rm --cached .vscode/settings.json`, commit. |
| P2-2 | Postman collection's `pm.test()` scripts assert an unrelated schema (generic boilerplate, not adapted to this API) | Phase 11 | Rewrite the test scripts to match the real response shape, or strip them if not worth maintaining. |
| P2-3 | Confirmed dead code: `apache.dockerfile` + `server/apache/httpd-vhosts.conf`, unused `vuex` dependency, unused `DatePicker.vue`, inert default-Laravel-auth scaffold (`users`/`password_reset_tokens`/`sessions`, default `User`+factory, seeded-but-unused, dead `/user` route) | Phase 7 | Remove each with tooling+coverage evidence, separate commits. Keep-or-delete decision on the auth scaffold's migrations needs its own written note (don't rewrite already-shipped migration history casually). |
| P2-4 | Status-option list hand-duplicated between `create.vue` and `[fin].vue` | Phase 8 (ADR) | Source from one place — either consume the backend's existing `status_label` or centralize a single frontend constant. |
| P2-5 | No community-standard files exist yet (CODE_OF_CONDUCT, CONTRIBUTING, SECURITY, SUPPORT, CHANGELOG, CODEOWNERS, ACCESSIBILITY, issue/PR templates) | Phase 15 | Standard creation pass, same as every other repo. |
| P2-6 | No `.github/dependabot.yml` (standing decision 14); two 2024-vintage stale Dependabot PRs still open | Phase 10 + Phase 4 (close stale PRs) | Add the config; close/triage `#20`/`#27`. |
| P2-7 | 4 open, unmerged PRs needing triage (`#67`, `#66`, `#27`, `#20`) | Phase 4 | Close/merge each per the Dependency analysis findings above; `#67` likely needs Gate 11 if deemed permanently abandoned. |
| P2-8 | `php: ^8.2` floor reaches upstream EOL 2026-12-31 | Phase 6 (step 3) | Bump the floor ahead of EOL, per standing decision 1. |
| P2-9 | No `config/cors.php` published; cross-origin fe(:3000)/be(:80) dev topology relies on undocumented defaults | Phase 5 (verify) → Phase 9 (author deliberately) | Confirm actual behavior once containers build; publish an explicit CORS config either way. |

## P3 — Low

| ID | Item | Owner phase | Remediation |
|---|---|---|---|
| P3-1 | `composer.json` still named/described as the generic Laravel skeleton | Phase 15 (or incidental during Phase 6) | Customize `name`/`description`. |
| P3-2 | `node.dockerfile` single-stage (dev deps in final image) | Phase 5 | Split into build/runtime stages. |
| P3-3 | Hardcoded dev-only Postgres credential in `docker-compose.yml:79-81` (placeholder value, local port only) | Phase 5 | Source from `.env` instead, while touching compose anyway. |
| P3-4 | Stray empty, untracked `src/be;C` directory on disk | None needed | Harmless filesystem debris; git does not track empty directories, nothing to action. |
| P3-5 | Default branch still `master` | Phase 4 (Gate 4) | Rename to `main` once approved, per this program's established precedent. |
| P3-6 | Frontend uses Options API consistently rather than `<script setup>` | Phase 8 (judgment only) | No change unless a concrete reason emerges — stylistic, not a defect. |

## Standing-decision-1 written blockers (formal record)

Per CLAUDE.md standing decision 1, an exception to "latest stable, always" requires a written blocker here, with the highest compatible version used meanwhile:

1. **`vue-router`** — latest stable is `5.5.1`, but its `peerDependencies` require `pinia`; this repo's state management is `vuex` (itself confirmed unused — see P2-3). Adopting v5 requires a Vuex→Pinia migration, which is an architectural change, not a Phase 6 drop-in bump. **Held at `^4.6.4` / npm's `v4-latest` dist-tag (`4.6.5`)** until that migration is separately scoped and approved. Evidence: 3 reverted Renovate cycles (PRs #56-#65) + a 4th open attempt (`#67`) as of this audit; npm registry peer-dependency data for `vue-router@5.5.1`.
2. **`picomatch` (transitive, via `vite`/`laravel-vite-plugin`/`tinyglobby`/`fdir` on `src/be`'s asset pipeline)** — `GHSA-fc5q-39xc-vjpf` ReDoS, no fix available upstream even at the newest `vite 8.3.8`/`laravel-vite-plugin 3.2.1`. **Bump to latest anyway** (nothing better exists); residual risk accepted and documented here, to be re-checked on every future `vite`/`laravel-vite-plugin` release.
