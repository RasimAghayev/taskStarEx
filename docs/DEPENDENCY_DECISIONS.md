# Dependency Decisions

> Per CLAUDE.md standing decision 3: a dependency is added only after Phase 2
> data exists and a decision entry is written here — what existing
> deps/stdlib already cover, maintenance status, license, known
> vulnerabilities, size, latest stable version. Entries are append-only,
> newest last matched with first addition.

## pinia (`src/fe`) — added Phase 6, 2026-11-18

- **What it is**: the official Vue.js state-management library (maintained
  by the same core team that owns Vue and Vue Router), the designated
  successor to Vuex. Intuitive, type-safe stores with a Composition-API
  shape.
- **Why needed now**: `vue-router@^5.0.0` lists `pinia` (`^3.0.4 || ^4.0.2`)
  as a `peerDependency`. Per npm's own `peerDependenciesMeta`, it is
  formally **optional**, not a hard install-time failure — `npm install`
  succeeds without it. The repo owner had already confirmed `vue-router`
  v5 as the intentional direction (2026-11-18, recorded in
  `docs/TECHNICAL_DEBT.md` P1-2) and the orchestrator's Phase 6 directive
  for this repo explicitly calls for adding `pinia` as part of making this
  bump's manifest/lock pair genuinely consistent (avoiding the optional-peer
  warning and unblocking the router's own data-loader integration,
  `@pinia/colada`, should Phase 8's eventual Vuex→Pinia migration want it).
  This entry documents that decision; it does not re-open it.
- **What it overlaps with / replaces**: `vuex` (`^4.0.2`, already confirmed
  **unused** by Phase 2/3 — no `store/` directory, zero `vuex`/`useStore`/
  `createStore` references anywhere under `src/fe`). Pinia is not wired to
  any component in this phase — it is declared and locked only, so
  `vue-router@^5.0.0` has a fully consistent, resolvable dependency graph.
  **No migration of `vuex` usage happens in this phase.** `vuex` stays
  declared and installed, unchanged, until Phase 7 confirms (via tooling +
  coverage) that zero usage survives and Phase 8 completes a real
  Vuex→Pinia migration — tracked explicitly in `docs/TECHNICAL_DEBT.md`
  P1-2 and `docs/IMPROVEMENT_ROADMAP.md` Phase 6/8 rows. Until then, two
  state-management libraries are declared in the manifest simultaneously;
  this is a known, intentional, temporary state, not an oversight.
- **Maintenance status**: actively maintained, official Vue ecosystem
  project (`github.com/vuejs/pinia`). Latest stable `4.1.0`, published
  2026-10-14 (verified live against the npm registry at run time, per
  standing decision 1).
