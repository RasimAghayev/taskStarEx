# ADR-0004: Wire real authentication and authorization on `/api/company/users/*`

- **Status**: Accepted
- **Date**: 2026-11-18
- **Phase**: 9 (Hardening)

## Context

`docs/TECHNICAL_DEBT.md` P1-1, confirmed independently by Phases 2, 3, 7,
and this phase: every route under `/api/company/users/*` — the repo's
entire real business domain, including the Phase 8B additions
(`/export`, `/import`, `/{fin}/audit`) — had **zero** authentication or
authorization. Full PII (name, surname, father's name, date of birth,
email) was readable, writable, and deletable by anyone who could reach
the API. This was this repo's single largest concrete security gap,
explicitly flagged as this phase's headline fix by the orchestrator.

Phase 8's ADR-0001 (`docs/adr/0001-fix-and-keep-userpolicy.md`) already
decided `UserPolicy`'s fate — kept, internally fixed to fail-closed
(`return false;` on every method) — but explicitly deferred both (a) the
authentication mechanism and (b) real authorization wiring to this
phase, as one coherent piece of work.

This app has no role, tenancy, or per-record ownership model: one
implicit actor type (a human operator of this internal employee
registry), one shared data set, no "my records vs. someone else's
records" concept anywhere in the domain. There is no `roles` table, no
`user_id`/`owner_id` column on `company_users`, and no business
requirement in Phase 3's analysis or anywhere since that describes
different users needing different access levels.

## Decision

**Token-based authentication via Laravel Sanctum (personal-access
tokens), not SPA/cookie-session auth. Single-authenticated-user-can-
manage-all-users authorization model — `UserPolicy`'s five real methods
(`viewAny`, `view`, `create`, `update`, `delete`) all return `true`; the
only real rule enforced is "authenticated or not."**

### Why Sanctum, and why tokens not cookies

- First-party Laravel package, already audited and maintained in
  lockstep with the framework already in use (`laravel/framework ^13.0`).
  Writing a hand-rolled token scheme would be reinventing an
  already-solved, already-trusted mechanism for zero benefit (KISS).
