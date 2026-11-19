# Security Review

> Phase 15. A point-in-time summary of this repo's security posture **as
> implemented**, consolidating findings already established and verified
> in `docs/ARCHITECTURE.md`, `docs/TECHNICAL_DEBT.md`, the ADRs, and the
> Phase 9/11 handoffs. This file does not re-run those checks; it cites
> them. For *how to report* a vulnerability, see `SECURITY.md` — this file
> is about current state, not process.

## Authentication & authorization

- **Mechanism**: Laravel Sanctum, personal-access tokens
  (`Authorization: Bearer <token>`), not SPA/cookie-session auth.
  Rationale and alternatives considered: `docs/adr/0004-wire-authentication-and-authorization.md`.
- **Coverage**: every `/api/company/users/*` route (CRUD, export,
  import, audit) requires `auth:sanctum`. Before Phase 9 this was **zero**
  — full PII, fully CRUD-able, unauthenticated. Verified by a dedicated
  unauthenticated-401 test per route (`CompanyUserAuthorizationTest`).
- **Authorization model**: single-actor model — `UserPolicy`'s 5 real
  methods (`viewAny`/`view`/`create`/`update`/`delete`) all return
  `true` once authenticated (no role/ownership concept exists in this
  domain; `restore`/`forceDelete` remain fail-closed `return false;`
  scaffolding, unused since this app has no soft-delete). Locked in by
  `UserPolicyTest` (7 tests).
- **Login brute-force protection**: `login` rate limiter, 5/min by IP
  (tighter than the general `api` limiter) — see `docs/OPERATIONS.md`.

## Input validation

- `StoreUserRequest`/`UpdateUserRequest` — regex-constrained
  name/surname/father fields, FIN-format validation, email format +
  uniqueness, enum-constrained `status`. Real, substantive validation,
  not placeholder rules.
- `ImportUsersRequest` — file-upload hardening: content-sniffed MIME type
  (`mimes:csv,txt`, not just the client-supplied extension/header),
  max-size limit (5120 KB). Verified by `CompanyUserImportTest`.
- CSV import additionally rejects (does not silently rewrite) any row
  whose raw field value starts with a formula-trigger character
  (`=`, `+`, `-`, `@`) before it reaches validation — see CSV-injection
  below.

## CSV injection (export & import)

Both directions are addressed, deliberately differently:

- **Export** (`UserController::export`): every string cell is sanitized
  via `App\Support\Csv\CsvSafety::sanitizeForExport()` regardless of
  field — field-level input validation alone doesn't guarantee a
  formula-trigger character can never reach this point (e.g. an email
  local-part may legitimately start with `+`). Verified by
  `CompanyUserExportTest` against data that reached the database by a
  route other than the validated HTTP request.
- **Import**: rejects the row outright rather than mutating it —
  silently rewriting a user's submitted data on the way in would be a
  correctness bug, not an acceptable safety fix for an import path.

## SQL injection

Not a present concern: the one dynamic query
(`UserRepository::index()`'s search) uses Eloquent's parameterized query
builder throughout — no raw string concatenation into SQL anywhere in
this codebase (confirmed by the Phase 3 audit and unchanged since).

## CORS

`config/cors.php` (added Phase 9 — previously absent, meaning Laravel's
own wildcard-allow default silently applied). `allowed_origins` is
env-driven (`CORS_ALLOWED_ORIGINS`), defaults to the dev Nuxt container's
own published port, never `*`. `supports_credentials: false` — this API
uses bearer-token auth, not cookies, so credentialed CORS mode is not
needed. See `docs/DEPLOYMENT.md`.

## Rate limiting

See `docs/OPERATIONS.md` — `api` (60/min), `login` (5/min), `import`
(10/min). Zero rate limiting existed before Phase 9.

## Secrets

- No secret found in the working tree or git history as of the most
  recent scan (`gitleaks dir . --redact`, re-run through Phase 3/4/9).
- Repo-level GitHub security settings (verified live via
  `gh api repos/RasimAghayev/taskStarEx` on 2026-11-19, not assumed from
  an earlier phase's note):

  | Setting | Status |
  |---|---|
  | `secret_scanning` | `enabled` |
  | `secret_scanning_push_protection` | `enabled` |
  | `dependabot_security_updates` | `enabled` |
  | Private vulnerability reporting (`/private-vulnerability-reporting`) | `enabled` |
  | `secret_scanning_validity_checks` | `disabled` |
  | `secret_scanning_non_provider_patterns` | `disabled` |

  The last two are GitHub Advanced-Security-tier refinements on top of
  an already-enabled base secret-scanning setup, not a gap in the
  base protection itself; noted here for completeness rather than left
  unmentioned.
- `docker-compose.yml`'s Postgres credentials: a local-only, env-driven
  placeholder value (`POSTGRES_PASSWORD:?Set ... in .env`), not a
  hardcoded live secret — see `docs/DEPLOYMENT.md`.
- No secret is ever written to a Docker image layer (`ARG`/`ENV`/`COPY`);
  `.dockerignore` excludes `.env*`, keys, and `.git`.

## Dependency vulnerabilities (current, as of this phase)

- `src/be` (Composer): `composer audit --locked` → **0 advisories**
  (the one critical CVE this repo had, `CVE-2026-114509`, was cleared in
  Phase 6 by the Laravel 11→13 bump).
- `src/be`/`src/fe` (npm): one standing accepted-risk advisory,
  `GHSA-fc5q-39xc-vjpf` (`picomatch` ReDoS), present on both ecosystems'
  Vite-based toolchains with **no upstream fix at any current version**
  of `vite`/`laravel-vite-plugin`/`nuxt` (re-verified live against the
  registries multiple times across Phases 6/9). Allow-listed by GHSA ID
  in CI (`.github/workflows/ci.yml`'s `*-audit` jobs) so it doesn't
  perpetually fail the pipeline; any **other** critical/high finding
  still fails CI. Full detail and re-verification history:
  `docs/TECHNICAL_DEBT.md` P1-5 and its "standing-decision-1 written
  blockers" section.

## Known residual gaps (not fixed by this phase — documentation only)

- Backend code-coverage percentage is unmeasured (no `pcov`/`xdebug` in
  the PHP image) — not a security defect, but relevant to how much the
  passing test suite above actually proves. See `docs/TEST_REPORT.md`.
- `docs/TECHNICAL_DEBT.md`'s P2/P3 items not otherwise listed above
  (dead code, stylistic items) carry no security implication and are
  tracked there, not duplicated here.

## Conclusion

The repo's single largest historical security gap — a fully
unauthenticated, fully CRUD-able PII API — is closed and verified
(Phase 9, locked in by Phase 11's tests). Remaining findings are either
resolved (dependency CVEs) or documented, accepted residual risk with no
available upstream fix (`picomatch`). No open, unaddressed, unacknowledged
finding exists as of this phase.
