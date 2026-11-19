# ADR-0003: Remove the unused `pinia` dependency

- **Status**: Accepted
- **Date**: 2026-11-18
- **Phase**: 8 (Architecture)

## Context

Phase 6 added `pinia@^4.0.0` to `src/fe/package.json` because
`vue-router@^5.0.0` lists `pinia` in its `peerDependencies`. Phase 6's own
handoff and `docs/DEPENDENCY_DECISIONS.md` already flagged a key nuance at
the time: per npm's `peerDependenciesMeta`, `pinia` is formally
**optional**, not a hard install-time requirement — `npm install` /
`npm ci` succeed without it. It was added anyway at the time "to avoid an
optional-peer warning and keep the graph consistent for any future
`@pinia/colada` integration," declared and locked only, never wired into
any component.

Phase 7 independently re-confirmed via `depcheck` and `knip` (both run in
Docker) that nothing in the codebase uses `pinia` — zero stores, zero
imports — and explicitly left its fate to Phase 8 rather than removing it
itself (Phase 7's scope was the already-proven-dead `vuex`/`DatePicker.vue`/
`apache.dockerfile` items only).

This phase re-verified both facts independently rather than trusting the
prior phases' say-so alone (standing decision 6's spirit applied to a
removal decision, not just a deletion):

- `grep -rni "pinia|createPinia|defineStore|usePinia"` across `src/fe`
  (excluding `node_modules`) — zero hits outside
  `package.json`/`package-lock.json`; `nuxt.config.ts`'s `modules` array
  does not register it either.
- Read `node_modules/vue-router/package.json` directly: `peerDependencies`
  lists `"pinia": "^3.0.4 || ^4.0.2"`, and `peerDependenciesMeta` confirms
  `"pinia": { "optional": true }` — independently reproducing Phase 6's
  finding, not just repeating it.

`vuex` (the dependency `pinia` was originally added alongside, to keep
`vue-router@5`'s graph "consistent") was itself removed in Phase 7 as
pure dead-dependency removal — there was never an actual Vuex store to
migrate (no `store/` directory ever existed). So the specific scenario
Phase 6 was hedging against (an in-flight Vuex-to-Pinia migration needing
both libraries present simultaneously) never existed and does not exist
now either.

## Decision

**Remove `pinia` now.** `npm uninstall pinia`, run inside the exact
`node:26.13.1-alpine` image `node.dockerfile` uses (bind-mounted against
the real worktree, matching the pattern Phase 7 established for authentic,
reproducible lockfile regeneration) — not a hand-edited manifest/lock.

Rationale, applying standing decision 7 (KISS/YAGNI win conflicts) and the
Design Principles section's instruction not to design for a speculative
future:

- It is a genuinely, currently unused dependency — confirmed independently
  above, not assumed from a prior phase's note.
- `vue-router@5` installs and resolves cleanly without it (it is a
  confirmed-optional peer, not a hard requirement — the original Phase 6
  framing of "pinia is required" was already corrected by Phase 6 itself
  at the time, and re-confirmed here).
- There is no concrete, present feature in this repo's actual codebase
  that needs cross-component shared client state today. "Might want state
  management later" is exactly the kind of speculative future-proofing
  standing decision 7 says not to design for now. If a real need appears —
  including as a Phase 8B-approved feature candidate — it can be re-added
  then, with a fresh `docs/DEPENDENCY_DECISIONS.md` entry justifying it at
  that point (standing decision 3 requires this regardless).
- Keeping an installed-but-never-imported dependency around "just in case"
  is itself a small, compounding form of technical debt: it inflates
  `node_modules`, the dependency-audit surface, and the mental model of
  what this app actually depends on, for zero present benefit.

## Alternatives considered

1. **Keep it, documented, for an anticipated near-term need.** Considered
   and rejected: no concrete near-term feature in this repo's actual,
   approved roadmap (`docs/IMPROVEMENT_ROADMAP.md`) requires client-side
   shared state. Phase 8B's feature candidates are proposed, not yet
   approved (Gate 12), and even if one of them eventually needs state
   management, adding the dependency at that point — when a real,
   specific use is in hand — is the correct time per YAGNI, not now on
   spec.
2. **Keep it because `vue-router@5`'s docs mention Pinia-based
   integrations (e.g. `@pinia/colada`).** Rejected — this repo does not
   use, and has no planned need for, `@pinia/colada` or any router-level
   data-loader integration; `vue-router@5`'s own routing (confirmed
   working via `<router-view/>` in `app.vue`, re-verified by Phase 7)
   functions correctly without either package.

## Consequences

- `src/fe/package.json` and `src/fe/package-lock.json` each lose exactly
  one entry (`pinia`) and its locked resolution — confirmed via
  `git diff --stat`: `package.json` 1 line removed, `package-lock.json`
  26 lines removed, 1 package removed per `npm uninstall`'s own output,
  zero orphaned sub-dependencies.
- `npm audit` high-severity count is unchanged (41 — the pre-existing,
  unfixed-upstream `picomatch` chain documented in
  `docs/TECHNICAL_DEBT.md` P1-5) — this removal neither improves nor
  worsens that unrelated, already-accepted residual risk.
- If a genuine future need for shared client state emerges, re-adding
  `pinia` (or evaluating alternatives at that time, since "latest stable"
  per standing decision 1 may have moved on) is cheap — this is not a
  one-way door.

## Verification

- `grep -rni "pinia|createPinia|defineStore|usePinia" src/fe` (excluding
  `node_modules`) — zero hits outside the manifest/lock, before removal.
- `node_modules/vue-router/package.json` read directly —
  `peerDependenciesMeta.pinia.optional === true`, confirmed independently.
- `npm uninstall pinia` inside `node:26.13.1-alpine` (digest-pinned, same
  as `node.dockerfile`'s `base` stage) — exit 0, "removed 1 package,"
  `git diff --stat` confirms a scoped, 2-file diff.
- `docker build --target test -f node.dockerfile .` (fresh build) — exit
  0, production server bundle (`.output/server/index.mjs`) produced.
- `npm audit --json` post-removal — 41 high / 0 critical, byte-for-byte
  the same as the pre-removal baseline (Phase 7's post-upgrade state).
