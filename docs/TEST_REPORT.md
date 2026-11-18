# CI Pipeline Test Report

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