- **License**: MIT (verified via the npm registry's own package metadata).
- **Known vulnerabilities**: none found (`api.osv.dev` query for
  `pinia@4.1.0` on the npm ecosystem returned zero advisories; `npm audit`
  on the regenerated `src/fe` lock reported no pinia-attributed findings).
- **Size**: ~195 KB unpacked (small; comparable to `vuex`'s own footprint,
  no meaningful bundle-size regression).
- **Standard library equivalent**: none — Vue has no built-in global-store
  primitive comparable to Pinia/Vuex; this remains a legitimate third-party
  need for any app with cross-component shared state.

## pinia (`src/fe`) — removed Phase 8, 2026-11-18 (addendum to the entry above)

- **Re-verified independently this phase** (not trusting Phase 6/7's say-so
  alone): `grep -rni "pinia|createPinia|defineStore|usePinia"` across
  `src/fe` (excluding `node_modules`) — zero hits outside
  `package.json`/`package-lock.json`; `nuxt.config.ts`'s `modules` array
  does not register it either. Independently re-read `node_modules/vue-router/package.json`'s
  own `peerDependenciesMeta` — confirms `pinia` is listed `"optional": true`,
  not a hard peer-dependency failure, exactly as Phase 6/7 already found.
- **Decision (ADR-0003, see `docs/adr/0003-remove-unused-pinia-dependency.md`):
  removed.** `vue-router@5` installs and resolves cleanly without it
  (optional peer, confirmed above); `vuex` was removed in Phase 7 as
  dead-dependency-only (no store module ever existed to migrate — Phase 7
  explicitly flagged this so no migration was assumed pending); there is no
  concrete, present feature in this repo that needs cross-component shared
  state today. Per standing decision 7 (KISS/YAGNI win conflicts) and the
  Design Principles section's "don't design for a speculative future," kept
  is not chosen without a real present justification, and none exists.
  `npm uninstall pinia` run inside the exact `node:26.13.1-alpine` image
  `node.dockerfile` uses, bind-mounted against the real worktree — diff
  scoped to exactly `pinia` + 0 orphaned sub-dependencies (1 package
  removed total). `npm audit` high-severity count unchanged (41, the
  pre-existing unfixed-upstream `picomatch` chain) — no regression.
- If a real, present need for client-side shared state appears later (e.g.
  as a Phase 8B-approved feature), re-add it then with a fresh decision
  entry here — this is not a "never again" ban, just a "not now, absent a
  real reason" call.

## laravel/sanctum (`src/be`) — added Phase 9, 2026-11-18

- **What it is**: Laravel's own first-party, officially-maintained package
  for lightweight API authentication — personal-access tokens (what this
  repo uses) and, separately, SPA cookie-session auth (not used here).
- **Why needed now**: `docs/TECHNICAL_DEBT.md` P1-1 — every
  `/api/company/users/*` route (full PII: name/surname/father/dob/email,
  fully CRUD-able) had **zero** authentication. Phase 9's explicit mandate
  is to close this P0 finding for real. Token-based, not SPA/cookie auth,
  per the orchestrator directive: this is a plain JSON API with no
  same-site frontend session requirement today.
- **What it overlaps with / replaces**: nothing already present — this
  repo had no authentication mechanism of any kind (the default Laravel
  `users`/`password_reset_tokens`/`sessions` scaffold exists but is inert,
  per `docs/TECHNICAL_DEBT.md` P2-3/Phase 7-8 notes; it provided no actual
  enforcement). Writing a hand-rolled token scheme instead of using
  Sanctum was considered and rejected — that would be reinventing a
  first-party, already-audited mechanism for zero benefit (KISS).
- **Maintenance status**: first-party Laravel package
  (`github.com/laravel/sanctum`), actively maintained in lockstep with the
  framework. Latest stable `4.5.0` (verified live against the Packagist
  registry at run time, per standing decision 1), compatible with the
  `laravel/framework ^13.0` already in use.
- **License**: MIT.
- **Known vulnerabilities**: none — `composer audit --locked` (run inside
  the real `composer:2.11.2` container against the actual worktree lock)
  reports "No security vulnerability advisories found" after this addition.
- **Size**: small — one new migration (`personal_access_tokens`), one
  trait (`HasApiTokens`) on `App\Models\User`, no new runtime services.
- **Standard library equivalent**: none — PHP/Laravel has no built-in API
  token mechanism; this is a legitimate, minimal third-party need.

## larastan/larastan (`src/be`, dev-only) — added Phase 9, 2026-11-18

- **What it is**: a Laravel-aware extension for PHPStan (static analysis —
  catches type errors, undefined methods/properties, dead code paths
  before runtime).
- **Why needed now**: this repo had zero static-analysis tooling (only
  `laravel/pint`, a style formatter, not a correctness checker). Per
  CLAUDE.md's Phase 9 checklist: "add quality tooling that fits the
  stack." Laravel + PHPStan is the de-facto standard pairing; Larastan
  specifically understands Eloquent/facade magic that plain PHPStan
  misparses.
- **What it overlaps with / replaces**: nothing — `laravel/pint` is
  formatting-only (whitespace/style), not type/correctness analysis; the
  two are complementary, not duplicative.
- **Maintenance status**: actively maintained
  (`github.com/larastan/larastan`, the successor name to the older
  `nunomaduro/larastan`). Latest stable `3.15.x` (verified live against
  Packagist at run time), requires PHPStan `^2.0`, which it pulls in as
  its own dependency — compatible with the installed PHP `^8.4`/Laravel
  `^13.0`.
- **License**: MIT.
- **Known vulnerabilities**: none — confirmed via the same
  `composer audit --locked` run above (dev-only, never shipped in the
  `runtime` Docker stage, which installs `--no-dev`).
- **Size**: dev-only dependency, zero runtime/production footprint.
- **Standard library equivalent**: none.
- **Verification**: `phpstan.neon.dist` (level 5, `paths: [app]`) run via
  `make stan` → initial run found 18 real findings (all genuine Larastan/
  Laravel-ecosystem gaps, not false suppressions): `JsonResource`
  dynamic-property access needing `@mixin` annotations
  (`UserResource`/`UserAuditLogResource`), the stock `App\Models\User`
  skeleton's `$fillable`/`$hidden` docblock type not matching the
  framework base class's `list<string>` typing, and one enum (`UserStatus`)
  whose `description()` method only exists via `__call()`/reflection and
  needed a `@method` annotation. All fixed with annotations/typed
  docblocks only — zero runtime behavior change (re-verified: `make verify`
  green after). Re-run: `[OK] No errors`.

## eslint + @nuxt/eslint (`src/fe`, dev-only) — added Phase 9, 2026-11-18

- **What it is**: `eslint` is the standard JS/TS linter; `@nuxt/eslint` is
  Nuxt's own first-party module that generates a flat ESLint config aware
  of Nuxt's file conventions (auto-imports, pages/, layouts/, middleware/)
  plus Vue 3 template rules, so the project doesn't hand-roll one.
- **Why needed now**: `docs/TECHNICAL_DEBT.md` P1-4 flagged zero frontend
  quality tooling of any kind. Per CLAUDE.md's Phase 9 checklist: "add
  quality tooling that fits the stack." This is the standard pairing for
  a Nuxt 4 app (Nuxt's own scaffolding/docs recommend `@nuxt/eslint`
  directly).
- **What it overlaps with / replaces**: nothing already present (zero
  frontend lint/type-check tooling existed).
- **Maintenance status**: `eslint` — the de-facto standard, extremely
  actively maintained. `@nuxt/eslint` — first-party Nuxt team package.
  Latest stable versions read live from the npm registry at run time:
  `eslint@10.15.0`, `@nuxt/eslint@1.17.0`.
- **License**: MIT (both).
- **Known vulnerabilities**: `npm audit` (run inside the real
  `node:26.13.1-alpine` container against the actual worktree lock) shows
  52 high-severity findings post-install, **up from the pre-existing 41**
  documented in `docs/TECHNICAL_DEBT.md` P1-5 — re-traced via the `via`
  chain: all 52 are still the single, already-known, upstream-unfixed
  `picomatch` ReDoS (`GHSA-fc5q-39xc-vjpf`), now duplicated across more
  package names because ESLint's own toolchain transitively pulls in more
  copies of the same vulnerable package. **Not a new vulnerability** —
  confirmed zero new advisory titles appear (`npm audit --json`'s
  `vulnerabilities[*].via[*].title` set is unchanged, just a larger count
  of the same one). `docs/TECHNICAL_DEBT.md` P1-5 updated with this count.
- **Size**: dev-only, zero production/runtime footprint (not part of the
  `node.dockerfile` `runtime` stage, which copies only `.output/`).
- **Standard library equivalent**: none.
- **Verification**: `npm run lint` (via `make lint-fe`) — initial run
  found 19 real findings (3 errors, 16 warnings), all genuine: 16
  auto-fixable `vue/html-self-closing` style warnings (applied via
  `eslint --fix`, zero behavior change); one real latent defect in
  `layouts/default.vue` (a bare `<slot/>` template root, invalid per
  `vue/no-multiple-template-root` — fixed by wrapping in a single `<div>`,
  harmless since this file is confirmed unused by this app's actual
  routing today — see `docs/TECHNICAL_DEBT.md` P3-7); and two
  `vue/valid-v-slot` false positives on
  Vuetify's own dotted data-table slot-name convention
  (`#item.status_label`, `#item.actions` — eslint-plugin-vue misparses the
  dot as an unsupported v-slot modifier rather than part of the literal
  slot name), suppressed with a targeted, documented
  `eslint-disable-next-line` comment each, not a blanket rule disable.
  Re-run: 0 problems.