- This is a plain JSON API consumed by one Nuxt SPA that is not
  same-site with the API in the dev topology (`fe` on one published
  port, `nginx`→`php` on another, see `docker-compose.yml`) — Sanctum's
  SPA/cookie mode exists specifically for a first-party SPA served from
  the *same* top-level domain, which doesn't describe this setup.
  Sanctum's token mode (personal-access tokens, `Authorization: Bearer
  <token>`) is the documented fit for "a separate SPA, or a mobile app,
  or any third-party API consumer," which does describe this setup.
- No registration endpoint was added. This is a small internal registry
  tool with one seeded actor type, not a multi-tenant SaaS — adding
  self-service signup would be inventing a business requirement nobody
  asked for (YAGNI). A user is provisioned by seeding/tinker today; if a
  real provisioning workflow is ever needed, that is a fresh, explicitly
  scoped feature (Phase 8B-shaped), not assumed here.

### Why `UserPolicy` returns `true`, not a real per-field/per-record rule

- Inventing a role or ownership model this repo's actual scope doesn't
  need would violate the Design Principles section's YAGNI/KISS
  precedence (standing decision 7: "When principles conflict, KISS and
  YAGNI win") and would be fabricating business logic no one has
  authority to decide here — exactly what ADR-0001 already declined to
  do and explicitly reserved for this phase's real design.
- The orchestrator's directive for this phase explicitly names this
  shape ("a single authenticated-user-can-manage-all-users model is
  appropriate") as the intended outcome, not a shortcut taken without
  authorization.
- `UserPolicy` is still genuinely load-bearing, not a no-op: it is the
  one and only place the real rule ("must be authenticated") is
  centralized and exercised via `$this->authorize()` in every
  `UserController`/`UserAuditController` action — removing it and
  relying on route middleware alone would still work functionally, but
  would discard the one hook this codebase already has for a future real
  per-record rule (e.g. if ownership is ever introduced) without
  rewriting every controller method.
- `restore`/`forceDelete` remain `return false;` (fail-closed) —
  unreachable today (no soft deletes, no routes call them), consistent
  with ADR-0001's original reasoning: no real rule exists to encode for
  an action nothing can trigger.

### Authorization is enforced via `$this->authorize()`, not route middleware alone

Every `UserController`/`UserAuditController` action calls
`$this->authorize(<ability>, ...)` explicitly (via Laravel's
`AuthorizesRequests` trait, added to the shared base `Controller`), in
addition to every route being wrapped in `auth:sanctum` middleware. This
is deliberately belt-and-suspenders: `auth:sanctum` proves *who* the
caller is (or rejects them with 401 if no valid token exists);
`$this->authorize()` proves *what* that caller may do, resolved through
`UserPolicy` by Laravel's own auto-discovery convention — no manual
policy registration needed, as ADR-0001 already confirmed. Today both
checks produce the same practical outcome (authenticated ⇒ allowed), but
keeping them as two distinct steps, not one collapsed check, is what
makes a future real per-record rule a one-file change (`UserPolicy`)
rather than a rewrite of every controller action.

### Supporting fixes bundled into this same change (not separable from "real enforcement")

- **CORS** (`config/cors.php`, closes `docs/TECHNICAL_DEBT.md` P2-9):
  authored deliberately — env-driven `allowed_origins`
  (`CORS_ALLOWED_ORIGINS`), not a wildcard; `supports_credentials: false`
  since auth is a bearer token, not a cookie.
- **Rate limiting** (`AppServiceProvider::configureRateLimiting()`):
  named limiters `api` (60/min, general default via
  `bootstrap/app.php`'s `throttleApi()` — this app had **zero** rate
  limiting of any kind before this phase), `login` (5/min, IP-keyed —
  the login endpoint is a brute-force target), `import` (10/min — the
  CSV bulk-import endpoint is a comparatively expensive, abusable bulk-
  write surface even once authenticated).
- **`UserAuditObserver::performed_by`** now populated
  (`auth('sanctum')->user()?->email`, explicit guard name — the bare
  `auth()` helper resolves the default `web` guard, which this API never
  authenticates through) now that a real authenticated actor exists to
  record. Stays `null` outside an authenticated HTTP request
  (factories/seeders/console) — not a regression, the honest value.
- **CSV-injection defenses** on both the Phase 8B export and import
  paths (`App\Support\Csv\CsvSafety`) — surfaced while reviewing the
  export/import endpoints as part of wiring their authorization, not a
  separate initiative: export sanitizes in place (prefix `'` on any cell
  starting with `=`/`+`/`-`/`@`/tab/CR — a presentation-only safeguard);
  import **rejects** (not silently rewrites) an offending row, since
  mutating a user's submitted data on the way in would be a correctness
  bug, not a safety fix. `email`'s local-part may legitimately start with
  `+`/`-` per RFC 5321/5322 and pass Laravel's own `email` validation
  rule, which is why this check is field-agnostic rather than relying on
  existing per-field validation alone.
- **CSV import upload hardening** (`ImportUsersRequest`): `max:5120`
  (5 MB), `mimes:csv,txt` (extension) + `mimetypes:text/csv,text/plain,
  application/csv,application/vnd.ms-excel` (content-sniffed via
  fileinfo) — neither existed before this phase.

## Alternatives considered

1. **Sanctum SPA/cookie mode instead of tokens.** Rejected — requires the
   SPA and API to share a top-level domain (or `stateful` subdomain
   config) and CSRF cookie handling; this dev topology already has the
   SPA and API on different ports with explicit CORS, which is exactly
   the "separate origin" case Sanctum's own docs say tokens fit, not
   cookies.
2. **A full role/permission package (e.g. `spatie/laravel-permission`).**
   Rejected — no role concept exists in this domain today; adding one
   would be speculative complexity with no present requirement (YAGNI),
   and would need its own `docs/DEPENDENCY_DECISIONS.md` justification
   this repo's actual scope doesn't support.
3. **Route-middleware-only authorization (`can:` middleware), no
   `UserPolicy`/`$this->authorize()` calls.** Rejected — functionally
   equivalent today, but discards the one centralization point a future
   real per-record rule would need; `$this->authorize()` inside each
   action costs nothing extra today and keeps the door open cheaply.
