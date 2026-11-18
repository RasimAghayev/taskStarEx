# ADR-0005: Do not wire a GHCR publish-on-tag job for this repo

- **Status**: Accepted
- **Date**: 2026-11-18
- **Phase**: 10 (CI/CD)

## Context

The cross-phase CI/CD runner-time standard (`PROMPT.md` section 5, Phase 10
definition) directs images to publish to `ghcr.io/rasimaghayev/<repo>` on a
tag cut from `main` (SemVer + commit-SHA + `latest`-from-main tags), after
the quality gate is green. The Phase 10 orchestrator directive for this
repo explicitly asked for a judgment call rather than blind compliance:
build the publish pipeline only if there is a real reason to, and document
the decision either way.

Evidence considered:

- `docs/REPOSITORY_AUDIT.md` / `projects/info/analysis/taskStarEx.md`:
  this repo's own README identifies it verbatim as "Interview Task: User
  Management System" — a take-home assignment / portfolio scaffold, not a
  service with real users or real traffic.
- `gh api repos/RasimAghayev/taskStarEx/git/refs/tags` and
  `gh release list` — **zero tags, zero releases exist** at the time of
  this phase. Nothing has ever been "cut" from `main` for this repo.
- `gh api user/packages?package_type=container` scoped checks and the
  repo's own history show no prior GHCR publishing activity for this repo.
- The 3-branch model (`preview`→`test`→`main`) exists and is enforced
  (Phase 4), so the *mechanism* a publish-on-tag job would hook into is in
  place — the gap is purely "is there a real consumer for the resulting
  images," not "is the branch model ready for it."
- This repo's own `docker-compose.yml` produces three separate runtime
  images (`nginx`, `php`, `fe`) wired only to each other over `localhost`
  ports on a single developer machine or CI runner — there is no
  deployment target (no platform/umbrella repo per
  `docs/IMPROVEMENT_ROADMAP.md`'s Phase 16 = n/a, no real environment per
  Gate 9's scope) that would ever pull these images from a registry.

## Decision

**Do not add a GHCR publish-on-tag workflow for this repo.** The
`docs/TECHNICAL_DEBT.md` P3-9 entry (added this phase) and this ADR are the
documented judgment call the orchestrator directive asked for, in place of
a publish pipeline nobody would use.

What Phase 10 adds instead, satisfying the spirit of the runner-time
standard's "build the image once, reuse it, validate before anything ships"
intent without the unused publish step:

- `docker-build-validate` job in `.github/workflows/ci.yml` builds all
  three `runtime`-target images (`php.dockerfile`, `node.dockerfile`,
  `nginx.dockerfile`) plus `docker compose config -q` on every PR/push —
  this is the real, present value (catching a broken runtime image before
  merge), without the no-consumer publish step.

This is not a permanent prohibition. If this repo's purpose ever changes —
e.g. it becomes a deployed reference/demo app, or a tag is cut for a
specific reason — the exact `docker build --target runtime` commands
already proven working (Phase 5/6/9's own verification, and this phase's
own local verification before pushing) are the bulk of what a publish job
would need; adding `docker/login-action` + `docker/build-push-action` with
`push: true` and GHCR tags, gated on `on.push.tags` and `github.ref ==
'refs/heads/main'`, is a small, additive change at that point — not a
redesign.

## Alternatives considered

1. **Build the publish-on-tag job anyway, per the standard's literal
   text, dormant until a tag is cut.** Rejected: a workflow that will
   never run for a repo with zero tags and no deployment target is exactly
   the kind of speculative scope standing decision 7 (KISS/YAGNI win
   conflicts) and the "don't over-engineer" working-procedure note warn
   against. It would also need `packages: write` permission declared
   somewhere, widening the permissions surface for a job with no real
   trigger — a cost with no offsetting benefit.
2. **Publish once, now, as a demonstration/proof that the pipeline
   works, then never again.** Rejected: this repo has never been tagged;
   manufacturing a tag purely to exercise a publish job would be
   theater, not a real release, and would leave a real, permanent
   `ghcr.io/rasimaghayev/taskstarex` package that nothing consumes —
   against the same KISS/YAGNI reasoning, and arguably worse (visible
   artifact vs. an unused workflow file).

## Consequences

- No `packages: write` permission is requested anywhere in
  `.github/workflows/ci.yml` — the whole pipeline stays at
  `permissions: contents: read`, least-privilege.
- No GHCR package is created for this repo. If one is later wanted, that is
  a new, explicit decision (and a new ADR superseding this one), not a
  silent drift.
- `docs/TECHNICAL_DEBT.md` P3-9 cross-references this ADR so a future
  phase (or the owner) sees the reasoning without re-deriving it.

## Verification

- `gh api repos/RasimAghayev/taskStarEx/git/refs/tags` — empty.
- `gh release list --repo RasimAghayev/taskStarEx` — empty.
- `.github/workflows/ci.yml` grepped for `packages:`/`GHCR`/`ghcr.io`/
  `docker/login-action` — no hits; the only `docker/*` actions used are
  `setup-buildx-action` and `build-push-action` with `push: false` on
  every step.
