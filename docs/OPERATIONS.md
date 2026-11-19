# Operations

> Phase 15. Day-to-day running notes for the one environment this repo
> actually has (local/dev Docker Compose — see `docs/DEPLOYMENT.md`).
> Written from the real code (`bootstrap/app.php`, `HealthController`,
> `AppServiceProvider`, `docker-compose.yml`), not invented.

## Health checks

| Endpoint | Checks | Use |
|---|---|---|
| `GET /health/live` | Nothing external — only that PHP-FPM is executing application code | Liveness: an orchestrator should restart the container only if this fails. |
| `GET /health/ready` | `select 1` against PostgreSQL | Readiness: can this instance serve real traffic right now. Returns `503` with `{"status":"error","checks":{"database":"error"}}` if the DB is unreachable; the underlying exception is logged server-side only, never in the response body. |
| `GET /up` | Laravel's own built-in check | Framework default, kept alongside the two above. |

Both `/health/*` routes are registered outside the `web`/`api` middleware
groups (`bootstrap/app.php`), the same convention Laravel itself uses for
`/up` — a probe never pays CSRF/session overhead and is never subject to
the API's CORS or rate-limit policy.

`php.dockerfile`'s runtime image has a Docker `HEALTHCHECK` that calls
`/health/live` over FastCGI directly (`cgi-fcgi`) and greps for
`"status":"ok"` — not a bare process check (`pgrep`), which previously
produced a false-negative "unhealthy" status that silently blocked
`nginx` from starting even though php-fpm was fine (`docs/TECHNICAL_DEBT.md`
P2-10).

## Logging

Standard Laravel logging (`LOG_CHANNEL=stack`, single file channel by
default). Structured `Log::info`/`Log::warning`/`Log::error` calls exist
at these points, all introduced in Phase 9:

- `AuthController::login` — failed attempt (`email`, `ip`) and successful
  login (`user_id`, `ip`).
- `AuthController::logout` — `user_id`.
- `UserController::import` — one summary line per CSV import
  (`user_id`, `created`, `failed`, `failed_rows`) — **never** the
  per-row error messages themselves, since those may echo
  user-submitted values.
- `UserAuditObserver::record` — logs (does not throw) if writing an
  audit-log row itself fails; an audit-write failure must never block or
  roll back the business operation it's observing.
- `HealthController::ready` — logs the exception message (not the full
  response) when the readiness DB check fails.

No secret or personal-data value is ever logged in full (see
`docs/SECURITY_REVIEW.md`).

## Rate limiting

Three named limiters (`App\Providers\AppServiceProvider::configureRateLimiting`):

| Limiter | Limit | Applies to | Why |
|---|---|---|---|
| `api` | 60/min, keyed by authenticated user id or IP | every `/api/*` route (`bootstrap/app.php`'s `throttleApi()`) | General abuse protection; this app had **zero** rate limiting before Phase 9. |
| `login` | 5/min, keyed by IP | `POST /api/login` | Brute-force target — tighter than the general limiter. |
| `import` | 10/min, keyed by user id or IP | `POST /api/company/users/import` | An expensive, abusable bulk-write surface even once authenticated. |

## Database

- One real business table (`company_users`), one append-only audit table
  (`company_user_audit_logs`, no foreign key back to `company_users` by
  design — a row must survive the parent row's hard deletion), plus
  Sanctum's `personal_access_tokens` and Laravel's default (unused)
  `users`/`password_reset_tokens`/`sessions`/cache/jobs tables. Full
  schema: `docs/DIAGRAMS.md`'s data-model diagram.
- Migrations: `make migrate` (`php artisan migrate --force` inside the
  `php-dev` tool-runner container against the `pgsql` Compose service).
- Seeding: `make seed`. `DatabaseSeeder` seeds exactly one row into the
  `users` table — note this table is Laravel's *default* scaffolding
  (`docs/ARCHITECTURE.md`'s Phase 3 snapshot flagged it as unused), but
  Phase 9 wired real Sanctum authentication against it
  (`AuthController::login` looks up `User::where('email')`), so as of
  this phase it is genuinely live, not dead: it's the credential you log
  in with in dev. `password_reset_tokens`/`sessions`/the cache/jobs
  tables remain unused.
- Backups: none automated (no real environment to back up). The
  `pgsql_data` Docker volume is the only persistence; `make clean` (which
  requires `CONFIRM=1`) deletes it along with `be_storage`.

## Runbook: common situations

**`nginx` won't start / stuck behind a healthcheck.**
Check `docker inspect --format='{{.State.Health.Status}}'` on the `php`
container. If unhealthy, check `/health/live` manually
(`docker compose exec php cgi-fcgi -bind -connect 127.0.0.1:9000 ...` or
simply hit it through `nginx` once it's briefly reachable) — see
`docs/TECHNICAL_DEBT.md` P2-10 for the exact prior failure mode this
replaced.

**A CSV import reports partial failures.**
Expected behavior, not a bug: `UserController::import` validates each row
independently and reports per-row errors in the JSON response
(`{data:{created, failed, errors:[{row, fin, messages}]}}`) rather than
aborting the whole file on the first bad row. Check the structured log
line (`Company user CSV import completed`) for the summary; row-level
messages are only in the HTTP response, by design (they may contain
user-submitted values).

**Dependency/CI audit job fails red.**
`backend-audit`/`frontend-audit` (`.github/workflows/ci.yml`) fail
closed on any **new** critical/high advisory. One standing accepted-risk
advisory (`GHSA-fc5q-39xc-vjpf`, `picomatch` ReDoS, no upstream fix at any
current `vite`/`laravel-vite-plugin`/`nuxt` version) is allow-listed by ID
— see `docs/TECHNICAL_DEBT.md` P1-5. If the job is red, check whether the
new finding is actually a different advisory before assuming it's this
one.

**A Dependabot/Renovate PR is open.**
Target branch is always `preview` (standing decision 5/14). Triage per
the standing-decision-1 re-attempt rule: merge if green and non-breaking
for this repo's own contract (Gate 5 applies to breaking bumps); if
blocked, update the written blocker in `docs/TECHNICAL_DEBT.md` rather
than silently closing it.

## Monitoring

No external monitoring/alerting (Datadog, Sentry, etc.) is wired — there
is no real environment to monitor. `GET /health/ready` is the only signal
available today; if this repo ever gets a real deployment target, wiring
real observability is explicitly Phase 9-class scope to revisit, not
assumed here.