4. **Leave `UserPolicy` returning `false` (ADR-0001's placeholder) and
   enforce everything via `auth:sanctum` middleware alone.** Rejected —
   would make the already-fixed Policy dead weight again (defeats
   ADR-0001's stated purpose: "the Policy Phase 9 wires in"), and
   `false` everywhere would make every real CRUD action permanently
   403 for every authenticated user, which is not the intended model.

## Consequences

- Every `/api/company/users/*` route (CRUD + Phase 8B export/import/
  audit) now requires a valid Sanctum bearer token; an unauthenticated
  request gets 401, not the real data.
- `UserPolicy` is now genuinely exercised (not dead code as ADR-0001
  found it) — every action still passes through it, so a future
  per-record rule change is a one-file edit.
- The existing Nuxt SPA required minimal companion wiring to stay usable
  end-to-end once the backend started requiring auth: a login page
  (`pages/login.vue`), a token-attaching Axios request interceptor plus a
  401-handler that clears the token and redirects to `/login`
  (`plugins/axiosApiClient.ts`), a client-side route guard
  (`middleware/auth.global.js`, UX convenience only — the real
  enforcement is server-side), and a sign-out action in `app.vue`'s nav
  drawer. Deliberately minimal: no account-settings page, no "remember
  me," no password-reset flow — none of those have a real present
  requirement in this app's scope (YAGNI); if one is needed later, it is
  fresh, explicitly scoped work.
- If a real per-record or role-based rule is ever needed, it is now a
  contained `UserPolicy` change, not a rewrite of every controller
  action or a new middleware layer.

## Addendum (2026-11-18): a real 500 found only by manually running the stack

The test suite above was green, but a manual Docker smoke test
(`docker compose up`, real `curl`, no `getJson()`/test-harness shortcuts)
found that an unauthenticated request *without* an `Accept:
application/json` header crashed with
`RouteNotFoundException: Route [login] not defined` instead of returning
401. Every existing test used `getJson()`/`postJson()`, which always send
that header, so nothing exercised this path.

Root cause: `Illuminate\Foundation\Configuration\ApplicationBuilder`
registers a default guest-redirect callback
(`Authenticate::redirectUsing(fn () => route('login'))`) as part of
Laravel's own framework bootstrapping, independent of anything configured
in this app's `bootstrap/app.php`. This app has no web/session guard and
no `login`-named route (the real login page is the separate Nuxt SPA, per
this ADR's own reasoning above), so that default callback throws the
moment a request doesn't declare it wants JSON — which is true of a great
many real, non-malicious clients, not just this smoke test's plain
`curl`. In `APP_DEBUG=true` this also leaked a full stack-trace page
(Ignition) to an unauthenticated caller — an information-disclosure
defect riding on top of the availability one.

Fixed in `bootstrap/app.php`: `$middleware->redirectGuestsTo(fn () =>
null)` removes the crash at its source (no redirect target is ever
computed), and a `$exceptions->render()` callback for
`AuthenticationException` guarantees the same
`{"success":false,"message":"Unauthenticated."}` JSON 401 regardless of
the caller's `Accept` header — consistent with this app being a pure
JSON API with exactly one honest way to reject an unauthenticated
request. A regression test
(`CompanyUserAuthorizationTest::test_index_requires_authentication_even_without_an_accept_json_header`,
using plain `get()`) now covers the path `getJson()` could not.

This is logged here, not as a new finding elsewhere, because it is not a
new or separate problem from this ADR's core subject — it is a defect in
the authentication-enforcement mechanism this ADR itself introduces, found
by actually exercising that enforcement end-to-end rather than only
through its own test suite.

## Verification

- `docker build --target test -f php.dockerfile .` — all tests pass,
  including the new `CompanyUserAuthorizationTest` (one 401 assertion per
  route) and every pre-existing test updated to authenticate via
  `Sanctum::actingAs()` (`tests/TestCase::actingAsApiUser()`).
- Manual smoke test (real Docker container, not just the test suite):
  unauthenticated `GET /api/company/users` → 401; `POST /api/login` with
  valid credentials → 200 + token; same request with the returned
  `Authorization: Bearer <token>` header → 200 with real data.
- `composer audit --locked` — "No security vulnerability advisories
  found" after adding `laravel/sanctum`.
- Zero regression vs. `docs/BASELINE.md`'s 8-test contract — all 8
  original tests still pass (now authenticated), plus new coverage.
