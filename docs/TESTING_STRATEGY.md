# Testing Strategy

Written in Phase 11 (tester), per `PROMPT.md`'s Phase 11 checklist and
`CLAUDE.md`'s honesty/testing-discipline rules. Describes what is tested,
how, where the real coverage gaps are, and why specific things were
deliberately left out of scope.

## Levels of testing in this repo

| Level | Tool | Where | What it covers |
|---|---|---|---|
| Backend unit | PHPUnit 13 | `src/be/tests/Unit/**` | Pure logic with no framework/DB dependency: `UserStatus` enum, `CsvSafety` (formula-injection detection/sanitization), `UserPolicy` (authorization rules, in isolation from HTTP/DB). |
| Backend feature/integration | PHPUnit 13 + `RefreshDatabase` (sqlite `:memory:`) | `src/be/tests/Feature/**` | Full HTTP-request-to-database round trips: CRUD, auth lifecycle (login/logout/token revocation), authorization (401-for-unauthenticated across every route), CSV export/import (including edge cases and the export-side CSV-injection defense against data that reached the DB by another route), audit-log observer behavior, health endpoints. |
| Frontend unit | Vitest 5 + happy-dom | `src/fe/tests/unit/**` | Plain-JS service/constant modules: `authService.js`, `companyUsersService.js`, `constants/userStatus.js`. |
| Frontend build characterization | `node.dockerfile`'s `test` stage | n/a (build-time assertion) | Confirms `nuxt build` actually produces a runnable server bundle (`.output/server/index.mjs`) — a defect class the unit suite above does not cover, since the unit suite never calls `nuxt build`. |
| Dependency/image audit | `composer audit --locked`, `npm audit`, Docker image build | CI (`backend-audit`/`frontend-audit`/`docker-build-validate` jobs, Phase 10) | Known-vulnerability and build-validity checks, not business-logic correctness. |

Everything above runs **in Docker**, via the repo's own existing
`docker build --target test -f php.dockerfile .` and
`-f node.dockerfile .` build targets (also reachable via `make test`) —
consistent with every prior phase and the standing Docker-first rule. A
run on the bare host is never treated as verification.

## What is deliberately out of scope this phase, and why

- **Frontend component/page tests** (e.g. mounting
  `pages/company/users/index.vue` with real Vuetify/Nuxt rendering).
  Considered, not done: would require `@nuxt/test-utils`'s full
  Nuxt/Nitro test context (see `docs/DEPENDENCY_DECISIONS.md`'s Phase 11
  entry for the full reasoning) — disproportionate for this phase's real,
  present need, which was "zero frontend tests exist at all," not "every
  page needs pixel-level interaction coverage." The plain-`vitest` suite
  added this phase covers every piece of *testable business logic* that
  exists in `src/fe` today (the two services + the shared constant) — the
  `.vue` files themselves are thin presentation wrappers around those
  services with no independent logic of their own worth isolating.
  Revisit if a future page grows real client-side logic.
- **End-to-end/browser tests** (Playwright/Cypress against the full
  Docker Compose stack). No existing skill, tooling, or CI job for this
  in the repo; Phase 9's own manual Docker smoke test already exercises
  the real login → authenticated-CRUD → logout → revoked-token sequence
  by hand (`docs/adr/0004-...md`'s Verification section) and found a real
  defect (the no-`Accept`-header 500) that the automated suite alone had
  missed — a documented precedent that manual smoke testing still has
  real value here, but a full E2E *framework* is a larger standing
  decision (new dependency, new CI job, new maintenance surface) than one
  phase should introduce unasked. Not proposed here; flagged as a
  candidate for a future Phase 8B-style proposal if ever wanted.
- **Mutation testing / property-based testing**. No present need
  identified; the business logic here (CRUD + CSV + audit log) is
  example-based-testable without needing either (YAGNI).
- **Load/performance testing**. Out of this phase's remit (correctness,
  not throughput); nothing in Phase 3/9's findings flagged a performance
  concern that would justify adding this now.

## Mocking policy

Mocking is used only where a real alternative would make the test flaky,
slow, or untestable in isolation — never to avoid exercising real logic:

- **Backend**: `DB::shouldReceive(...)` is used exactly twice, both to
  simulate an *infrastructure failure* that cannot be reliably triggered
  against a real sqlite connection in a test process
  (`HealthEndpointTest`'s "database unreachable" case, and
  `CompanyUserAuditLogTest`'s "audit table missing" resilience case,
  which drops the real table inside a transaction that `RefreshDatabase`
  rolls back afterward — not mocked, actually executed). Every other
  backend test runs against a real sqlite database via `RefreshDatabase`
  — no repository/query-builder mocking anywhere, so a test failure means
  the real Eloquent/DB layer actually misbehaved, not that a mock was
  misconfigured.
- **Frontend**: `useNuxtApp()` is stubbed (`vi.stubGlobal`) because it is
  a Nuxt-injected runtime global that does not exist outside a running
  Nuxt app; the stub returns a fake `$axiosApiClient` whose methods are
  `vitest` mock functions, so each service test asserts the exact
  HTTP verb/URL/payload/config the service module constructs — the one
  thing these thin wrapper modules are actually responsible for getting
  right. Axios itself is not re-tested (a trusted, independently-tested
  third-party library).

## Regression contract

`docs/BASELINE.md` is the standing regression contract (Phase 5,
"existing business behavior is preserved"). Phase 11 re-verified its
backend test count was stale (still describing a scratch-copy run before
Phase 6 unblocked the real worktree) and updated it with the
Docker-verified current count — see that file's Phase 11 addendum. Any
future phase touching `src/be`/`src/fe` must keep this phase's full suite
green (both languages) or justify and document a deliberate, reviewed
exception.

## A correction this phase made to its own briefing (verify, don't trust)

This phase's own task briefing described `UserPolicy` as having had its
"7 authorization methods wired to literally always `return false`,"
calling for a test proving every authenticated user gets 403 on every
policy-gated action. Reading the actual current code
(`src/be/app/Policies/Company/UserPolicy.php`) and its governing ADR
(`docs/adr/0004-wire-authentication-and-authorization.md`) found this to
be inaccurate: that `return false;` shape was Phase 8's ADR-0001
*placeholder*, explicitly superseded by Phase 9's ADR-0004, which wires
five of the seven methods (`viewAny`/`view`/`create`/`update`/`delete`)
to `true` — a deliberate, documented, orchestrator-directed
single-authenticated-user-can-manage-all-users model. ADR-0004's own
"Alternatives considered" section explicitly names and rejects the
always-`false` shape the briefing described. Per this program's own
"verify each yourself, don't take the claim on faith" instruction and the
standing honesty rule against asserting behavior that doesn't match
reality, this phase did **not** write the briefed 403-for-everyone test
(it would assert something false). Instead it added
`tests/Unit/Policies/Company/UserPolicyTest.php`, which locks in the
*real* model (five methods `true`, `restore`/`forceDelete` fail-closed
`false` since they're unreachable) — serving the same underlying purpose
the briefing wanted (an explicit, named guard rail so nobody "fixes" this
surprising-looking code back to `false` by accident) without asserting a
behavior the app doesn't actually have.
