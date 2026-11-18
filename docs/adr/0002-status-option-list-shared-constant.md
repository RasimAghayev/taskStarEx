# ADR-0002: Centralize the status-option list in one frontend constant

- **Status**: Accepted
- **Date**: 2026-11-18
- **Phase**: 8 (Architecture)

## Context

The 8-entry status dropdown (value + Azerbaijani label, mirroring the
backend's `App\Enums\UserStatus` enum and its `#[Description(...)]`
attributes) was hand-duplicated **verbatim** in two files:
`src/fe/pages/company/users/create.vue` and
`src/fe/pages/company/users/[fin].vue` — confirmed by direct read of both
files this phase (re-confirming Phase 3's P2-4 finding, not just trusting
it). Each file's `data()` carried its own identical 8-item `statusItems`
array. This is exactly 2 call sites of one small, fixed list — flagged as
a DRY violation and a drift risk since 2026-11-18's Phase 3 audit
(`docs/TECHNICAL_DEBT.md` P2-4): if the backend enum ever gains, loses, or
relabels a status without a matching manual edit in *both* frontend files,
the two pages silently disagree.

The backend already computes a per-record `status_label` string in
`UserResource` (a single label for one record's current value, for
*display*), not a list of all possible value/label pairs for a *dropdown*.
Consuming that for this purpose would require a new API endpoint (e.g.
`GET /api/company/statuses`) purely to serve a static 8-row list that
never changes at runtime — real, added complexity (a new route, controller
method, request/response contract, test coverage, and a network round-trip
on every form load) to solve a problem a one-file constant solves for free.

## Decision

**Extract the list into one new frontend module,
`src/fe/constants/userStatus.js`, exporting `USER_STATUS_OPTIONS`.** Both
`create.vue` and `[fin].vue` now `import { USER_STATUS_OPTIONS } from
'~/constants/userStatus'` and set `statusItems: USER_STATUS_OPTIONS` in
their `data()`, instead of each declaring its own literal array.

This is standing decision 7's DRY principle applied exactly where it
solves a real, present problem (two existing, already-duplicated call
sites) at proportionate cost (one small new file, zero new runtime
dependency, zero new API surface) — not over-engineering. The new module
is a plain exported array, not a composable, a Pinia/Vuex store, or a
config service: this app has no reactive or server-driven need for this
list (it never changes without a code change to the backend enum too), so
a richer abstraction would be exactly the kind of speculative
future-proofing standing decision 7 says KISS/YAGNI should win against.

A code comment in the new file explicitly cross-references
`src/be/app/Enums/UserStatus.php` and states that the two must be updated
together — this does not create a runtime link (none is warranted for a
static list this small) but gives the next person editing either file the
exact pointer to the other side, directly addressing the "drift risk"
half of the original finding.

## Alternatives considered

1. **Add an API endpoint exposing the enum's cases+labels, consumed by
   both pages.** Rejected — real added complexity (route, controller,
   contract, tests, an extra network call on every form load) for a list
   of 8 static strings that only changes when a developer edits the
   backend enum anyway (at which point they can just as easily edit the
   one frontend constant file in the same commit). Not justified at this
   repo's scale; would be premature generality.
2. **A `composables/useUserStatus.js` Nuxt auto-import.** Rejected — this
   project's existing convention is explicit imports everywhere
   (`services/companyUsersService.js` is imported explicitly, not
   auto-registered), and a composable implies reactive/stateful behavior
   this plain constant list doesn't need. A plain exported array matches
   the existing code style more closely and is simpler to reason about.
3. **Leave the duplication as-is.** Rejected — this is precisely the "two
   call sites of a small fixed list" case the orchestrator's directive
   flagged as "likely justified" for a shared source of truth; leaving it
   duplicated after the ADR process explicitly considered it would be
   ignoring an already-identified, cheap-to-fix drift risk for no benefit.

## Consequences

- One new file, `src/fe/constants/userStatus.js`; both Vue pages' `data()`
  are each 7 lines shorter.
- Any future change to the status list requires touching 2 files instead
  of 3 (the backend enum + this one constant, instead of the enum + 2
  duplicated arrays) — not 1, since no runtime link to the backend enum
  exists or is warranted at this scale; the code comment is the mitigation
  for that residual gap, not a complete fix.
- No new dependency, no new API surface, no change to either page's
  runtime behavior (the rendered dropdown options are byte-for-byte
  identical before and after).

## Verification

- `docker build --target test -f node.dockerfile .` (fresh build) — exit
  0, production server bundle (`.output/server/index.mjs`) produced,
  confirming both pages still build correctly importing from the new
  module path.
- Visual/data equivalence: the extracted array was copied verbatim
  (byte-for-byte) from both original inline arrays, which were themselves
  already identical to each other — confirmed by diff before extraction.
