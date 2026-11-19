# Security Policy

## Reporting a vulnerability

**Please do not open a public GitHub issue for a security vulnerability.**

This repository has GitHub's private vulnerability reporting enabled
(confirmed live: `gh api repos/RasimAghayev/taskStarEx/private-vulnerability-reporting`
→ `enabled`). Use it:

1. Go to the [Security tab](https://github.com/RasimAghayev/taskStarEx/security/advisories/new).
2. Click **"Report a vulnerability"**.
3. Describe the issue: affected component (`src/be` or `src/fe`),
   reproduction steps, impact, and any proof-of-concept — redact any
   real credential/token/PII from your report.

If you cannot use GitHub's private reporting for any reason, open a
regular issue asking to be contacted privately, with no vulnerability
detail in it, and a maintainer will follow up.

## Scope

In scope:

- Application code (`src/be` — Laravel API, `src/fe` — Nuxt frontend)
- CI/CD configuration (`.github/workflows/`, `Dockerfile`s)
- Docker images built from this repo (`php.dockerfile`, `node.dockerfile`,
  `nginx.dockerfile`)
- This repo's own dependency manifests/lockfiles

Out of scope:

- Vulnerabilities only reproducible against a fork or an environment this
  repo does not actually run in (it has no staging/production deployment
  — see `docs/DEPLOYMENT.md`)
- Already-documented, accepted residual risks (e.g. the `picomatch`
  ReDoS advisory tracked in `docs/TECHNICAL_DEBT.md` — still worth a
  report if you find a new angle on it, but it is a known, unfixed-
  upstream issue, not a surprise)
- Social engineering, physical access, denial-of-service via sheer
  volume

## Severity and response timeline

| Severity | Example | Target response |
|---|---|---|
| P0 — Critical | Unauthenticated access to PII, auth bypass, RCE | Immediate — acknowledge within 24h, fix prioritized above all other work |
| P1 — High | Privilege escalation, significant data exposure | Acknowledge within 24h, fix targeted within days |
| P2 — Medium | Lower-impact logic flaw, a fixable dependency CVE | Acknowledge within 1 week |
| P3 — Low | Hardening suggestion, defense-in-depth gap | Acknowledged, triaged into `docs/TECHNICAL_DEBT.md` |

This is a single-maintainer personal-account repository, not a company
with a dedicated security team — "response" means the repo owner
personally triages and acts, on a best-effort basis against the targets
above, not a staffed on-call rotation.

## Process after a report

1. **Acknowledge** — confirm receipt, ask clarifying questions if needed.
2. **Triage** — reproduce, assign a severity per the table above, record
   in `docs/TECHNICAL_DEBT.md` once a fix is scoped (the advisory itself
   stays private until resolved).
3. **Fix** — in a branch off `preview` per this repo's normal branch
   model (see `CONTRIBUTING.md`), verified in Docker (`make verify`)
   before merge.
4. **Coordinated disclosure** — the GitHub Security Advisory is published
   (optionally with a CVE) once a fix is merged and released, crediting
   the reporter unless they ask not to be.
5. **Rotate** — if the report involves an exposed secret/credential
   (even a dev-only/placeholder one), it is treated as compromised:
   rotated immediately, and if it reached git history, history is
   rewritten under this program's Approval Gate 1 process (backup
   mirror first, `git filter-branch`/equivalent, tree-hash verified,
   force-push only after approval).

## Supported versions

This repo does not maintain multiple released version lines — there is
one active line (`main`, promoted through `preview` → `test` → `main`).
Fixes land on `main`; there is no backport policy for older tags since
none are published (`docs/adr/0005-no-ghcr-publish-for-interview-task-repo.md`).
