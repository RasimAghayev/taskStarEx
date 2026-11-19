# Test Report

This file holds two independent reports, written by two different
phases, kept in one file because they're both "how long/how well does
the test machinery run" records for the same repo:

1. **CI pipeline timing** (Phase 10) — below.
2. **Test suite results and coverage** (Phase 11) — see that section
   further down.

## CI Pipeline Test Report

Measured against a real GitHub Actions run, not estimated from the YAML.
Written once, in Phase 10 (CI/CD), and updated only if the pipeline shape
changes enough to invalidate these numbers (new job, materially different
image/test size, etc.).

## Budget

Cross-phase CI/CD runner-time standard (`PROMPT.md` section 3/5): PR
pipeline should complete in **≤10 minutes** wall-clock.

## Measured run

- **Repo**: `RasimAghayev/taskStarEx`
- **PR**: [#92](https://github.com/RasimAghayev/taskStarEx/pull/92) (`phase-10/ci-pipeline` → `preview`)
- **Run**: [44283098339](https://github.com/RasimAghayev/taskStarEx/actions/runs/44283098339) — `conclusion: success`, triggered by `pull_request` (`synchronize`), commit `0bef83a` (the final, green commit on the branch at measurement time; two earlier pushes on the same PR are discussed below)
- **Runner**: GitHub-hosted `ubuntu-latest`, no self-hosted runner, no pre-warmed cache beyond `actions/cache`'s `type=gha` scopes

| Timestamp (UTC) | Event |
|---|---|
| 10:12:00 | Run created (push received) |
| 10:13:06 | First job (`changes`) starts — ~66s runner queue/provisioning time |
| 10:13:10 | `changes` completes (4s) |
| 10:13:12 | `backend-quality` / `frontend-quality` start (parallel) |
| 10:14:18 | `backend-quality` completes (1m6s) — slower of the two quality jobs |
| 10:14:20 | `backend-test` starts |
| 10:15:08 | `backend-test` completes (48s) |
| 10:15:11 | `docker-build-validate` starts (needs `backend-test` + `frontend-test`) |
| 10:16:45 | `docker-build-validate` completes (1m34s) — slowest single job |
| 10:16:46 | Run marked completed |

**Total wall-clock: 4 minutes 46 seconds (286s), 53% of the 600s budget.**

### Per-job durations (this run, both path filters true — worst case: backend + frontend both touched)

| Job | Duration | Notes |
|---|---:|---|
| `changes` | 4s | `dorny/paths-filter`, no checkout of app code needed |
| `backend-quality` | 1m6s | `pint --test` + Larastan inside `docker build --target build -f php.dockerfile .` |
| `frontend-quality` | 56s | `@nuxt/eslint` inside `docker build --target build -f node.dockerfile .` |
| `backend-test` | 48s | `docker build --target test -f php.dockerfile .` — 55 tests / 161 assertions |
| `frontend-test` | 35s | `docker build --target test -f node.dockerfile .` — build-output characterization |
| `backend-audit` | 15s | `composer audit --locked` + allowlist `jq` filter |
| `frontend-audit` | 21s | `npm audit` (both `src/be`'s Vite manifest and `src/fe`'s) + allowlist `jq` filter |
| `docker-build-validate` | 1m34s | **slowest job** — `make env` + `docker compose config -q` + 3 sequential `docker/build-push-action` runtime-image builds (`php`, `nginx`, `fe`) |

### Critical path

`changes` (4s) → `backend-quality` (1m6s, slower than `frontend-quality`'s 56s) →
`backend-test` (48s) → `docker-build-validate` (1m34s) = **3m32s of actual job
time**, plus ~66s of one-time runner queue/provisioning at the start and a
few seconds of inter-job scheduling gaps, totalling the measured 4m46s.

`docker-build-validate` is the single slowest job and the tail of the
critical path — it builds three separate runtime images sequentially
rather than as a matrix, which is a deliberate simplicity trade-off (one
job, one clear pass/fail, `cache-from`/`cache-to: type=gha` per-scope so a
re-run of just one image's `Dockerfile` doesn't invalidate the other two's
cache) rather than an oversight. At 1m34s against a 10-minute budget there
is no present need to parallelize it further (KISS/YAGNI) — revisit only if
a future image addition pushes this job close to the budget.

## Prior (red) run, for contrast

[Run 44281068441](https://github.com/RasimAghayev/taskStarEx/actions/runs/44281068441)
(commit `2049c6e`, the pipeline's first real execution) failed in
`docker-build-validate`'s "Validate docker-compose.yml" step after only
12s: `docker compose config -q` ran on a bare checkout with no `.env`
(gitignored by design) and failed closed on the `pgsql` service's required
variable interpolation. All 7 other jobs were already green on that run.
Fixed by commit `f2f3255` (`make env` before the validate step) — see
`CHANGELOG.md`'s Phase 10 section and `docs/TECHNICAL_DEBT.md`. This is the
exact kind of gap that only a real CI run surfaces, not local
`docker build`/`docker compose` testing (which always had a conveniently
pre-existing local `.env`).

## Caching

- `docker/build-push-action`'s `cache-from`/`cache-to: type=gha` is scoped
  per Dockerfile+purpose (`php-be`, `node-fe`, `nginx`, and a separate
  implicit scope per quality/test job's own `docker build` step) so a
  change to one Dockerfile doesn't cold-start the others' layers.
- This measured run followed two earlier pushes on the same PR (the red
  run above, and one docs-only commit), so GitHub Actions' `type=gha`
  cache was already warm for every `apt`/`composer`/`npm` layer by the
  time this run executed. The red run (`44281068441`, the pipeline's
  actual first-ever execution, genuinely cold cache) still finished its 7
  passing jobs in comparable per-job times to this one (`backend-quality`
  2m13s, `frontend-quality` 2m17s, `backend-test` 41s, `frontend-test`
  40s, `backend-audit` 19s, `frontend-audit` 1m0s) — roughly double the
  quality jobs' warm-cache time, consistent with a first-time
  `composer install`/`npm ci` paying for dependency download once. Even
  at those cold-cache per-job times, total wall-clock would still land
  well under the 10-minute budget. If a future run is ever materially
  slower than either of these two data points, re-measure and update this
  file rather than assume.

## Conclusion

**Budget met.** 4m46s measured wall-clock against a 10-minute target,
across all 8 jobs, both `src/be` and `src/fe` path filters triggered
(the worst case — a PR touching only one side is faster since the other
side's quality/test/audit jobs are skipped entirely, though
`docker-build-validate` still builds whichever runtime image(s) the
changed side owns, plus `nginx` unconditionally since both sides depend on
it).

## Test Suite Results and Coverage Report

Written in Phase 11 (tester). All numbers below are from real Docker
builds run on this phase's work, not estimated — command and exit code
given for each. See `docs/TESTING_STRATEGY.md` for what is and isn't
tested and why, and `docs/BASELINE.md` for the full historical backend
test-count progression (the regression contract).

### Backend (`src/be`, PHPUnit 13)

**Command**: `docker build --target test -f php.dockerfile -t taskstarex-be-test:p11-fixed .`
**Result**: exit 0

```
Tests:    66 passed (185 assertions)
```

| | Before Phase 11 | After Phase 11 | Delta |
|---|---:|---:|---:|
| Tests | 55 | 66 | +11 |
| Assertions | 161 | 185 | +24 |

The 11 new tests (see `docs/BASELINE.md`'s Phase 11 addendum for the
full list) close the gaps identified in this phase's brief:
`UserPolicyTest` (7 tests locking in the real ADR-0004 authorization
model — see the "correction" section of `docs/TESTING_STRATEGY.md`),
2 new `CompanyUserExportTest` cases (CSV-injection-safe export of data
that reached the database by a route other than the validated HTTP
request), and 2 new `CompanyUserImportTest` cases (content-sniffed MIME
rejection, oversized-file rejection).

**Coverage**: not measured. Neither `pcov` nor `xdebug` is installed in
`php.dockerfile`'s `base` stage (confirmed:
`docker run --rm taskstarex-be-test:p11-fixed php -m | grep -iE
"xdebug|pcov"` → no match, exit 1), so `php artisan test --coverage` has
no driver to run against. Adding one is a `base`-stage change —
every stage including `runtime` builds on `base` — which is a larger,
riskier change than this phase's narrow "tests only" remit, and a new
system dependency besides (standing decision 3: needs a
`docs/DEPENDENCY_DECISIONS.md` entry, which in turn wants a real "do we
actually want this permanently" decision, not one made in passing to
produce a number for this report). Flagged as a known limitation and a
candidate follow-up (either a future phase, or a dedicated, approved
addition here) rather than silently skipped or faked.

### Frontend (`src/fe`, Vitest 5 + happy-dom)

**Command**: `docker build --target test -f node.dockerfile -t taskstarex-fe-test:p11 .`
**Result**: exit 0

```
Test Files  3 passed (3)
     Tests  19 passed (19)
  Duration  1.20s
```

This is the first test suite that has ever existed for `src/fe` — there
is no "before" column. All 3 files/19 tests cover the plain-JS service
and constant modules (`authService.js`, `companyUsersService.js`,
`constants/userStatus.js`); see `docs/TESTING_STRATEGY.md` for why
component/page tests were ruled out of scope this phase.

**Coverage** (`docker run --rm taskstarex-fe-test:p11 npm run test:coverage`,
exit 0, v8 provider):

| File | % Stmts | % Branch | % Funcs | % Lines | Uncovered |
|---|---:|---:|---:|---:|---|
| All files | 97.14 | 81.81 | 100 | 97.14 | |
| `constants/userStatus.js` | 100 | 100 | 100 | 100 | — |
| `services/authService.js` | 93.75 | 75 | 100 | 93.75 | line 29 |
| `services/companyUsersService.js` | 100 | 100 | 100 | 100 | — |

The one uncovered line is `authService.js:29` — the `return false` branch
of `isAuthenticated()` taken when `!import.meta.client` (the SSR path).
`vitest.config.ts` statically defines `import.meta.client` to `'true'`
for the whole suite (replicating what Nuxt's own Vite plugin does at
build time, per that file's comment), so the SSR branch of this one
function is never exercised by this suite. Documented here rather than
hidden; a true SSR-path test would need a second Vitest project/config
with the macro flipped, which was judged disproportionate for one
single-line branch (KISS/YAGNI) — revisit if `isAuthenticated()` grows
real SSR-specific logic.

### Build-output characterization (both languages)

Both Dockerfiles' `test` stages also assert the production build
actually produced a runnable artifact
(`test -f .output/server/index.mjs` for `src/fe`; the backend has no
equivalent separate build-output artifact to check, since
`php artisan test` running at all already proves the application
bootstraps). This is a different defect class than the unit suites above
— it would catch a broken build that no unit test exercises — and both
passed as part of the same Docker builds reported above (exit 0).

### Conclusion

Both languages' Docker test-stage builds are green with real, executed
tests (no test was weakened, skipped, or reported passing without
having actually run). Backend regression floor moved from 55/161 to
66/185 with zero existing tests altered. Frontend went from no test
infrastructure at all to a 19-test suite at 97% statement coverage on
the code that exists. Known, documented gaps: backend code-coverage
percentage (no driver installed, flagged above, not fabricated) and the
frontend SSR-only branch noted above.
