---
name: Bug report
about: Something in taskStarEx is broken or behaves incorrectly
title: "[bug] "
labels: bug
---

<!--
Found a security vulnerability instead? Do NOT file it here — use
private reporting: https://github.com/RasimAghayev/taskStarEx/security/advisories/new
(see SECURITY.md).

Never paste a real secret, token, password, or personal data (even
someone else's) into this issue, even if you think it's redacted. If one
slips in, it is treated as compromised, not just edited out.
-->

## Environment

- Component: `src/be` (Laravel API) / `src/fe` (Nuxt frontend) / infra (Docker/CI)
- How you ran it: `make up` (Docker Compose) / other (describe)
- Docker image tag / commit SHA, if known:
- Stack versions (only if relevant — e.g. `php -v`, `node -v` on the host if not using Docker):

## Steps to reproduce

1.
2.
3.

## Expected behavior

## Actual behavior

## Evidence

<!-- Logs, screenshots, API response bodies. Redact any email/FIN/token/
     password before pasting — e.g. replace with <REDACTED>. -->

## Severity (your assessment; maintainer may adjust)

- [ ] P0 — Critical (data loss, security, totally blocked)
- [ ] P1 — High (major feature broken, no workaround)
- [ ] P2 — Medium (workaround exists)
- [ ] P3 — Low (cosmetic, minor)
