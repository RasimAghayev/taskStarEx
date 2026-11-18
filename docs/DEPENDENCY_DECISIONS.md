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
